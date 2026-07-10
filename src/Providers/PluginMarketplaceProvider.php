<?php

namespace PelicanMarketplace\PluginMarketplace\Providers;

use App\Enums\TablerIcon;
use App\Models\Role;
use App\Models\Server;
use App\Models\Subuser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Jobs\ScanInstalledPluginsJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Policies\InstalledPluginPolicy;
use PelicanMarketplace\PluginMarketplace\Services\Daemon\PelicanDaemonFileRepositoryFactory;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\HangarClient;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\ModrinthClient;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\SpigetClient;

class PluginMarketplaceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RepositoryClientManager::class, function ($app) {
            $manager = new RepositoryClientManager();
            $manager->register($app->make(HangarClient::class));
            $manager->register($app->make(ModrinthClient::class));
            $manager->register($app->make(SpigetClient::class));

            return $manager;
        });

        $this->app->bind(DaemonFileRepositoryFactory::class, PelicanDaemonFileRepositoryFactory::class);

        // Per Pelican's plugin documentation, Role::registerCustomPermissions()
        // / Subuser::registerCustomPermissions() are called from register(),
        // not boot().
        $this->registerPermissions();
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerSchedule();
    }

    private function registerPermissions(): void
    {
        // Admin/staff permissions, shown in Admin > Roles as their own
        // "Plugins" section (Role::getPermissionList() picks these up
        // automatically - no further wiring needed).
        Role::registerCustomPermissions([
            'plugins' => ['view', 'install', 'update', 'delete', 'settings'],
        ]);
        Role::registerCustomModelIcon('plugins', TablerIcon::Packages);

        // Per-server subuser permissions, shown in a server's Subusers
        // permission picker as their own "plugins" group.
        Subuser::registerCustomPermissions(
            name: 'plugins',
            permissions: ['view', 'install', 'update', 'delete'],
            translationPrefix: 'plugin-marketplace::permissions',
            icon: TablerIcon::Packages,
        );
    }

    private function registerPolicies(): void
    {
        Gate::policy(InstalledPlugin::class, InstalledPluginPolicy::class);
    }

    /**
     * Periodically checks marketplace-sourced installed plugins for
     * updates across every server, independent of anyone visiting the
     * Plugin Updates page - this is what makes "Automatic update
     * checks" in settings actually mean something rather than only
     * ever running when a user happens to open the panel.
     */
    private function registerSchedule(): void
    {
        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->call(function () {
                if (!app(MarketplaceSettingsService::class)->automaticUpdateChecksEnabled()) {
                    return;
                }

                $serverIds = InstalledPlugin::query()->distinct()->pluck('server_id');

                Server::query()->whereIn('id', $serverIds)->each(function (Server $server) {
                    ScanInstalledPluginsJob::dispatch(null, $server, notifyOnUpdatesFound: false);
                });
            })
                ->name('plugin-marketplace-update-check')
                ->hourly()
                ->withoutOverlapping();
        });
    }
}
