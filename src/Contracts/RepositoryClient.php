<?php

namespace PelicanMarketplace\PluginMarketplace\Contracts;

use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

interface RepositoryClient
{
    public function repository(): MarketplaceRepository;

    public function isEnabled(): bool;

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData;

    public function find(string $projectId): ?MarketplacePluginData;

    /** @return MarketplaceVersionData[] */
    public function versions(string $projectId): array;

    public function latestCompatibleVersion(string $projectId, ?string $minecraftVersion = null): ?MarketplaceVersionData;

    /** @return string[] normalized category keys supported by this repository */
    public function categories(): array;
}
