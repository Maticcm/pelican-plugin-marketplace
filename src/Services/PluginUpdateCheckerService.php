<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\Server;
use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;

/**
 * Compares every marketplace-sourced installed plugin on a server
 * against the latest version its repository reports, and stamps the
 * `latest_version` / `update_available` columns accordingly.
 *
 * Only plugins that were installed (or later matched) through this
 * plugin - i.e. that have a known `repository` + `project_id` - can be
 * checked this way; a jar dropped in via SFTP with no marketplace
 * provenance has nothing to compare against and is left untouched.
 */
class PluginUpdateCheckerService
{
    public function __construct(
        private readonly RepositoryClientManager $clients,
        private readonly VersionComparatorService $comparator,
    ) {}

    /** @return Collection<int, InstalledPlugin> the plugins that have an update available, after refreshing */
    public function check(Server $server): Collection
    {
        $installedPlugins = InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->whereNotNull('repository')
            ->whereNotNull('project_id')
            ->get();

        foreach ($installedPlugins as $installedPlugin) {
            $this->checkOne($installedPlugin);
        }

        return InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->where('update_available', true)
            ->get();
    }

    public function checkOne(InstalledPlugin $installedPlugin): void
    {
        $repository = $installedPlugin->repository instanceof MarketplaceRepository
            ? $installedPlugin->repository
            : MarketplaceRepository::tryFrom((string) $installedPlugin->repository);

        if ($repository === null || $installedPlugin->project_id === null) {
            return;
        }

        $client = $this->clients->for($repository);
        if ($client === null || !$client->isEnabled()) {
            return;
        }

        $latest = $client->latestCompatibleVersion($installedPlugin->project_id);
        if ($latest === null) {
            return;
        }

        $installedPlugin->update([
            'latest_version' => $latest->versionNumber,
            'update_available' => $this->comparator->isNewer($latest->versionNumber, $installedPlugin->version),
        ]);
    }
}
