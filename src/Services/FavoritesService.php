<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Models\Favorite;

class FavoritesService
{
    public function isFavorited(User $user, MarketplaceRepository $repository, string $projectId): bool
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->where('repository', $repository->value)
            ->where('project_id', $projectId)
            ->exists();
    }

    /**
     * @return bool the new favorited state (true = now favorited)
     */
    public function toggle(User $user, MarketplacePluginData $plugin): bool
    {
        $favorite = Favorite::query()
            ->where('user_id', $user->id)
            ->where('repository', $plugin->repository->value)
            ->where('project_id', $plugin->projectId)
            ->first();

        if ($favorite !== null) {
            $favorite->delete();

            return false;
        }

        Favorite::create(Favorite::fromPluginData($user->id, $plugin));

        return true;
    }

    public function remove(User $user, MarketplaceRepository $repository, string $projectId): void
    {
        Favorite::query()
            ->where('user_id', $user->id)
            ->where('repository', $repository->value)
            ->where('project_id', $projectId)
            ->delete();
    }

    /** @return Collection<int, Favorite> */
    public function list(User $user): Collection
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function count(User $user): int
    {
        return Favorite::query()->where('user_id', $user->id)->count();
    }
}
