<?php

use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceDependencyData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Services\DependencyResolverService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use PelicanMarketplace\PluginMarketplace\Tests\Fixtures\FakeRepositoryClient;

beforeEach(function () {
    $manager = new RepositoryClientManager();
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Modrinth, [
        makeMarketplacePlugin(['repository' => MarketplaceRepository::Modrinth, 'projectId' => 'placeholderapi', 'name' => 'PlaceholderAPI']),
    ]));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Hangar));
    $manager->register(new FakeRepositoryClient(MarketplaceRepository::Spigot));

    $this->resolver = new DependencyResolverService(new MarketplaceSearchService($manager));
});

it('marks a dependency as satisfied when it is already installed by name', function () {
    $dependencies = [new MarketplaceDependencyData(name: 'Vault', required: true)];
    $installed = new Collection([new InstalledPlugin(['name' => 'Vault', 'file_name' => 'Vault.jar'])]);

    $resolved = $this->resolver->resolve($dependencies, $installed);

    expect($resolved[0]->satisfied)->toBeTrue();
});

it('leaves an already-resolvable dependency alone', function () {
    $dependencies = [new MarketplaceDependencyData(
        name: 'LuckPerms',
        required: true,
        repository: MarketplaceRepository::Modrinth,
        projectId: 'luckperms',
        resolvable: true,
    )];

    $resolved = $this->resolver->resolve($dependencies, new Collection());

    expect($resolved[0]->resolvable)->toBeTrue();
    expect($resolved[0]->projectId)->toBe('luckperms');
    expect($resolved[0]->satisfied)->toBeFalse();
});

it('resolves an unresolved dependency using the known_dependencies config map', function () {
    config()->set('plugin-marketplace.known_dependencies', [
        'PlaceholderAPI' => ['repository' => 'modrinth', 'slug' => 'placeholderapi'],
    ]);

    $dependencies = [new MarketplaceDependencyData(name: 'PlaceholderAPI', required: true)];

    $resolved = $this->resolver->resolve($dependencies, new Collection());

    expect($resolved[0]->resolvable)->toBeTrue();
    expect($resolved[0]->repository)->toBe(MarketplaceRepository::Modrinth);
    expect($resolved[0]->projectId)->toBe('placeholderapi');
});

it('leaves a dependency unresolved when it is unknown and not installed', function () {
    config()->set('plugin-marketplace.known_dependencies', []);

    $dependencies = [new MarketplaceDependencyData(name: 'SomeObscurePlugin', required: true)];

    $resolved = $this->resolver->resolve($dependencies, new Collection());

    expect($resolved[0]->resolvable)->toBeFalse();
    expect($resolved[0]->satisfied)->toBeFalse();
});

it('finds installable marketplace listings for resolved dependencies', function () {
    $dependencies = [new MarketplaceDependencyData(
        name: 'PlaceholderAPI',
        required: true,
        repository: MarketplaceRepository::Modrinth,
        projectId: 'placeholderapi',
        resolvable: true,
    )];

    $installable = $this->resolver->findInstallable($dependencies);

    expect($installable)->toHaveKey('PlaceholderAPI');
    expect($installable['PlaceholderAPI']->name)->toBe('PlaceholderAPI');
});

it('does not try to find an installable listing for a satisfied dependency', function () {
    $dependencies = [(new MarketplaceDependencyData(
        name: 'PlaceholderAPI',
        required: true,
        repository: MarketplaceRepository::Modrinth,
        projectId: 'placeholderapi',
        resolvable: true,
    ))->withSatisfied(true)];

    $installable = $this->resolver->findInstallable($dependencies);

    expect($installable)->toBeEmpty();
});
