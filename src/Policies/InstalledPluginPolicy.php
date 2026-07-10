<?php

namespace PelicanMarketplace\PluginMarketplace\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorizes access to the Installed Plugins / Plugin Updates Filament
 * resources using the `plugins.*` subuser permission group this
 * plugin's provider registers via `Subuser::registerCustomPermissions()`.
 * Mirrors the panel's own {@see \App\Policies\FilePolicy} exactly, since
 * Filament resources whose model lives outside the `App\Models`
 * namespace don't get auto-discovered by Laravel's Model-to-Policy
 * naming convention and need an explicit `Gate::policy()` binding
 * (registered in {@see \PelicanMarketplace\PluginMarketplace\Providers\PluginMarketplaceProvider}).
 */
class InstalledPluginPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plugins.view', Filament::getTenant());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('plugins.view', Filament::getTenant());
    }

    public function create(User $user): bool
    {
        return $user->can('plugins.install', Filament::getTenant());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('plugins.update', Filament::getTenant());
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('plugins.delete', Filament::getTenant());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('plugins.delete', Filament::getTenant());
    }
}
