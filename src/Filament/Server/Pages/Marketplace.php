<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Pages;

use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceCategory;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins\InstalledPluginResource;
use PelicanMarketplace\PluginMarketplace\Models\Favorite;
use PelicanMarketplace\PluginMarketplace\Models\RecentlyViewed;
use PelicanMarketplace\PluginMarketplace\Services\FavoritesService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\RecentPluginsService;

class Marketplace extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?int $navigationSort = 0;

    protected string $view = 'plugin-marketplace::filament.server.pages.marketplace';

    public string $search = '';

    /** @var string[] */
    public array $repositories = [];

    public ?string $category = null;

    public ?string $minecraftVersion = null;

    public string $sort = 'popular';

    public int $page = 1;

    public bool $favoritesOnly = false;

    public static function canAccess(): bool
    {
        return (bool) user()?->can('plugins.view', Filament::getTenant());
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('plugin-marketplace::marketplace.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('plugin-marketplace::marketplace.marketplace.nav_label');
    }

    public function getTitle(): string
    {
        return trans('plugin-marketplace::marketplace.marketplace.title');
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function results(): MarketplaceSearchResultData
    {
        if ($this->favoritesOnly) {
            return $this->favoritesAsResult();
        }

        return app(MarketplaceSearchService::class)->search(new MarketplaceSearchQuery(
            term: $this->search,
            repositories: array_map(fn (string $r) => MarketplaceRepository::from($r), $this->repositories),
            categories: $this->category ? [$this->category] : [],
            minecraftVersion: $this->minecraftVersion ?: null,
            sort: MarketplaceSort::from($this->sort),
            page: $this->page,
            perPage: 24,
        ));
    }

    private function favoritesAsResult(): MarketplaceSearchResultData
    {
        $favorites = app(FavoritesService::class)->list(user());

        return new MarketplaceSearchResultData(
            items: $favorites->map(fn (Favorite $favorite) => new MarketplacePluginData(
                repository: $favorite->repository,
                projectId: $favorite->project_id,
                slug: $favorite->slug,
                name: $favorite->name,
                summary: null,
                description: null,
                iconUrl: $favorite->icon_url,
                author: '',
                authorUrl: null,
                categories: [],
                downloads: 0,
                rating: null,
                followers: null,
                latestVersion: null,
                minecraftVersions: [],
                loaders: [],
                sourceUrl: null,
                issuesUrl: null,
                wikiUrl: null,
                externalHomepageUrl: null,
                gallery: [],
                createdAt: null,
                updatedAt: null,
            ))->all(),
            page: 1,
            perPage: max($favorites->count(), 1),
            total: $favorites->count(),
            hasMore: false,
        );
    }

    /** @return array<int, RecentlyViewed> */
    public function recentlyViewed(): array
    {
        return app(RecentPluginsService::class)->list(user(), 8)->all();
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return MarketplaceCategory::options();
    }

    public function isFavorited(string $repository, string $projectId): bool
    {
        return in_array("$repository:$projectId", $this->favoritedKeys(), true);
    }

    /**
     * Batched favorite lookup (one query per render instead of one per
     * card) used by the card grid to decide whether to render a filled
     * or outline heart icon.
     *
     * @return string[]
     */
    public function favoritedKeys(): array
    {
        return app(FavoritesService::class)->list(user())
            ->map(fn ($favorite) => "{$favorite->repository->value}:{$favorite->project_id}")
            ->all();
    }

    public function toggleFavorite(string $repository, string $projectId, MarketplaceSearchService $search, FavoritesService $favorites): void
    {
        $plugin = $search->find(MarketplaceRepository::from($repository), $projectId);

        if ($plugin === null) {
            Notification::make()->danger()->title(trans('plugin-marketplace::marketplace.marketplace.plugin_not_found'))->send();

            return;
        }

        $favorited = $favorites->toggle(user(), $plugin);

        Notification::make()
            ->success()
            ->title($favorited ? trans('plugin-marketplace::marketplace.marketplace.favorited') : trans('plugin-marketplace::marketplace.marketplace.unfavorited'))
            ->send();
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function installedPluginsUrl(): string
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return InstalledPluginResource::getUrl(tenant: $server);
    }
}
