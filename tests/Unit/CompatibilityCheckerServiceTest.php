<?php

use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Services\CompatibilityCheckerService;

beforeEach(function () {
    $this->checker = new CompatibilityCheckerService();
});

function makeVersion(array $overrides = []): MarketplaceVersionData
{
    return new MarketplaceVersionData(
        id: $overrides['id'] ?? 'v1',
        name: $overrides['name'] ?? '1.0.0',
        versionNumber: $overrides['versionNumber'] ?? '1.0.0',
        changelog: null,
        downloadUrl: 'https://example.com/plugin.jar',
        fileName: $overrides['fileName'] ?? 'Plugin.jar',
        fileSize: 1024,
        minecraftVersions: $overrides['minecraftVersions'] ?? ['1.21'],
        loaders: $overrides['loaders'] ?? ['paper'],
        dependencies: [],
        publishedAt: null,
    );
}

it('has no warnings for a fully compatible, non-conflicting install', function () {
    $warnings = $this->checker->check(makeVersion(), '1.21', new Collection(), 'Plugin.jar');

    expect($warnings)->toBeEmpty();
});

it('warns when the target minecraft version is not listed as supported', function () {
    $warnings = $this->checker->check(makeVersion(minecraftVersions: ['1.20']), '1.21', new Collection(), 'Plugin.jar');

    expect($warnings)->not->toBeEmpty();
    expect($warnings[0]['level'])->toBe('warning');
});

it('does not warn about minecraft version when none was specified', function () {
    $warnings = $this->checker->check(makeVersion(minecraftVersions: ['1.20']), null, new Collection(), 'Plugin.jar');

    expect(collect($warnings)->pluck('level'))->not->toContain('danger');
});

it('flags a version with no bukkit-family loader support as dangerous', function () {
    $warnings = $this->checker->check(makeVersion(loaders: ['fabric']), null, new Collection(), 'Plugin.jar');

    expect(collect($warnings)->firstWhere('level', 'danger'))->not->toBeNull();
});

it('detects an exact filename collision with an already-installed plugin', function () {
    $installed = new InstalledPlugin(['name' => 'Existing', 'file_name' => 'Plugin.jar', 'version' => '0.9.0']);

    $warnings = $this->checker->check(makeVersion(), null, new Collection([$installed]), 'Plugin.jar');

    $messages = collect($warnings)->pluck('message')->implode(' ');
    expect($messages)->toContain('already installed');
});

it('detects a likely duplicate plugin published under a different filename', function () {
    $installed = new InstalledPlugin(['name' => 'EssentialsX', 'file_name' => 'EssentialsX-2.20.1.jar', 'version' => '2.20.1']);

    $warnings = $this->checker->check(makeVersion(fileName: 'EssentialsX-2.21.0.jar'), null, new Collection([$installed]), 'EssentialsX-2.21.0.jar');

    expect(collect($warnings)->pluck('message')->implode(' '))->toContain('EssentialsX');
});

it('does not flag unrelated installed plugins as conflicts', function () {
    $installed = new InstalledPlugin(['name' => 'LuckPerms', 'file_name' => 'LuckPerms.jar', 'version' => '5.4']);

    $warnings = $this->checker->check(makeVersion(fileName: 'Vault.jar'), null, new Collection([$installed]), 'Vault.jar');

    expect($warnings)->toBeEmpty();
});
