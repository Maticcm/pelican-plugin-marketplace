<?php

use Illuminate\Support\Carbon;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\PluginHealthStatus;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\PluginHealthService;

beforeEach(function () {
    $this->health = new PluginHealthService(app(MarketplaceSettingsService::class));
});

it('reports a recently updated plugin as healthy', function () {
    $plugin = makeMarketplacePlugin(['updatedAt' => Carbon::now()->subDays(10)]);

    expect($this->health->status($plugin))->toBe(PluginHealthStatus::Healthy);
    expect($this->health->warningMessage($plugin))->toBeNull();
});

it('flags a plugin with no updates in over a year as abandoned', function () {
    $plugin = makeMarketplacePlugin(['updatedAt' => Carbon::now()->subDays(400)]);

    expect($this->health->status($plugin))->toBe(PluginHealthStatus::Abandoned);
    expect($this->health->warningMessage($plugin))->not->toBeNull();
});

it('flags a known-deprecated plugin regardless of how recently it was updated', function () {
    config()->set('plugin-marketplace.known_replacements', [
        'modrinth:old-plugin' => ['repository' => 'modrinth', 'slug' => 'new-plugin'],
    ]);

    $plugin = makeMarketplacePlugin([
        'repository' => MarketplaceRepository::Modrinth,
        'projectId' => 'old-plugin',
        'updatedAt' => Carbon::now()->subDay(),
    ]);

    expect($this->health->status($plugin))->toBe(PluginHealthStatus::Deprecated);
});

it('treats every plugin as healthy when health warnings are disabled', function () {
    app(MarketplaceSettingsService::class)->update(['health_warnings_enabled' => false]);

    $plugin = makeMarketplacePlugin(['updatedAt' => Carbon::now()->subDays(1000)]);

    expect($this->health->status($plugin))->toBe(PluginHealthStatus::Healthy);
});
