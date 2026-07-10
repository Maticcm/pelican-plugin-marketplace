<?php

use Illuminate\Support\Facades\Http;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceCacheService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\HangarClient;

/*
|--------------------------------------------------------------------------
| Fixtures
|--------------------------------------------------------------------------
|
| Trimmed-down but structurally faithful copies of real responses
| captured directly from the live Hangar API
| (https://hangar.papermc.io/api/v1) while building this client -
| see docs/DEVELOPER.md for how these were verified.
|
*/

function hangarProjectFixture(): array
{
    return [
        'createdAt' => '2022-12-23T20:47:00.582094Z',
        'id' => 423,
        'name' => 'ErraticExplosions',
        'namespace' => ['owner' => 'srnyx', 'slug' => 'ErraticExplosions'],
        'stats' => ['views' => 3216, 'downloads' => 86, 'recentViews' => 129, 'recentDownloads' => 3, 'stars' => 0, 'watchers' => 86],
        'category' => 'gameplay',
        'description' => 'Gives explosions random properties.',
        'lastUpdated' => '2025-06-21T20:04:28.281953Z',
        'visibility' => 'public',
        'settings' => [
            'links' => [
                ['id' => 0, 'type' => 'top', 'title' => null, 'links' => [
                    ['id' => 0, 'name' => 'Wiki', 'url' => 'https://srnyx.com/git/erratic-explosions/wiki'],
                    ['id' => 1, 'name' => 'Issues', 'url' => 'https://srnyx.com/git/erratic-explosions/issues'],
                ]],
            ],
            'tags' => [],
            'license' => ['name' => 'MIT', 'url' => null, 'type' => 'MIT'],
            'keywords' => ['creeper', 'tnt'],
        ],
        'supportedPlatforms' => ['PAPER' => ['1.20.1', '1.21']],
        'mainPageContent' => '# Erratic Explosions',
        'memberNames' => ['srnyx'],
        'avatarUrl' => 'https://hangarcdn.papermc.io/avatars/project/423.webp?v=1',
    ];
}

function hangarVersionFixture(): array
{
    return [
        'createdAt' => '2025-06-21T20:04:28.281953Z',
        'id' => 16684,
        'projectId' => 423,
        'name' => '2.1.0',
        'visibility' => 'public',
        'description' => 'https://github.com/srnyx/erratic-explosions/releases/tag/2.1.0',
        'stats' => ['totalDownloads' => 34, 'platformDownloads' => ['PAPER' => 34]],
        'author' => 'srnyx',
        'channel' => ['createdAt' => '2023-04-22T20:45:22.159667Z', 'name' => 'Release', 'description' => null, 'color' => '#14b8a6', 'flags' => []],
        'downloads' => [
            'PAPER' => [
                'fileInfo' => ['name' => 'ErraticExplosions-2.1.0.jar', 'sizeBytes' => 308963, 'sha256Hash' => 'abc'],
                'externalUrl' => null,
                'downloadUrl' => 'https://hangarcdn.papermc.io/plugins/srnyx/ErraticExplosions/versions/2.1.0/PAPER/ErraticExplosions-2.1.0.jar',
            ],
        ],
        'pluginDependencies' => [
            'PAPER' => [
                ['name' => 'Vault', 'projectId' => null, 'required' => true, 'externalUrl' => 'https://www.spigotmc.org/resources/vault.34315/', 'platform' => 'PAPER'],
            ],
        ],
        'platformDependencies' => ['PAPER' => ['1.20.1', '1.21']],
        'platformDependenciesFormatted' => ['PAPER' => ['1.20.1-1.21']],
        'memberNames' => ['srnyx'],
    ];
}

beforeEach(function () {
    $this->client = new HangarClient(
        app(MarketplaceCacheService::class),
        app(MarketplaceSettingsService::class),
    );
});

it('maps a hangar project into the unified plugin shape', function () {
    Http::fake([
        'hangar.papermc.io/*' => Http::response(hangarProjectFixture()),
    ]);

    $plugin = $this->client->find('srnyx/ErraticExplosions');

    expect($plugin)->not->toBeNull();
    expect($plugin->name)->toBe('ErraticExplosions');
    expect($plugin->projectId)->toBe('srnyx/ErraticExplosions');
    expect($plugin->author)->toBe('srnyx');
    expect($plugin->downloads)->toBe(86);
    expect($plugin->issuesUrl)->toBe('https://srnyx.com/git/erratic-explosions/issues');
    expect($plugin->license)->toBe('MIT');
    expect($plugin->minecraftVersions)->toBe(['1.20.1', '1.21']);
});

it('maps hangar versions, resolving the download url and dependencies', function () {
    Http::fake([
        'hangar.papermc.io/*' => Http::response(['pagination' => ['count' => 1, 'limit' => 25, 'offset' => 0], 'result' => [hangarVersionFixture()]]),
    ]);

    $versions = $this->client->versions('srnyx/ErraticExplosions');

    expect($versions)->toHaveCount(1);
    expect($versions[0]->versionNumber)->toBe('2.1.0');
    expect($versions[0]->downloadUrl)->toBe('https://hangarcdn.papermc.io/plugins/srnyx/ErraticExplosions/versions/2.1.0/PAPER/ErraticExplosions-2.1.0.jar');
    expect($versions[0]->fileName)->toBe('ErraticExplosions-2.1.0.jar');
    expect($versions[0]->dependencies)->toHaveCount(1);
    expect($versions[0]->dependencies[0]->name)->toBe('Vault');
    // No Hangar projectId on this dependency (it's a Spigot-only plugin), so it's not resolvable from Hangar's own data.
    expect($versions[0]->dependencies[0]->resolvable)->toBeFalse();
});

it('skips a version with no downloadable file for any supported platform', function () {
    $version = hangarVersionFixture();
    $version['downloads'] = ['PAPER' => ['fileInfo' => null, 'externalUrl' => null, 'downloadUrl' => null]];

    Http::fake([
        'hangar.papermc.io/*' => Http::response(['pagination' => ['count' => 1, 'limit' => 25, 'offset' => 0], 'result' => [$version]]),
    ]);

    $versions = $this->client->versions('srnyx/ErraticExplosions');

    expect($versions)->toBeEmpty();
});

it('returns null and does not throw when hangar is unreachable', function () {
    Http::fake([
        'hangar.papermc.io/*' => Http::response(null, 500),
    ]);

    expect($this->client->find('srnyx/ErraticExplosions'))->toBeNull();
});

it('returns no results when hangar is disabled in settings', function () {
    app(MarketplaceSettingsService::class)->update(['hangar_enabled' => false]);

    Http::fake(['hangar.papermc.io/*' => Http::response(hangarProjectFixture())]);

    expect($this->client->find('srnyx/ErraticExplosions'))->toBeNull();
    Http::assertNothingSent();
});
