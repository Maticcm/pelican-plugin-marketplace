<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

final readonly class MarketplaceSearchResultData
{
    /** @param MarketplacePluginData[] $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public bool $hasMore,
        /** @var array<string, string|null> keyed by repository value, holding an error message if that repository failed */
        public array $errors = [],
    ) {}

    public static function empty(int $page = 1, int $perPage = 20): self
    {
        return new self(items: [], page: $page, perPage: $perPage, total: 0, hasMore: false);
    }

    public function merge(self $other): self
    {
        return new self(
            items: [...$this->items, ...$other->items],
            page: $this->page,
            perPage: $this->perPage,
            total: $this->total + $other->total,
            hasMore: $this->hasMore || $other->hasMore,
            errors: [...$this->errors, ...$other->errors],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (MarketplacePluginData $item) => $item->toArray(), $this->items),
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'has_more' => $this->hasMore,
            'errors' => $this->errors,
        ];
    }
}
