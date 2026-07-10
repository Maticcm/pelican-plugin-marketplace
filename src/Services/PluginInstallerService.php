<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\Server;
use Illuminate\Support\Str;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;
use PelicanMarketplace\PluginMarketplace\Exceptions\PluginAlreadyInstalledException;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobStatus;

/**
 * Downloads, validates and writes a plugin jar onto a server's
 * filesystem through Wings, then records it in the installed-plugins
 * table. Never touches the filesystem directly - every write goes
 * through {@see DaemonFileRepositoryFactory}, exactly like the panel's
 * own server file manager.
 */
class PluginInstallerService
{
    public function __construct(
        private readonly DownloadManagerService $downloader,
        private readonly JarValidatorService $validator,
        private readonly PluginMetadataService $metadataService,
        private readonly DaemonFileRepositoryFactory $fileRepositories,
    ) {}

    /**
     * @throws MarketplaceException
     * @throws PluginAlreadyInstalledException
     */
    public function install(
        Server $server,
        MarketplacePluginData $plugin,
        MarketplaceVersionData $version,
        bool $overwrite = false,
        ?PluginJob $job = null,
    ): InstalledPlugin {
        throw_unless($version->downloadUrl, new MarketplaceException('This version has no downloadable file (it may only be available as a manual download).'));

        $fileName = $this->safeFileName($version->fileName ?? (Str::slug($plugin->name) . '.jar'));

        $directory = rtrim((string) config('plugin-marketplace.downloads.directory', '/plugins'), '/');

        $existing = InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->where('file_name', $fileName)
            ->first();

        if ($existing !== null && !$overwrite) {
            throw new PluginAlreadyInstalledException("\"$fileName\" is already installed on this server. Confirm to overwrite it.");
        }

        $job?->markStatus(InstallJobStatus::Downloading, "Downloading $fileName from " . $plugin->repository->getLabel() . '...');
        $bytes = $this->downloader->download($version->downloadUrl);

        $job?->markStatus(InstallJobStatus::Validating, 'Validating jar file...');
        $this->validator->validate($bytes, $fileName);

        $job?->markStatus(InstallJobStatus::Installing, "Uploading $fileName to the server...");
        $fileRepository = $this->fileRepositories->forServer($server);
        $fileRepository->putContent("$directory/$fileName", $bytes);

        $metadata = $this->metadataService->extractFromJarBytes($bytes, pathinfo($fileName, PATHINFO_FILENAME));

        return InstalledPlugin::query()->updateOrCreate(
            ['server_id' => $server->id, 'file_name' => $fileName],
            [
                'name' => $metadata['name'] ?? $plugin->name,
                'version' => $metadata['version'] ?? $version->versionNumber,
                'authors' => $metadata['authors'] ?? [$plugin->author],
                'description' => $metadata['description'] ?? $plugin->summary,
                'main_class' => $metadata['main'] ?? null,
                'api_version' => $metadata['api_version'] ?? null,
                'depend' => $metadata['depend'] ?? [],
                'softdepend' => $metadata['softdepend'] ?? [],
                'size' => strlen($bytes),
                'enabled' => true,
                'repository' => $plugin->repository->value,
                'project_id' => $plugin->projectId,
                'version_id' => $version->id,
                'checksum' => hash('sha256', $bytes),
                'latest_version' => $version->versionNumber,
                'update_available' => false,
                'installed_at' => now(),
                'last_scanned_at' => now(),
            ],
        );
    }

    /**
     * Reduces an upstream-provided file name down to a single, safe
     * basename, so a malicious/malformed `files[].filename` from a
     * repository API can never be used to write outside the plugins
     * directory.
     */
    private function safeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?? $fileName;

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $fileName = Str::uuid() . '.jar';
        }

        if (!str_ends_with(strtolower($fileName), '.jar')) {
            $fileName .= '.jar';
        }

        return $fileName;
    }
}
