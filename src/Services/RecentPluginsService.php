<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Models\RecentlyViewed;

class RecentPluginsService
{
    private const MAX_ENTRIES_PER_USER = 25;

    public function record(User $user, MarketplacePluginData $plugin): void
    {
        RecentlyViewed::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'repository' => $plugin->repository->value,
                'project_id' => $plugin->projectId,
            ],
            [
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'icon_url' => $plugin->iconUrl,
                'viewed_at' => now(),
            ],
        );

        $this->prune($user);
    }

    /** @return Collection<int, RecentlyViewed> */
    public function list(User $user, int $limit = 12): Collection
    {
        return RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit($limit)
            ->get();
    }

    private function prune(User $user): void
    {
        $idsToKeep = RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit(self::MAX_ENTRIES_PER_USER)
            ->pluck('id');

        RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
