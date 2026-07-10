<?php

namespace PelicanMarketplace\PluginMarketplace\Tests\Fixtures;

use PelicanMarketplace\PluginMarketplace\Contracts\RepositoryClient;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * An in-memory stand-in for a real repository client, used to unit
 * test aggregation/resolution logic (MarketplaceSearchService,
 * DependencyResolverService) without making any HTTP calls or needing
 * the database-backed settings/cache services a real client depends on.
 */
class FakeRepositoryClient implements RepositoryClient
{
    /** @param MarketplacePluginData[] $plugins */
    public function __construct(
        private readonly MarketplaceRepository $repositoryEnum,
        private array $plugins = [],
        /** @var array<string, MarketplaceVersionData[]> */
        private array $versionsByProjectId = [],
        private bool $enabled = true,
    ) {}

    public function repository(): MarketplaceRepository
    {
        return $this->repositoryEnum;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData
    {
        $items = array_values(array_filter(
            $this->plugins,
            fn (MarketplacePluginData $plugin) => $query->term === '' || str_contains(strtolower($plugin->name), strtolower($query->term))
        ));

        return new MarketplaceSearchResultData(
            items: $items,
            page: $query->page,
            perPage: $query->perPage,
            total: count($items),
            hasMore: false,
        );
    }

    public function find(string $projectId): ?MarketplacePluginData
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->projectId === $projectId) {
                return $plugin;
            }
        }

        return null;
    }

    public function versions(string $projectId): array
    {
        return $this->versionsByProjectId[$projectId] ?? [];
    }

    public function latestCompatibleVersion(string $projectId, ?string $minecraftVersion = null): ?MarketplaceVersionData
    {
        return $this->versions($projectId)[0] ?? null;
    }

    public function categories(): array
    {
        return [];
    }
}
