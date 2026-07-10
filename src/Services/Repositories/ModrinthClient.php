<?php

namespace PelicanMarketplace\PluginMarketplace\Services\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PelicanMarketplace\PluginMarketplace\Contracts\RepositoryClient;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceDependencyData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceGalleryImageData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchResultData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceCategory;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceCacheService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\Repositories\Concerns\BuildsHttpClient;
use Throwable;

/**
 * Client for the Modrinth API v2 (https://api.modrinth.com/v2).
 *
 * Modrinth stores "platform" info (bukkit/spigot/paper/purpur/folia vs
 * fabric/forge/neoforge/quilt) as plain tags mixed into the `categories`
 * array on search results, but as a clean, separate `loaders` array on
 * the full project resource and on every version resource. Both shapes
 * were confirmed against the live API while building this client, and
 * both are handled explicitly below - this is the single most
 * important subtlety in this client, since getting it wrong would leak
 * Fabric/Forge mods into the marketplace.
 */
class ModrinthClient implements RepositoryClient
{
    use BuildsHttpClient;

    public function __construct(
        private readonly MarketplaceCacheService $cache,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function repository(): MarketplaceRepository
    {
        return MarketplaceRepository::Modrinth;
    }

    public function isEnabled(): bool
    {
        return $this->settings->isRepositoryEnabled('modrinth');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('plugin-marketplace.repositories.modrinth.base_url'), '/');
    }

    private function supportedLoaders(): array
    {
        return config('plugin-marketplace.supported_loaders', ['bukkit', 'spigot', 'paper', 'purpur', 'folia']);
    }

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData
    {
        if (!$this->isEnabled()) {
            return MarketplaceSearchResultData::empty($query->page, $query->perPage);
        }

        $cacheKey = 'modrinth.search.' . md5(serialize($query));

        return $this->cache->remember($cacheKey, function () use ($query) {
            try {
                $facets = [
                    ['project_type:plugin'],
                    array_map(fn (string $loader) => "categories:$loader", $this->supportedLoaders()),
                ];

                if ($query->minecraftVersion) {
                    $facets[] = ["versions:{$query->minecraftVersion}"];
                }

                if ($query->categories !== []) {
                    $facets[] = array_map(fn (string $category) => "categories:$category", $query->categories);
                }

                $response = $this->http()->get('/search', [
                    'query' => $query->term,
                    'facets' => json_encode($facets),
                    'index' => $this->mapSort($query->sort),
                    'offset' => ($query->page - 1) * $query->perPage,
                    'limit' => $query->perPage,
                ]);

                if ($response->failed()) {
                    Log::warning('[plugin-marketplace] Modrinth search failed', ['status' => $response->status()]);

                    return MarketplaceSearchResultData::empty($query->page, $query->perPage);
                }

                $body = $response->json();
                $hits = Arr::get($body, 'hits', []);

                // Belt-and-braces: even though facets already restrict to
                // Bukkit-family loaders, defensively drop anything that
                // slips through without a matching loader tag.
                $hits = array_filter($hits, fn (array $hit) => $this->hasSupportedLoader(Arr::get($hit, 'categories', [])));

                $items = array_map(fn (array $hit) => $this->mapSearchHit($hit), $hits);

                if ($query->sort === MarketplaceSort::Name) {
                    usort($items, fn (MarketplacePluginData $a, MarketplacePluginData $b) => strcasecmp($a->name, $b->name));
                }

                $total = (int) Arr::get($body, 'total_hits', count($items));

                return new MarketplaceSearchResultData(
                    items: array_values($items),
                    page: $query->page,
                    perPage: $query->perPage,
                    total: $total,
                    hasMore: ($query->page * $query->perPage) < $total,
                );
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Modrinth search threw', ['exception' => $exception->getMessage()]);

                return MarketplaceSearchResultData::empty($query->page, $query->perPage);
            }
        }, minutes: $this->settings->cacheDurationMinutes());
    }

