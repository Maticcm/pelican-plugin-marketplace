<?php

use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use PelicanMarketplace\PluginMarketplace\Tests\Fixtures\FakeRepositoryClient;

it('merges results from every enabled repository', function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Hangar, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Hangar, 'projectId' => 'a', 'name' => 'Alpha', 'downloads' => 500]),
    ]));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'b', 'name' => 'Beta', 'downloads' => 100]),
    ]));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Spigot, []));

    $service = new MarketplaceSearchService($manager);
    $result = $service->search(new MarketplaceSearchQuery(sort: MarketplaceSort::Downloads));

    expect($result->items)->toHaveCount(2);
    // Sorted by downloads descending.
    expect($result->items[0]->name)->toBe('Alpha');
    expect($result->items[1]->name)->toBe('Beta');
});

it('excludes disabled repositories from search results', function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Hangar, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Hangar, 'projectId' => 'a', 'name' => 'Alpha']),
    ], enabled: false));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'b', 'name' => 'Beta']),
    ]));

    $service = new MarketplaceSearchService($manager);
    $result = $service->search(new MarketplaceSearchQuery());

    expect($result->items)->toHaveCount(1);
    expect($result->items[0]->name)->toBe('Beta');
});

it('restricts results to explicitly requested repositories', function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Hangar, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Hangar, 'projectId' => 'a', 'name' => 'Alpha']),
    ]));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'b', 'name' => 'Beta']),
    ]));

    $service = new MarketplaceSearchService($manager);
    $result = $service->search(new MarketplaceSearchQuery(repositories: [MarketplaceRepository::Modrinth]));

    expect($result->items)->toHaveCount(1);
    expect($result->items[0]->name)->toBe('Beta');
});

it('sorts results alphabetically by name', function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'z', 'name' => 'Zeta']),
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'a', 'name' => 'Alpha']),
    ]));

    $service = new MarketplaceSearchService($manager);
    $result = $service->search(new MarketplaceSearchQuery(sort: MarketplaceSort::Name));

    expect($result->items[0]->name)->toBe('Alpha');
    expect($result->items[1]->name)->toBe('Zeta');
});

it('finds a single plugin by repository and project id', function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'luckperms', 'name' => 'LuckPerms']),
    ]));

    $service = new MarketplaceSearchService($manager);

    expect($service->find(MarketplaceRepository::Modrinth, 'luckperms')?->name)->toBe('LuckPerms');
    expect($service->find(MarketplaceRepository::Modrinth, 'does-not-exist'))->toBeNull();
});
