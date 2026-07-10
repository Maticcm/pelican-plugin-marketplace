<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\Server;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use Throwable;

/**
 * Uninstalls a plugin: deletes its jar from the server's `/plugins`
 * directory through Wings and removes the local tracking row.
 */
class PluginRemovalService
{
    public function __construct(private readonly DaemonFileRepositoryFactory $fileRepositories) {}

    /** @throws MarketplaceException */
    public function uninstall(Server $server, InstalledPlugin $installedPlugin): void
    {
        $directory = rtrim((string) config('plugin-marketplace.downloads.directory', '/plugins'), '/');

        try {
            $fileRepository = $this->fileRepositories->forServer($server);
            $fileRepository->deleteFiles($directory, [$installedPlugin->file_name]);
        } catch (Throwable $exception) {
            throw new MarketplaceException("Could not delete \"{$installedPlugin->file_name}\" from the server: {$exception->getMessage()}", previous: $exception);
        }

        $installedPlugin->delete();
    }

    /**
     * Toggles a plugin between enabled and disabled by renaming its
     * jar with (or without) a `.disabled` suffix. Bukkit/Spigot/Paper
     * have no built-in "disable without removing" mechanism, so this is
     * this plugin's own convention - it always requires a server
     * restart to take effect, same as install/update/uninstall.
     *
     * @throws MarketplaceException
     */
    public function setEnabled(Server $server, InstalledPlugin $installedPlugin, bool $enabled): InstalledPlugin
    {
        if ($installedPlugin->enabled === $enabled) {
            return $installedPlugin;
        }

        $directory = rtrim((string) config('plugin-marketplace.downloads.directory', '/plugins'), '/');
        $currentName = $installedPlugin->file_name;
        $newName = $enabled
            ? preg_replace('/\.disabled$/i', '', $currentName)
            : $currentName . '.disabled';

        try {
            $fileRepository = $this->fileRepositories->forServer($server);
            $fileRepository->renameFiles($directory, [
                ['from' => $currentName, 'to' => $newName],
            ]);
        } catch (Throwable $exception) {
            throw new MarketplaceException('Could not rename "' . $currentName . '": ' . $exception->getMessage(), previous: $exception);
        }

        $installedPlugin->update(['file_name' => $newName, 'enabled' => $enabled]);

        return $installedPlugin->refresh();
    }
}
