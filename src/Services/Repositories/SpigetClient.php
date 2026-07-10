<?php

namespace PelicanMarketplace\PluginMarketplace\Services\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PelicanMarketplace\PluginMarketplace\Contracts\RepositoryClient;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceCacheService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\Concerns\BuildsHttpClient;
use Throwable;

/**
 * Client for the SpiGet API (https://api.spiget.org/v2), a free,
 * read-only, third-party mirror of SpigotMC resource *metadata*.
 *
 * IMPORTANT: SpigotMC's terms of service do not allow third parties to
 * redistribute resource downloads. This client therefore only ever
 * implements discovery (search, listing, and detail metadata) - it has
 * no download/version-file method, `MarketplaceRepository::Spigot`
 * reports `supportsDirectInstall() === false`, and every call site in
 * this plugin that reaches a "Spigot" result renders "Manual download
 * required" and links out to the resource's real spigotmc.org page
 * instead of offering an Install button. Do not add a download method
 * to this class.
 */
class SpigetClient implements RepositoryClient
{
    use BuildsHttpClient;

    private const SPIGOTMC_BASE = 'https://www.spigotmc.org/';

    public function __construct(
        private readonly MarketplaceCacheService $cache,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function repository(): MarketplaceRepository
    {
        return MarketplaceRepository::Spigot;
    }

    public function isEnabled(): bool
    {
        return $this->settings->isRepositoryEnabled('spigot');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('plugin-marketplace.repositories.spigot.base_url'), '/');
    }

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData
    {
        if (!$this->isEnabled()) {
            return MarketplaceSearchResultData::empty($query->page, $query->perPage);
        }

        $cacheKey = 'spiget.search.' . md5(serialize($query));

        return $this->cache->remember($cacheKey, function () use ($query) {
            try {
                $fields = 'id,name,tag,downloads,rating,icon,releaseDate,updateDate,premium,testedVersions';

                if ($query->term !== '') {
                    $response = $this->http()->get('/search/resources/' . rawurlencode($query->term), [
                        'size' => $query->perPage,
                        'page' => $query->page,
                        'sort' => $this->mapSort($query->sort),
                        'fields' => $fields,
                    ]);
                } else {
                    $response = $this->http()->get('/resources', [
                        'size' => $query->perPage,
                        'page' => $query->page,
                        'sort' => $this->mapSort($query->sort),
                        'fields' => $fields,
                    ]);
                }

                if ($response->failed()) {
                    Log::warning('[plugin-marketplace] Spiget search failed', ['status' => $response->status()]);

                    return MarketplaceSearchResultData::empty($query->page, $query->perPage);
                }

                $resources = $response->json() ?? [];
                $items = array_map(fn (array $resource) => $this->mapResource($resource), $resources);

                return new MarketplaceSearchResultData(
                    items: $items,
                    page: $query->page,
                    perPage: $query->perPage,
                    // SpiGet's list endpoints don't return a total count
                    // cheaply, so we treat "got a full page" as "there is
                    // probably another page" rather than paying for an
                    // extra request just to know the exact total.
                    total: count($items),
                    hasMore: count($items) >= $query->perPage,
                );
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Spiget search threw', ['exception' => $exception->getMessage()]);

                return MarketplaceSearchResultData::empty($query->page, $query->perPage);
            }
        }, minutes: $this->settings->cacheDurationMinutes());
    }

    public function find(string $projectId): ?MarketplacePluginData
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return $this->cache->remember("spiget.resource.$projectId", function () use ($projectId) {
            try {
                $response = $this->http()->get("/resources/$projectId");

                if ($response->failed()) {
                    return null;
                }

                return $this->mapResource($response->json(), full: true);
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Spiget find() threw', ['exception' => $exception->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Always returns an empty array. SpigotMC does not permit third
     * parties to redistribute downloads, so this plugin never resolves
     * (let alone fetches) a downloadable file for a Spigot resource.
     *
     * @return MarketplaceVersionData[]
     */
    public function versions(string $projectId): array
    {
        return [];
    }

    public function latestCompatibleVersion(string $projectId, ?string $minecraftVersion = null): ?MarketplaceVersionData
    {
        return null;
    }

    /** @return string[] */
    public function categories(): array
    {
        return $this->cache->remember('spiget.categories', function () {
            try {
                $response = $this->http()->get('/categories', ['size' => 50]);

                if ($response->failed()) {
                    return [];
                }

                return collect($response->json())->pluck('name')->all();
            } catch (Throwable) {
                return [];
            }
        }, minutes: 60 * 24);
    }

    /** @param array<string, mixed> $resource */
    private function mapResource(array $resource, bool $full = false): MarketplacePluginData
    {
        $id = (string) Arr::get($resource, 'id');
        $author = Arr::get($resource, 'author.id') ? $this->resolveAuthorName((int) Arr::get($resource, 'author.id')) : null;

        return new MarketplacePluginData(
            repository: MarketplaceRepository::Spigot,
            projectId: $id,
            slug: $id,
            name: Arr::get($resource, 'name', 'Unknown'),
            summary: Arr::get($resource, 'tag'),
            description: $full ? $this->decodeBase64(Arr::get($resource, 'description')) : null,
            iconUrl: $this->baseUrl() . "/resources/$id/icon",
            author: $author ?? 'Unknown',
            authorUrl: null,
            categories: [],
            downloads: (int) Arr::get($resource, 'downloads', 0),
            rating: Arr::get($resource, 'rating.average') !== null ? (float) Arr::get($resource, 'rating.average') : null,
            followers: (int) Arr::get($resource, 'likes', 0),
            latestVersion: null,
            minecraftVersions: array_values(Arr::get($resource, 'testedVersions', [])),
            loaders: ['spigot'],
            sourceUrl: Arr::get($resource, 'sourceCodeLink'),
            issuesUrl: null,
            wikiUrl: null,
            externalHomepageUrl: null,
            gallery: [],
            createdAt: $this->parseUnixDate(Arr::get($resource, 'releaseDate')),
            updatedAt: $this->parseUnixDate(Arr::get($resource, 'updateDate')),
            license: Arr::get($resource, 'premium') ? 'Premium' : 'Free',
        );
    }

    private function resolveAuthorName(int $authorId): ?string
    {
        return $this->cache->remember("spiget.author.$authorId", function () use ($authorId) {
            try {
                $response = $this->http()->get("/authors/$authorId");

                return $response->failed() ? null : Arr::get($response->json(), 'name');
            } catch (Throwable) {
                return null;
            }
        }, minutes: 60 * 24);
    }

    private function mapSort(MarketplaceSort $sort): string
    {
        return match ($sort) {
            MarketplaceSort::Popular => '-downloads',
            MarketplaceSort::Downloads => '-downloads',
            MarketplaceSort::Updated => '-updateDate',
            MarketplaceSort::Rating => '-rating',
            MarketplaceSort::Name => 'name',
        };
    }

    private function decodeBase64(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? $value : $decoded;
    }

    private function parseUnixDate(int|string|null $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
