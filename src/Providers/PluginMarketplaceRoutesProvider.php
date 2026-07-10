<?php

namespace PelicanMarketplace\PluginMarketplace\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Registers this plugin's own small HTTP API (see routes/api.php and
 * docs/API.md).
 *
 * Per Pelican's plugin documentation, plugin routes must be registered
 * through a dedicated provider extending the framework's own
 * `RouteServiceProvider` and wrapped in `$this->routes(...)`, rather
 * than calling `Route::group()` directly from an arbitrary
 * `ServiceProvider::boot()` - `RouteServiceProvider::routes()` is what
 * correctly integrates with Laravel's route caching lifecycle.
 * Auto-discovered from `src/Providers/*.php` exactly like
 * {@see PluginMarketplaceProvider}, since
 * `Illuminate\Foundation\Support\Providers\RouteServiceProvider` is
 * itself a subclass of `Illuminate\Support\ServiceProvider`.
 */
class PluginMarketplaceRoutesProvider extends RouteServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware(['web', 'auth.session'])
                ->prefix('plugin-marketplace/api')
                ->name('plugin-marketplace.api.')
                ->group(plugin_path('plugin-marketplace', 'routes/api.php'));
        });
    }
}
