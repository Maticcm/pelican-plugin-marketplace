<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

use Illuminate\Support\Carbon;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Support\PlaceholderIcon;

/**
 * Unified representation of a plugin listing, regardless of which
 * repository it came from. Every repository client is responsible for
 * mapping its own API response shape onto this object so the rest of
 * the plugin (search aggregation, cards, detail page, health checks)
 * never has to know which upstream API a result originated from.
 */
final readonly class MarketplacePluginData
{
    /**
     * @param  string[]  $categories
     * @param  string[]  $minecraftVersions
     * @param  string[]  $loaders
     * @param  MarketplaceGalleryImageData[]  $gallery
     */
    public function __construct(
        public MarketplaceRepository $repository,
        public string $projectId,
        public string $slug,
        public string $name,
        public ?string $summary,
        public ?string $description,
        public ?string $iconUrl,
        public string $author,
        public ?string $authorUrl,
        public array $categories,
        public int $downloads,
        public ?float $rating,
        public ?int $followers,
        public ?string $latestVersion,
        public array $minecraftVersions,
        public array $loaders,
        public ?string $sourceUrl,
        public ?string $issuesUrl,
        public ?string $wikiUrl,
        public ?string $externalHomepageUrl,
        public array $gallery,
        public ?Carbon $createdAt,
        public ?Carbon $updatedAt,
        public ?string $license = null,
    ) {}

    public function homepageUrl(): string
    {
        return $this->repository->homepageUrl($this->repository === MarketplaceRepository::Hangar ? $this->projectId : $this->slug);
    }

    public function iconUrlOrPlaceholder(): string
    {
        return PlaceholderIcon::or($this->iconUrl);
    }

    /**
     * A stable cross-repository cache/identity key.
     */
    public function key(): string
    {
        return $this->repository->value . ':' . $this->projectId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'repository' => $this->repository->value,
            'project_id' => $this->projectId,
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'icon_url' => $this->iconUrlOrPlaceholder(),
            'author' => $this->author,
            'author_url' => $this->authorUrl,
            'categories' => $this->categories,
            'downloads' => $this->downloads,
            'rating' => $this->rating,
            'followers' => $this->followers,
            'latest_version' => $this->latestVersion,
            'minecraft_versions' => $this->minecraftVersions,
            'loaders' => $this->loaders,
            'source_url' => $this->sourceUrl,
            'issues_url' => $this->issuesUrl,
            'wiki_url' => $this->wikiUrl,
            'homepage_url' => $this->homepageUrl(),
            'external_homepage_url' => $this->externalHomepageUrl,
            'gallery' => array_map(fn (MarketplaceGalleryImageData $g) => $g->toArray(), $this->gallery),
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'license' => $this->license,
            'supports_direct_install' => $this->repository->supportsDirectInstall(),
        ];
    }
}
