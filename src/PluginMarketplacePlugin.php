<?php

namespace PelicanMarketplace\PluginMarketplace;

use Filament\Contracts\Plugin;
use Filament\Panel;
use PelicanMarketplace\PluginMarketplace\Filament\Admin\Pages\MarketplaceSettings;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Pages\Marketplace;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Pages\PluginDetails;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins\InstalledPluginResource;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\PluginUpdates\PluginUpdateResource;

/**
 * The plugin's Filament entry point, discovered and instantiated by
 * `App\Services\Helpers\PluginService::loadPanelPlugins()` exactly like
 * any other Pelican plugin (see plugin.json `class`/`namespace`).
 *
 * Registers into two panels, since the marketplace itself is
 * inherently per-server (you install plugins onto a specific game
 * server) while its global configuration (which repositories are
 * enabled, cache duration, download limits, ...) is an
 * instance-wide/operator concern - exactly like every other panel-wide
 * setting in Pelican, which all live in the admin panel. See
 * docs/ARCHITECTURE.md for the full rationale.
 */
class PluginMarketplacePlugin implements Plugin
{
    public function getId(): string
    {
        return 'plugin-marketplace';
    }

    public function register(Panel $panel): void
    {
        match ($panel->getId()) {
            'server' => $panel
                ->resources([
                    InstalledPluginResource::class,
                    PluginUpdateResource::class,
                ])
                ->pages([
                    Marketplace::class,
                    PluginDetails::class,
                ]),
            'admin' => $panel->pages([
                MarketplaceSettings::class,
            ]),
            default => null,
        };
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
