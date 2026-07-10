<?php

namespace PelicanMarketplace\PluginMarketplace\Jobs;

use App\Models\Server;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobStatus;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceNotificationService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\PluginUpdaterService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use RuntimeException;
use Throwable;

/**
 * Updates every plugin on a server that currently has
 * `update_available = true`, one at a time, recording a per-plugin
 * outcome in the tracking job's `meta` column so the UI can show which
 * updates succeeded and which failed rather than a single pass/fail for
 * the whole batch.
 */
class BulkUpdatePluginsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param  int[]|null  $installedPluginIds  null = every outdated plugin on the server */
    public function __construct(
        public User $user,
        public Server $server,
        public ?array $installedPluginIds = null,
        public ?int $jobId = null,
    ) {}

    public function handle(
        MarketplaceSearchService $search,
        RepositoryClientManager $clients,
        PluginUpdaterService $updater,
        MarketplaceNotificationService $notifications,
    ): void {
        $job = $this->jobId ? PluginJob::find($this->jobId) : null;

        $query = InstalledPlugin::query()
            ->where('server_id', $this->server->id)
            ->where('update_available', true);

        if ($this->installedPluginIds !== null) {
            $query->whereIn('id', $this->installedPluginIds);
        }

        $installedPlugins = $query->get();

        $results = [];
        $succeeded = 0;

        foreach ($installedPlugins as $index => $installedPlugin) {
            $job?->markStatus(InstallJobStatus::Installing, 'Updating ' . $installedPlugin->name . ' (' . ($index + 1) . '/' . $installedPlugins->count() . ')...');

            try {
                $repository = $installedPlugin->repository instanceof MarketplaceRepository
                    ? $installedPlugin->repository
                    : MarketplaceRepository::from((string) $installedPlugin->repository);

                $plugin = $search->find($repository, $installedPlugin->project_id);
                $version = $plugin ? $clients->for($repository)?->latestCompatibleVersion($installedPlugin->project_id) : null;

                if ($plugin === null || $version === null) {
                    throw new RuntimeException('Could not find an installable version.');
                }

                $updater->update($this->server, $installedPlugin, $plugin, $version);

                $results[] = ['name' => $installedPlugin->name, 'status' => 'success', 'version' => $version->versionNumber];
                $succeeded++;
            } catch (Throwable $exception) {
                report($exception);
                $results[] = ['name' => $installedPlugin->name, 'status' => 'failed', 'message' => $exception->getMessage()];
            }
        }

        $job?->update(['meta' => ['results' => $results]]);
        $job?->markStatus(
            InstallJobStatus::Completed,
            "Updated $succeeded of {$installedPlugins->count()} plugin(s)."
        );

        if ($succeeded > 0) {
            $notifications->success($this->user, "$succeeded plugin(s) updated", 'Restart the server for the updates to take effect.');
        }

        $failed = $installedPlugins->count() - $succeeded;
        if ($failed > 0) {
            $notifications->warning($this->user, "$failed plugin update(s) failed", 'Check the Plugin Updates page for details.');
        }
    }

    public function uniqueId(): string
    {
        return "plugin-marketplace:bulk-update:{$this->server->id}";
    }
}
