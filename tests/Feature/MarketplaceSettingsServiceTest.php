<?php

use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;

beforeEach(function () {
    $this->settings = app(MarketplaceSettingsService::class);
});

it('seeds sane defaults on first access with no manual row', function () {
    $current = $this->settings->current();

    expect($current->hangar_enabled)->toBeTrue();
    expect($current->modrinth_enabled)->toBeTrue();
    expect($current->spigot_enabled)->toBeTrue();
    expect($current->cache_duration)->toBe(30);
});

it('persists updates and invalidates its cache', function () {
    $this->settings->update(['hangar_enabled' => false, 'cache_duration' => 90]);

    // A fresh service instance must see the change - proves the
    // update path actually invalidates the cached row rather than
    // only updating the in-memory copy on this instance.
    $fresh = app(MarketplaceSettingsService::class);

    expect($fresh->isRepositoryEnabled('hangar'))->toBeFalse();
    expect($fresh->cacheDurationMinutes())->toBe(90);
});

it('converts the configured download size from megabytes to bytes', function () {
    $this->settings->update(['max_download_size' => 5]);

    expect($this->settings->maxDownloadSizeBytes())->toBe(5 * 1024 * 1024);
});

it('never returns a cache duration or timeout below its safety floor', function () {
    $this->settings->update(['cache_duration' => 0, 'download_timeout' => 0]);

    expect($this->settings->cacheDurationMinutes())->toBeGreaterThanOrEqual(1);
    expect($this->settings->downloadTimeoutSeconds())->toBeGreaterThanOrEqual(5);
});
