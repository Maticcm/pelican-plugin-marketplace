<?php

use Illuminate\Support\Facades\Http;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceCacheService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\SpigetClient;

beforeEach(function () {
    $this->client = new SpigetClient(
        app(MarketplaceCacheService::class),
        app(MarketplaceSettingsService::class),
    );
});

it('maps a spiget search result', function () {
    Http::fake([
        'api.spiget.org/*' => Http::response([
            ['id' => 9089, 'name' => 'EssentialsX', 'tag' => 'The modern Essentials suite for Spigot and Paper.'],
        ]),
    ]);

    $result = $this->client->search(new MarketplaceSearchQuery(term: 'essentials'));

    expect($result->items)->toHaveCount(1);
    expect($result->items[0]->name)->toBe('EssentialsX');
    expect($result->items[0]->summary)->toBe('The modern Essentials suite for Spigot and Paper.');
});

it('never reports direct-install support for spigot plugins', function () {
    Http::fake(['api.spiget.org/*' => Http::response(['id' => 9089, 'name' => 'EssentialsX'])]);

    $plugin = $this->client->find('9089');

    expect($plugin->repository->supportsDirectInstall())->toBeFalse();
});

it('base64-decodes the resource description on the detail view only', function () {
    $encoded = base64_encode('<b>Hello</b> world');

    Http::fake(['api.spiget.org/*' => Http::response(['id' => 9089, 'name' => 'EssentialsX', 'description' => $encoded])]);

    $plugin = $this->client->find('9089');

    expect($plugin->description)->toBe('<b>Hello</b> world');
});

it('never exposes a version list, since spiget is discovery-only', function () {
    expect($this->client->versions('9089'))->toBe([]);
    expect($this->client->latestCompatibleVersion('9089'))->toBeNull();
});
