<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Support\Facades\Cache;
use PelicanMarketplace\PluginMarketplace\Models\MarketplaceSetting;

/**
 * Single point of access for the admin-editable marketplace settings row.
 * Wrapping it here means the rest of the plugin never touches the model
 * or its caching directly, per the single-responsibility principle.
 */
class MarketplaceSettingsService
{
    private const CACHE_KEY = 'plugin-marketplace.settings';

    public function current(): MarketplaceSetting
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            /** @var MarketplaceSetting|null $setting */
            $setting = MarketplaceSetting::query()->first();

            return $setting ?? MarketplaceSetting::create();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): MarketplaceSetting
    {
        $setting = $this->current();
        $setting->fill($data);
        $setting->save();

        $this->forget();

        return $setting;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isRepositoryEnabled(string $repository): bool
    {
        return match ($repository) {
            'hangar' => $this->current()->hangar_enabled,
            'modrinth' => $this->current()->modrinth_enabled,
            'spigot' => $this->current()->spigot_enabled,
            default => false,
        };
    }

    public function cacheDurationMinutes(): int
    {
        return max(1, $this->current()->cache_duration);
    }

    public function maxDownloadSizeBytes(): int
    {
        return max(1, $this->current()->max_download_size) * 1024 * 1024;
    }

    public function downloadTimeoutSeconds(): int
    {
        return max(5, $this->current()->download_timeout);
    }

    public function dependencyInstallationEnabled(): bool
    {
        return $this->current()->dependency_installation_enabled;
    }

    public function healthWarningsEnabled(): bool
    {
        return $this->current()->health_warnings_enabled;
    }

    public function backupsEnabled(): bool
    {
        return $this->current()->backups_enabled;
    }

    public function updateNotificationsEnabled(): bool
    {
        return $this->current()->update_notifications_enabled;
    }

    public function automaticUpdateChecksEnabled(): bool
    {
        return $this->current()->automatic_update_checks;
    }

    public function preferredRepository(): string
    {
        return $this->current()->preferred_repository;
    }
}
