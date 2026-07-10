<?php

use Illuminate\Support\Facades\Route;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\FavoriteController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\InstallController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\InstalledPluginController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\JobController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\PluginDetailController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\RecentController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\ScanController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\SearchController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\SettingsController;
use PelicanMarketplace\PluginMarketplace\Http\Controllers\UpdateController;

/*
|--------------------------------------------------------------------------
| Plugin Marketplace API routes
|--------------------------------------------------------------------------
|
| Registered by PluginMarketplaceProvider::registerRoutes() under the
| `web` + `auth.session` middleware (session-authenticated, same as the
| rest of the panel UI - there is no separate API-key-based surface for
| this plugin). Every route below re-checks the `plugins.*` subuser
| permission (or the admin `settings plugins` permission for the
| settings endpoints) inside its controller/FormRequest, in addition to
| whatever Filament UI already gated the user through, since this is a
| real HTTP surface that can be called directly.
|
*/

Route::get('search', SearchController::class)->name('search');
Route::get('plugins/{repository}/{projectId}', PluginDetailController::class)
    ->where('projectId', '.*')
    ->name('plugins.show');

Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
Route::post('favorites', [FavoriteController::class, 'store'])->name('favorites.store');
Route::delete('favorites/{repository}/{projectId}', [FavoriteController::class, 'destroy'])
    ->where('projectId', '.*')
    ->name('favorites.destroy');

Route::get('recent', RecentController::class)->name('recent');

Route::get('jobs/{job}', JobController::class)->name('jobs.show');

Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

Route::prefix('servers/{server:uuid}/plugins')->name('servers.plugins.')->group(function () {
    Route::get('/', [InstalledPluginController::class, 'index'])->name('index');
    Route::post('install', InstallController::class)->name('install');
    Route::post('scan', ScanController::class)->name('scan');
    Route::get('updates', [UpdateController::class, 'index'])->name('updates.index');
    Route::post('updates/bulk', [UpdateController::class, 'bulk'])->name('updates.bulk');
    Route::post('{installedPlugin}/update', [UpdateController::class, 'update'])->name('update');
    Route::post('{installedPlugin}/toggle', [InstalledPluginController::class, 'toggle'])->name('toggle');
    Route::delete('{installedPlugin}', [InstalledPluginController::class, 'destroy'])->name('destroy');
});
