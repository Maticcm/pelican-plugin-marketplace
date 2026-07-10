<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use Throwable;

/**
 * Scans a server's `/plugins` directory over Wings and reconciles it
 * with the `plugin_marketplace_installed_plugins` table: new jars are
 * added (with metadata extracted from their plugin.yml), removed jars
 * are pruned, and existing jars are refreshed in place so the Installed
 * Plugins page always reflects what is actually on disk rather than
 * only what this plugin itself has ever installed.
 */
class PluginScannerService
{
    public function __construct(
        private readonly PluginMetadataService $metadata,
        private readonly MarketplaceSettingsService $settings,
        private readonly DaemonFileRepositoryFactory $fileRepositories,
    ) {}

    /**
     * @return Collection<int, InstalledPlugin>
     *
     * @throws MarketplaceException
     */
    public function scan(Server $server): Collection
    {
        $directory = rtrim((string) config('plugin-marketplace.downloads.directory', '/plugins'), '/');

        try {
            $fileRepository = $this->fileRepositories->forServer($server);
            $entries = $fileRepository->getDirectory($directory);
        } catch (ConnectionException $exception) {
            throw new MarketplaceException("Could not reach the daemon to list \"$directory\": {$exception->getMessage()}", previous: $exception);
        } catch (Throwable $exception) {
            throw new MarketplaceException("Could not list \"$directory\" on this server: {$exception->getMessage()}", previous: $exception);
        }

        $jarEntries = collect($entries)->filter(fn (array $entry) => ($entry['file'] ?? false) && preg_match('/\.jar(\.disabled)?$/i', (string) ($entry['name'] ?? '')));

        $seenFileNames = [];

        foreach ($jarEntries as $entry) {
            $fileName = $entry['name'];
            $seenFileNames[] = $fileName;

            try {
                $this->syncEntry($server, $fileRepository, $directory, $entry);
            } catch (Throwable $exception) {
                // One unreadable/corrupt jar should never abort scanning
                // the rest of the directory.
                Log::warning("[plugin-marketplace] Failed to sync \"$fileName\" while scanning server #{$server->id}", ['exception' => $exception->getMessage()]);
            }
        }

        InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->whereNotIn('file_name', $seenFileNames === [] ? [''] : $seenFileNames)
            ->delete();

        return InstalledPlugin::query()->where('server_id', $server->id)->orderBy('name')->get();
    }

    /** @param array<string, mixed> $entry */
    private function syncEntry(Server $server, DaemonFileRepository $fileRepository, string $directory, array $entry): void
    {
        $fileName = $entry['name'];
        $enabled = !str_ends_with(strtolower($fileName), '.disabled');
        $fallbackName = preg_replace('/\.jar(\.disabled)?$/i', '', $fileName) ?? $fileName;

        $metadata = null;
        $checksum = null;
        $maxBytes = $this->settings->maxDownloadSizeBytes();

        try {
            $bytes = $fileRepository->getContent($directory . '/' . $fileName, $maxBytes);
            $checksum = hash('sha256', $bytes);
            $metadata = $this->metadata->extractFromJarBytes($bytes, $fallbackName);
        } catch (Throwable $exception) {
            // Too large to read back, or Wings refused it for some other
            // reason - fall back to filename-derived metadata so the
            // plugin still shows up in the list rather than disappearing.
            Log::info("[plugin-marketplace] Could not read \"$fileName\" back for metadata extraction on server #{$server->id}", ['exception' => $exception->getMessage()]);
        }

        $existing = InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->where('file_name', $fileName)
            ->first();

        InstalledPlugin::query()->updateOrCreate(
            ['server_id' => $server->id, 'file_name' => $fileName],
            [
                'name' => $metadata['name'] ?? $existing?->name ?? $fallbackName,
                'version' => $metadata['version'] ?? $existing?->version,
                'authors' => $metadata['authors'] ?? $existing?->authors,
                'description' => $metadata['description'] ?? $existing?->description,
                'main_class' => $metadata['main'] ?? $existing?->main_class,
                'api_version' => $metadata['api_version'] ?? $existing?->api_version,
                'depend' => $metadata['depend'] ?? $existing?->depend,
                'softdepend' => $metadata['softdepend'] ?? $existing?->softdepend,
                'size' => (int) ($entry['size'] ?? 0),
                'enabled' => $enabled,
                'checksum' => $checksum ?? $existing?->checksum,
                'installed_at' => $existing?->installed_at ?? now(),
                'last_scanned_at' => now(),
            ],
        );
    }
}
