<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;

/**
 * Single place responsible for user-facing notifications so every job
 * and controller reports success/failure the same way, using Filament's
 * own database notifications (bell icon), consistent with how the
 * panel's own plugin-extension install/update/uninstall jobs behave.
 */
class MarketplaceNotificationService
{
    public function __construct(private readonly MarketplaceSettingsService $settings) {}

    public function success(User $user, string $title, ?string $body = null): void
    {
        Notification::make()->success()->title($title)->body($body)->sendToDatabase($user);
    }

    public function error(User $user, string $title, ?string $body = null): void
    {
        Notification::make()->danger()->title($title)->body($body)->sendToDatabase($user);
    }

    public function warning(User $user, string $title, ?string $body = null): void
    {
        Notification::make()->warning()->title($title)->body($body)->sendToDatabase($user);
    }

    public function installCompleted(User $user, string $pluginName): void
    {
        $this->success(
            $user,
            "$pluginName installed",
            'Restart the server for the new plugin to take effect.'
        );
    }

    public function updateCompleted(User $user, string $pluginName, string $version): void
    {
        $this->success(
            $user,
            "$pluginName updated to $version",
            'Restart the server for the update to take effect.'
        );
    }

    public function uninstallCompleted(User $user, string $pluginName): void
    {
        $this->success(
            $user,
            "$pluginName uninstalled",
            'Restart the server to fully unload it.'
        );
    }

    public function jobFailed(User $user, PluginJob $job): void
    {
        $this->error($user, ucfirst($job->type->value) . ' failed' . ($job->plugin_name ? " for {$job->plugin_name}" : ''), $job->message);
    }

    public function updatesAvailable(User $user, int $count, string $serverName): void
    {
        if (!$this->settings->updateNotificationsEnabled() || $count === 0) {
            return;
        }

        $this->warning(
            $user,
            $count === 1 ? '1 plugin update available' : "$count plugin updates available",
            "$serverName has plugin updates ready to install."
        );
    }
}