    public function find(string $projectId): ?MarketplacePluginData
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return $this->cache->remember("modrinth.project.$projectId", function () use ($projectId) {
            try {
                $response = $this->http()->get("/project/$projectId");

                if ($response->failed()) {
                    return null;
                }

                $project = $response->json();

                if (!$this->hasSupportedLoader(Arr::get($project, 'loaders', []))) {
                    return null;
                }

                return $this->mapProject($project);
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Modrinth find() threw', ['exception' => $exception->getMessage()]);

                return null;
            }
        });
    }

    public function versions(string $projectId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return $this->cache->remember("modrinth.versions.$projectId", function () use ($projectId) {
            try {
                $response = $this->http()->get("/project/$projectId/version");

                if ($response->failed()) {
                    return [];
                }

                $versions = collect($response->json())
                    ->filter(fn (array $version) => $this->hasSupportedLoader(Arr::get($version, 'loaders', [])))
                    ->values()
                    ->all();

                $dependencyNames = $this->resolveDependencyNames($versions);

                return array_map(fn (array $version) => $this->mapVersion($version, $dependencyNames), $versions);
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Modrinth versions() threw', ['exception' => $exception->getMessage()]);

                return [];
            }
        });
    }

    public function latestCompatibleVersion(string $projectId, ?string $minecraftVersion = null): ?MarketplaceVersionData
    {
        $versions = $this->versions($projectId);

        foreach ($versions as $version) {
            if ($minecraftVersion === null || $version->supportsMinecraftVersion($minecraftVersion)) {
                return $version;
            }
        }

        return $versions[0] ?? null;
    }

    /** @return string[] */
    public function categories(): array
    {
        return array_keys(MarketplaceCategory::options());
    }

    private function hasSupportedLoader(array $tags): bool
    {
        $tags = array_map('strtolower', $tags);

        return array_intersect($tags, $this->supportedLoaders()) !== [];
    }

    /** @param array<string, mixed> $hit */
    private function mapSearchHit(array $hit): MarketplacePluginData
    {
        $tags = array_map('strtolower', Arr::get($hit, 'categories', []));
        $loaders = array_values(array_intersect($tags, $this->supportedLoaders()));
        $categories = array_values(array_diff($tags, $this->supportedLoaders()));

        $gallery = collect(Arr::get($hit, 'gallery', []))
            ->map(fn (string $url) => new MarketplaceGalleryImageData(url: $url))
            ->all();

        return new MarketplacePluginData(
            repository: MarketplaceRepository::Modrinth,
            projectId: Arr::get($hit, 'project_id'),
            slug: Arr::get($hit, 'slug'),
            name: Arr::get($hit, 'title'),
            summary: Arr::get($hit, 'description'),
            description: null,
            iconUrl: Arr::get($hit, 'icon_url'),
            author: Arr::get($hit, 'author', 'Unknown'),
            authorUrl: Arr::get($hit, 'author') ? 'https://modrinth.com/user/' . Arr::get($hit, 'author') : null,
            categories: array_map(fn (string $c) => str_replace('-', '_', $c), $categories),
            downloads: (int) Arr::get($hit, 'downloads', 0),
            rating: null,
            followers: (int) Arr::get($hit, 'follows', 0),
            // The search endpoint's `latest_version` field is a version
            // *id*, not a human-readable label - resolving it here would
            // cost an extra HTTP round trip per card, so it is left blank
            // for list views and populated properly on the detail page
            // via versions()/latestCompatibleVersion() instead.
            latestVersion: null,
            minecraftVersions: array_values(Arr::get($hit, 'versions', [])),
            loaders: $loaders,
            sourceUrl: null,
            issuesUrl: null,
            wikiUrl: null,
            externalHomepageUrl: null,
            gallery: $gallery,
            createdAt: $this->parseDate(Arr::get($hit, 'date_created')),
            updatedAt: $this->parseDate(Arr::get($hit, 'date_modified')),
            license: Arr::get($hit, 'license'),
        );
    }

    /** @param array<string, mixed> $project */
    private function mapProject(array $project): MarketplacePluginData
    {
        $gallery = collect(Arr::get($project, 'gallery', []))
            ->map(fn (array $image) => new MarketplaceGalleryImageData(
                url: Arr::get($image, 'url'),
                caption: Arr::get($image, 'title') ?? Arr::get($image, 'description'),
                featured: (bool) Arr::get($image, 'featured', false),
            ))
            ->all();

        $author = $this->resolveTeamOwnerUsername(Arr::get($project, 'team'));

        return new MarketplacePluginData(
            repository: MarketplaceRepository::Modrinth,
            projectId: Arr::get($project, 'id'),
            slug: Arr::get($project, 'slug'),
            name: Arr::get($project, 'title'),
            summary: Arr::get($project, 'description'),
            description: Arr::get($project, 'body'),
            iconUrl: Arr::get($project, 'icon_url'),
            author: $author ?? 'Unknown',
            authorUrl: $author ? "https://modrinth.com/user/$author" : null,
            categories: array_map(
                fn (string $c) => str_replace('-', '_', $c),
                array_diff(array_map('strtolower', Arr::get($project, 'categories', [])), $this->supportedLoaders())
            ),
            downloads: (int) Arr::get($project, 'downloads', 0),
            rating: null,
            followers: (int) Arr::get($project, 'followers', 0),
            latestVersion: null,
            minecraftVersions: array_values(Arr::get($project, 'game_versions', [])),
            loaders: array_values(array_intersect(array_map('strtolower', Arr::get($project, 'loaders', [])), $this->supportedLoaders())),
            sourceUrl: Arr::get($project, 'source_url'),
            issuesUrl: Arr::get($project, 'issues_url'),
            wikiUrl: Arr::get($project, 'wiki_url'),
            externalHomepageUrl: Arr::get($project, 'discord_url'),
            gallery: $gallery,
            createdAt: $this->parseDate(Arr::get($project, 'published')),
            updatedAt: $this->parseDate(Arr::get($project, 'updated')),
            license: Arr::get($project, 'license.name') ?: Arr::get($project, 'license.id'),
        );
    }

    /** @param array<string, mixed> $version */
    private function mapVersion(array $version, array $dependencyNames): MarketplaceVersionData
    {
        $primaryFile = collect(Arr::get($version, 'files', []))->firstWhere('primary', true)
            ?? Arr::first(Arr::get($version, 'files', []));

        $dependencies = collect(Arr::get($version, 'dependencies', []))
            ->filter(fn (array $dependency) => in_array(Arr::get($dependency, 'dependency_type'), ['required', 'optional'], true))
            ->map(function (array $dependency) use ($dependencyNames) {
                $projectId = Arr::get($dependency, 'project_id');

                return new MarketplaceDependencyData(
                    name: $dependencyNames[$projectId] ?? Arr::get($dependency, 'file_name') ?? 'Unknown dependency',
                    required: Arr::get($dependency, 'dependency_type') === 'required',
                    repository: $projectId ? MarketplaceRepository::Modrinth : null,
                    projectId: $projectId,
                    resolvable: $projectId !== null,
                );
            })
            ->values()
            ->all();

        return new MarketplaceVersionData(
            id: Arr::get($version, 'id'),
            name: Arr::get($version, 'name'),
            versionNumber: Arr::get($version, 'version_number'),
            changelog: Arr::get($version, 'changelog'),
            downloadUrl: Arr::get($primaryFile, 'url'),
            fileName: Arr::get($primaryFile, 'filename'),
            fileSize: Arr::get($primaryFile, 'size'),
            minecraftVersions: array_values(Arr::get($version, 'game_versions', [])),
            loaders: array_values(Arr::get($version, 'loaders', [])),
            dependencies: $dependencies,
            publishedAt: $this->parseDate(Arr::get($version, 'date_published')),
            channel: Arr::get($version, 'version_type', 'release'),
            downloads: (int) Arr::get($version, 'downloads', 0),
        );
    }

    /**
     * Batch-resolve project id -> title for every dependency across a
     * project's versions in a single request, instead of one request
     * per dependency.
     *
     * @param  array<int, array<string, mixed>>  $versions
     * @return array<string, string>
     */
    private function resolveDependencyNames(array $versions): array
    {
        $ids = collect($versions)
            ->flatMap(fn (array $version) => Arr::get($version, 'dependencies', []))
            ->pluck('project_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->cache->remember('modrinth.dependency-names.' . md5($ids->join(',')), function () use ($ids) {
            try {
                $response = $this->http()->get('/projects', ['ids' => json_encode($ids->all())]);

                if ($response->failed()) {
                    return [];
                }

                return collect($response->json())->mapWithKeys(fn (array $p) => [$p['id'] => $p['title']])->all();
            } catch (Throwable) {
                return [];
            }
        }, minutes: 60 * 24);
    }

    private function resolveTeamOwnerUsername(?string $teamId): ?string
    {
        if ($teamId === null) {
            return null;
        }

        return $this->cache->remember("modrinth.team.$teamId", function () use ($teamId) {
            try {
                $response = $this->http()->get("/team/$teamId/members");

                if ($response->failed()) {
                    return null;
                }

                $members = collect($response->json());

                $owner = $members->firstWhere('role', 'Owner') ?? $members->first();

                return Arr::get($owner, 'user.username');
            } catch (Throwable) {
                return null;
            }
        }, minutes: 60 * 24);
    }

    private function mapSort(MarketplaceSort $sort): string
    {
        return match ($sort) {
            MarketplaceSort::Popular, MarketplaceSort::Rating => 'follows',
            MarketplaceSort::Downloads => 'downloads',
            MarketplaceSort::Updated => 'updated',
            MarketplaceSort::Name => 'relevance',
        };
    }

    private function parseDate(?string $date): ?Carbon
    {
        return $date ? Carbon::parse($date) : null;
    }
}
