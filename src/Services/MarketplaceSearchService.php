<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;

/**
 * Fans a single search query out to every enabled, requested repository
 * client and merges the results into one unified, re-sorted page.
 *
 * Cross-repository pagination is necessarily approximate: each
 * repository is asked for its own page N, the results are merged and
 * re-sorted client-side, and the merged page is truncated back down to
 * the requested page size. This is the standard approach for federated
 * search over APIs that don't share a single index, and is more than
 * accurate enough for a "browse plugins" UI - it is not used anywhere
 * that requires exact totals.
 */
class MarketplaceSearchService
{
    public function __construct(private readonly RepositoryClientManager $clients) {}

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData
    {
        // array_intersect() casts every element to a string to compare
        // them, and MarketplaceRepository (a pure backed enum) has no
        // __toString(), so intersecting two arrays of enum instances
        // directly throws. Compare by ->value instead.
        $enabledRepositories = array_map(fn ($client) => $client->repository(), $this->clients->enabled());
        $enabledValues = array_map(fn (MarketplaceRepository $r) => $r->value, $enabledRepositories);

        $repositories = array_values(array_filter(
            $query->repositoriesOrAll(),
            fn (MarketplaceRepository $r) => in_array($r->value, $enabledValues, true)
        ));

        $merged = MarketplaceSearchResultData::empty($query->page, $query->perPage);

        foreach ($repositories as $repository) {
            $client = $this->clients->for($repository);
            if ($client === null) {
                continue;
            }

            $result = $client->search($query);
            $merged = $merged->merge($result);
        }

        $items = $this->sort($merged->items, $query->sort);

        return new MarketplaceSearchResultData(
            items: array_slice($items, 0, $query->perPage),
            page: $query->page,
            perPage: $query->perPage,
            total: $merged->total,
            hasMore: $merged->hasMore,
            errors: $merged->errors,
        );
    }

    public function find(MarketplaceRepository $repository, string $projectId): ?MarketplacePluginData
    {
        return $this->clients->for($repository)?->find($projectId);
    }

    /** @param MarketplacePluginData[] $items */
    private function sort(array $items, MarketplaceSort $sort): array
    {
        $items = array_values($items);

        usort($items, function (MarketplacePluginData $a, MarketplacePluginData $b) use ($sort) {
            return match ($sort) {
                MarketplaceSort::Downloads => $b->downloads <=> $a->downloads,
                MarketplaceSort::Updated => ($b->updatedAt?->timestamp ?? 0) <=> ($a->updatedAt?->timestamp ?? 0),
                MarketplaceSort::Rating => ($b->rating ?? 0) <=> ($a->rating ?? 0),
                MarketplaceSort::Name => strcasecmp($a->name, $b->name),
                MarketplaceSort::Popular => $this->popularityScore($b) <=> $this->popularityScore($a),
            };
        });

        return $items;
    }

    private function popularityScore(MarketplacePluginData $plugin): float
    {
        return $plugin->downloads + (($plugin->followers ?? 0) * 10) + (($plugin->rating ?? 0) * 1000);
    }
}
