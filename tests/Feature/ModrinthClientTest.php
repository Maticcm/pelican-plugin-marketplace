<?php

use Illuminate\Support\Facades\Http;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceCacheService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\ModrinthClient;

/*
| Fixtures below are trimmed but structurally faithful copies of real
| responses captured directly from https://api.modrinth.com/v2 while
| building this client - see docs/DEVELOPER.md.
*/

function modrinthSearchHitFixture(array $overrides = []): array
{
    return array_merge([
        'project_id' => 'AgDMtDj6',
        'slug' => 'logo-smp-essentials',
        'title' => 'SMP Essentials',
        'author' => 'logogaming',
        'description' => 'A Minecraft plugin.',
        // Loader tags are mixed into `categories` at the search-hit
        // level - this is the real, confirmed Modrinth API quirk this
        // client has to account for.
        'categories' => ['bukkit', 'game-mechanics', 'paper', 'purpur', 'spigot', 'utility'],
        'versions' => ['1.21', '1.21.1'],
        'downloads' => 6674,
        'follows' => 18,
        'icon_url' => 'https://cdn.modrinth.com/data/AgDMtDj6/icon.webp',
        'date_created' => '2025-09-07T23:01:00Z',
        'date_modified' => '2026-04-11T05:02:31Z',
        'latest_version' => 'vAZzQe4V',
        'license' => 'LicenseRef-All-Rights-Reserved',
        'gallery' => ['https://cdn.modrinth.com/data/AgDMtDj6/images/one.webp'],
    ], $overrides);
}

function modrinthFabricHitFixture(): array
{
    return modrinthSearchHitFixture([
        'project_id' => 'u6dRKJwZ',
        'slug' => 'jei',
        'title' => 'Just Enough Items',
        'categories' => ['fabric', 'forge', 'library', 'neoforge'],
    ]);
}

function modrinthVersionFixture(): array
{
    return [
        'id' => 'vAZzQe4V',
        'project_id' => 'AgDMtDj6',
        'name' => 'SMP Essentials 1.0.9',
        'version_number' => '1.0.9',
        'changelog' => 'Minor bug fixes',
        'game_versions' => ['1.21', '1.21.1'],
        'loaders' => ['bukkit', 'paper', 'purpur', 'spigot'],
        'version_type' => 'release',
        'downloads' => 3432,
        'date_published' => '2026-04-11T05:02:31Z',
        'dependencies' => [
            ['project_id' => 'luckperms123', 'version_id' => null, 'file_name' => null, 'dependency_type' => 'required'],
        ],
        'files' => [
            ['hashes' => ['sha1' => 'abc'], 'url' => 'https://cdn.modrinth.com/data/AgDMtDj6/versions/vAZzQe4V/SMPEssentials-1.0.9.jar', 'filename' => 'SMPEssentials-1.0.9.jar', 'primary' => true, 'size' => 36480],
        ],
    ];
}

beforeEach(function () {
    $this->client = new ModrinthClient(
        app(MarketplaceCacheService::class),
        app(MarketplaceSettingsService::class),
    );
});

it('maps a modrinth search hit, separating loader tags from real categories', function () {
    Http::fake([
        'api.modrinth.com/*' => Http::response(['hits' => [modrinthSearchHitFixture()], 'total_hits' => 1]),
    ]);

    $result = $this->client->search(new \PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery());

    expect($result->items)->toHaveCount(1);
    $plugin = $result->items[0];
    expect($plugin->name)->toBe('SMP Essentials');
    expect($plugin->loaders)->toEqualCanonicalizing(['bukkit', 'paper', 'purpur', 'spigot']);
    expect($plugin->categories)->toEqualCanonicalizing(['game_mechanics', 'utility']);
});

it('defensively drops results with no bukkit-family loader even if facets somehow let one through', function () {
    Http::fake([
        'api.modrinth.com/*' => Http::response(['hits' => [modrinthFabricHitFixture()], 'total_hits' => 1]),
    ]);

    $result = $this->client->search(new \PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery());

    expect($result->items)->toBeEmpty();
});

it('maps modrinth versions including their download file and dependencies', function () {
    Http::fake([
        'api.modrinth.com/*/project/*/version' => Http::response([modrinthVersionFixture()]),
        'api.modrinth.com/*/projects*' => Http::response([
            ['id' => 'luckperms123', 'slug' => 'luckperms', 'title' => 'LuckPerms'],
        ]),
    ]);

    $versions = $this->client->versions('AgDMtDj6');

    expect($versions)->toHaveCount(1);
    expect($versions[0]->downloadUrl)->toBe('https://cdn.modrinth.com/data/AgDMtDj6/versions/vAZzQe4V/SMPEssentials-1.0.9.jar');
    expect($versions[0]->fileSize)->toBe(36480);
    expect($versions[0]->dependencies)->toHaveCount(1);
    expect($versions[0]->dependencies[0]->name)->toBe('LuckPerms');
    expect($versions[0]->dependencies[0]->required)->toBeTrue();
});
