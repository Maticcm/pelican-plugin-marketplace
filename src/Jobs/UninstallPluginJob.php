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
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceNotificationService;
use PelicanMarketplace\PluginMarketplace\Services\PluginRemovalService;
use Throwable;

class UninstallPluginJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public User $user,
        public Server $server,
        public int $installedPluginId,
        public ?int $jobId = null,
    ) {}

    public function handle(PluginRemovalService $removal, MarketplaceNotificationService $notifications): void
    {
        $job = $this->jobId ? PluginJob::find($this->jobId) : null;
        $job?->markStatus(InstallJobStatus::Installing, 'Removing plugin...');

        try {
            $installedPlugin = InstalledPlugin::findOrFail($this->installedPluginId);
            $name = $installedPlugin->name;

            $removal->uninstall($this->server, $installedPlugin);

            $job?->markStatus(InstallJobStatus::Completed, "Uninstalled $name.");
            $notifications->uninstallCompleted($this->user, $name);
        } catch (Throwable $exception) {
            report($exception);
            $job?->markStatus(InstallJobStatus::Failed, $exception->getMessage());
            $notifications->error($this->user, 'Plugin uninstall failed', $exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return "plugin-marketplace:uninstall:{$this->server->id}:{$this->installedPluginId}";
    }
}
