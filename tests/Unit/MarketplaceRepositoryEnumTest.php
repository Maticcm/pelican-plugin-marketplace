<?php

use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

it('only allows direct installation for hangar and modrinth', function () {
    expect(MarketplaceRepository::Hangar->supportsDirectInstall())->toBeTrue();
    expect(MarketplaceRepository::Modrinth->supportsDirectInstall())->toBeTrue();
    expect(MarketplaceRepository::Spigot->supportsDirectInstall())->toBeFalse();
});

it('builds the correct homepage url per repository', function () {
    expect(MarketplaceRepository::Hangar->homepageUrl('EssentialsX/Essentials'))
        ->toBe('https://hangar.papermc.io/EssentialsX/Essentials');

    expect(MarketplaceRepository::Modrinth->homepageUrl('luckperms'))
        ->toBe('https://modrinth.com/plugin/luckperms');

    expect(MarketplaceRepository::Spigot->homepageUrl('9089'))
        ->toBe('https://www.spigotmc.org/resources/9089.html');
});

it('round-trips through its string value', function () {
    expect(MarketplaceRepository::from('hangar'))->toBe(MarketplaceRepository::Hangar);
    expect(MarketplaceRepository::tryFrom('nonexistent'))->toBeNull();
});
