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
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceNotificationService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\PluginUpdaterService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use Throwable;

class UpdatePluginJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public User $user,
        public Server $server,
        public int $installedPluginId,
        public ?string $versionId = null,
        public ?int $jobId = null,
    ) {}

    public function handle(
        MarketplaceSearchService $search,
        RepositoryClientManager $clients,
        PluginUpdaterService $updater,
        MarketplaceNotificationService $notifications,
    ): void {
        $job = $this->jobId ? PluginJob::find($this->jobId) : null;

        try {
            $installedPlugin = InstalledPlugin::findOrFail($this->installedPluginId);
            throw_unless($installedPlugin->isFromMarketplace(), new MarketplaceException("{$installedPlugin->name} was not installed through the marketplace, so it cannot be auto-updated."));

            $repository = $installedPlugin->repository instanceof MarketplaceRepository
                ? $installedPlugin->repository
                : MarketplaceRepository::from((string) $installedPlugin->repository);

            $plugin = $search->find($repository, $installedPlugin->project_id);
            throw_unless($plugin, new MarketplaceException('This plugin could not be found. It may have been removed from the repository.'));

            $client = $clients->for($repository);
            $version = $this->versionId
                ? collect($client?->versions($installedPlugin->project_id) ?? [])->firstWhere('id', $this->versionId)
                : $client?->latestCompatibleVersion($installedPlugin->project_id);

            throw_unless($version, new MarketplaceException('No installable version was found.'));

            $job?->update(['plugin_name' => $plugin->name]);

            $updater->update($this->server, $installedPlugin, $plugin, $version, $job);

            $job?->markStatus(InstallJobStatus::Completed, "Updated {$installedPlugin->name} to {$version->versionNumber}.");
            $notifications->updateCompleted($this->user, $installedPlugin->name, $version->versionNumber);
        } catch (Throwable $exception) {
            report($exception);

            if ($job !== null && $job->status !== InstallJobStatus::RolledBack) {
                $job->markStatus(InstallJobStatus::Failed, $exception->getMessage());
            }

            $notifications->error($this->user, 'Plugin update failed', $exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return "plugin-marketplace:update:{$this->server->id}:{$this->installedPluginId}";
    }
}
