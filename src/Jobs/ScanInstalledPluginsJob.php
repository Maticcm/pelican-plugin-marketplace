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
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceNotificationService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\PluginScannerService;
use PelicanMarketplace\PluginMarketplace\Services\PluginUpdateCheckerService;
use Throwable;

/**
 * Rescans a server's `/plugins` directory and, if automatic update
 * checks are enabled, immediately checks the freshly-synced plugins
 * for available updates too - this is the job the "Scan now" button on
 * the Installed Plugins page dispatches, and is also suitable for a
 * scheduled/cron-triggered periodic scan.
 */
class ScanInstalledPluginsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        public ?User $user,
        public Server $server,
        public ?int $jobId = null,
        public bool $notifyOnUpdatesFound = false,
    ) {}

    public function handle(
        PluginScannerService $scanner,
        PluginUpdateCheckerService $updateChecker,
        MarketplaceSettingsService $settings,
        MarketplaceNotificationService $notifications,
    ): void {
        $job = $this->jobId ? PluginJob::find($this->jobId) : null;
        $job?->markStatus(InstallJobStatus::Installing, 'Scanning plugins directory...');

        try {
            $installedPlugins = $scanner->scan($this->server);

            $updatesAvailable = collect();
            if ($settings->automaticUpdateChecksEnabled()) {
                $updatesAvailable = $updateChecker->check($this->server);
            }

            $job?->markStatus(InstallJobStatus::Completed, "Found {$installedPlugins->count()} plugin(s), {$updatesAvailable->count()} update(s) available.");

            if ($this->notifyOnUpdatesFound && $this->user !== null && $updatesAvailable->isNotEmpty()) {
                $notifications->updatesAvailable($this->user, $updatesAvailable->count(), $this->server->name);
            }
        } catch (Throwable $exception) {
            report($exception);
            $job?->markStatus(InstallJobStatus::Failed, $exception->getMessage());

            if ($this->user !== null) {
                $notifications->error($this->user, 'Plugin scan failed', $exception->getMessage());
            }
        }
    }

    public function uniqueId(): string
    {
        return "plugin-marketplace:scan:{$this->server->id}";
    }
}
