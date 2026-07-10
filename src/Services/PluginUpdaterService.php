<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Log;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobStatus;
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use Throwable;

/**
 * Updates an already-installed plugin to a newer version: backs up the
 * current jar (outside of `/plugins`, so the backup itself is never
 * picked up and loaded as a plugin), downloads and validates the new
 * jar, writes it over the old one, and - if anything after the backup
 * step fails - restores the original bytes so a failed update never
 * leaves the server without a working copy of the plugin.
 */
class PluginUpdaterService
{
    private const BACKUP_DIRECTORY = '/.pelican-plugin-marketplace/backups';

    public function __construct(
        private readonly DownloadManagerService $downloader,
        private readonly JarValidatorService $validator,
        private readonly PluginMetadataService $metadataService,
        private readonly MarketplaceSettingsService $settings,
        private readonly DaemonFileRepositoryFactory $fileRepositories,
    ) {}

    /** @throws MarketplaceException */
    public function update(
        Server $server,
        InstalledPlugin $installedPlugin,
        MarketplacePluginData $plugin,
        MarketplaceVersionData $version,
        ?PluginJob $job = null,
    ): InstalledPlugin {
        throw_unless($version->downloadUrl, new MarketplaceException('This version has no downloadable file (it may only be available as a manual download).'));

        $directory = rtrim((string) config('plugin-marketplace.downloads.directory', '/plugins'), '/');
        $path = "$directory/{$installedPlugin->file_name}";

        $fileRepository = $this->fileRepositories->forServer($server);

        $backupPath = null;
        $originalBytes = null;

        if ($this->settings->backupsEnabled()) {
            $job?->markStatus(InstallJobStatus::BackingUp, "Backing up {$installedPlugin->file_name}...");

            try {
                $originalBytes = $fileRepository->getContent($path, $this->settings->maxDownloadSizeBytes());
                $backupPath = self::BACKUP_DIRECTORY . '/' . $installedPlugin->file_name . '.' . now()->format('YmdHis') . '.bak';
                $fileRepository->putContent($backupPath, $originalBytes);
            } catch (Throwable $exception) {
                Log::warning("[plugin-marketplace] Could not back up \"{$installedPlugin->file_name}\" before updating on server #{$server->id}", ['exception' => $exception->getMessage()]);
                $backupPath = null;
            }
        }

        $job?->markStatus(InstallJobStatus::Downloading, "Downloading {$version->fileName}...");
        $newBytes = $this->downloader->download($version->downloadUrl);

        $job?->markStatus(InstallJobStatus::Validating, 'Validating new jar file...');

        try {
            $this->validator->validate($newBytes, $installedPlugin->file_name);

            $job?->markStatus(InstallJobStatus::Installing, "Installing {$version->versionNumber}...");
            $fileRepository->putContent($path, $newBytes);
        } catch (Throwable $exception) {
            if ($originalBytes !== null) {
                $this->rollback($fileRepository, $path, $originalBytes, $job);
            }

            throw $exception instanceof MarketplaceException ? $exception : new MarketplaceException('Update failed: ' . $exception->getMessage(), previous: $exception);
        }

        $metadata = $this->metadataService->extractFromJarBytes($newBytes, pathinfo($installedPlugin->file_name, PATHINFO_FILENAME));

        $installedPlugin->update([
            'name' => $metadata['name'] ?? $installedPlugin->name,
            'version' => $metadata['version'] ?? $version->versionNumber,
            'authors' => $metadata['authors'] ?? $installedPlugin->authors,
            'description' => $metadata['description'] ?? $installedPlugin->description,
            'main_class' => $metadata['main'] ?? $installedPlugin->main_class,
            'api_version' => $metadata['api_version'] ?? $installedPlugin->api_version,
            'depend' => $metadata['depend'] ?? $installedPlugin->depend,
            'softdepend' => $metadata['softdepend'] ?? $installedPlugin->softdepend,
            'size' => strlen($newBytes),
            'repository' => $plugin->repository->value,
            'project_id' => $plugin->projectId,
            'version_id' => $version->id,
            'checksum' => hash('sha256', $newBytes),
            'latest_version' => $version->versionNumber,
            'update_available' => false,
            'last_scanned_at' => now(),
        ]);

        return $installedPlugin->refresh();
    }

    private function rollback(DaemonFileRepository $fileRepository, string $path, string $originalBytes, ?PluginJob $job): void
    {
        try {
            $fileRepository->putContent($path, $originalBytes);
            $job?->markStatus(InstallJobStatus::RolledBack, 'Update failed - the previous version was restored.');
        } catch (Throwable $exception) {
            Log::error('[plugin-marketplace] Rollback after failed update also failed - manual intervention may be required', [
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            $job?->markStatus(InstallJobStatus::Failed, 'Update failed and the automatic rollback also failed. Please check the plugin manually.');
        }
    }
}
