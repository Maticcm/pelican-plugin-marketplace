<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;

final readonly class MarketplaceSearchQuery
{
    /**
     * @param  MarketplaceRepository[]  $repositories
     * @param  string[]  $categories
     */
    public function __construct(
        public string $term = '',
        public array $repositories = [],
        public array $categories = [],
        public ?string $minecraftVersion = null,
        public MarketplaceSort $sort = MarketplaceSort::Popular,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /** @return MarketplaceRepository[] */
    public function repositoriesOrAll(): array
    {
        return $this->repositories === [] ? MarketplaceRepository::cases() : $this->repositories;
    }
}
