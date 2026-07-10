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
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceNotificationService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\PluginInstallerService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use Throwable;

/**
 * Installs a plugin from a marketplace repository onto a server. Runs
 * on the queue so a slow upstream download never blocks the HTTP
 * request that triggered it - progress is reported through the
 * {@see PluginJob} row (`$jobId`), which the Filament UI polls.
 */
class InstallPluginJob implements ShouldBeUnique, ShouldQueue
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
        public string $repository,
        public string $projectId,
        public string $versionId,
        public bool $overwrite = false,
        public ?int $jobId = null,
    ) {}

    public function handle(
        MarketplaceSearchService $search,
        RepositoryClientManager $clients,
        PluginInstallerService $installer,
        MarketplaceNotificationService $notifications,
    ): void {
        $job = $this->jobId ? PluginJob::find($this->jobId) : null;

        try {
            $repository = MarketplaceRepository::from($this->repository);

            $plugin = $search->find($repository, $this->projectId);
            throw_unless($plugin, new MarketplaceException('This plugin could not be found. It may have been removed from the repository.'));

            $client = $clients->for($repository);
            $version = collect($client?->versions($this->projectId) ?? [])->firstWhere('id', $this->versionId);
            throw_unless($version, new MarketplaceException('The requested version could not be found.'));

            $job?->update(['plugin_name' => $plugin->name]);

            $installedPlugin = $installer->install($this->server, $plugin, $version, $this->overwrite, $job);

            $job?->markStatus(InstallJobStatus::Completed, "Installed {$installedPlugin->name} {$installedPlugin->version}.");
            $notifications->installCompleted($this->user, $installedPlugin->name);
        } catch (Throwable $exception) {
            report($exception);
            $job?->markStatus(InstallJobStatus::Failed, $exception->getMessage());
            $notifications->error($this->user, 'Plugin install failed', $exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return "plugin-marketplace:install:{$this->server->id}:{$this->repository}:{$this->projectId}";
    }
}
