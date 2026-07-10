<?php

namespace PelicanMarketplace\PluginMarketplace\Services\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PelicanMarketplace\PluginMarketplace\Contracts\RepositoryClient;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceDependencyData;
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
 * Client for the Hangar API (https://hangar.papermc.io/api/v1), the
 * official plugin repository run by PaperMC.
 *
 * Hangar identifies projects by an "owner/slug" namespace pair rather
 * than a single opaque id, so throughout this plugin a Hangar
 * `projectId` string is always that composite `owner/slug` form (e.g.
 * `EssentialsX/Essentials`), never Hangar's internal numeric row id.
 *
 * Endpoint shapes below were verified directly against the live API
 * (not just documentation) while building this client.
 */
class HangarClient implements RepositoryClient
{
    use BuildsHttpClient;

    /** @var array<string, string> Hangar's fixed category enum, as observed on the live API. */
    private const CATEGORIES = [
        'admin_tools' => 'admin_tools',
        'chat' => 'chat',
        'dev_tools' => 'library',
        'economy' => 'economy',
        'food' => 'other',
        'gameplay' => 'mechanics',
        'games' => 'games',
        'protection' => 'protection',
        'role_playing' => 'roleplay',
        'world_management' => 'world_management',
        'misc' => 'other',
        'undefined' => 'other',
    ];

    public function __construct(
        private readonly MarketplaceCacheService $cache,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function repository(): MarketplaceRepository
    {
        return MarketplaceRepository::Hangar;
    }

    public function isEnabled(): bool
    {
        return $this->settings->isRepositoryEnabled('hangar');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('plugin-marketplace.repositories.hangar.base_url'), '/');
    }

    public function search(MarketplaceSearchQuery $query): MarketplaceSearchResultData
    {
        if (!$this->isEnabled()) {
            return MarketplaceSearchResultData::empty($query->page, $query->perPage);
        }

        $cacheKey = 'hangar.search.' . md5(serialize($query));

        return $this->cache->remember($cacheKey, function () use ($query) {
            try {
                $response = $this->http()->get('/projects', array_filter([
                    'q' => $query->term !== '' ? $query->term : null,
                    'limit' => $query->perPage,
                    'offset' => ($query->page - 1) * $query->perPage,
                    'sort' => $this->mapSort($query->sort),
                    'category' => $query->categories === [] ? null : $this->toHangarCategory($query->categories[0]),
                    'platform' => 'PAPER',
                    'version' => $query->minecraftVersion,
                ], fn ($value) => $value !== null));

                if ($response->failed()) {
                    Log::warning('[plugin-marketplace] Hangar search failed', ['status' => $response->status()]);

                    return MarketplaceSearchResultData::empty($query->page, $query->perPage);
                }

                $body = $response->json();
                $count = Arr::get($body, 'pagination.count', 0);
                $items = array_map(fn (array $project) => $this->mapProject($project), Arr::get($body, 'result', []));

                return new MarketplaceSearchResultData(
                    items: $items,
                    page: $query->page,
                    perPage: $query->perPage,
                    total: $count,
                    hasMore: ($query->page * $query->perPage) < $count,
                );
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Hangar search threw', ['exception' => $exception->getMessage()]);

                return MarketplaceSearchResultData::empty($query->page, $query->perPage);
            }
        }, minutes: $this->settings->cacheDurationMinutes());
    }

    public function find(string $projectId): ?MarketplacePluginData
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return $this->cache->remember("hangar.project.$projectId", function () use ($projectId) {
            try {
                $response = $this->http()->get("/projects/$projectId");

                if ($response->failed()) {
                    return null;
                }

                return $this->mapProject($response->json());
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Hangar find() threw', ['exception' => $exception->getMessage()]);

                return null;
            }
        });
    }

    public function versions(string $projectId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return $this->cache->remember("hangar.versions.$projectId", function () use ($projectId) {
            try {
                $response = $this->http()->get("/projects/$projectId/versions", [
                    'limit' => 25,
                    'offset' => 0,
                ]);

                if ($response->failed()) {
                    return [];
                }

                $versions = Arr::get($response->json(), 'result', []);

                return array_values(array_filter(array_map(
                    fn (array $version) => $this->mapVersion($version),
                    array_filter($versions, fn (array $v) => Arr::get($v, 'visibility') === 'public'),
                )));
            } catch (Throwable $exception) {
                Log::warning('[plugin-marketplace] Hangar versions() threw', ['exception' => $exception->getMessage()]);

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
        return array_values(array_unique(self::CATEGORIES));
    }

    /** @param array<string, mixed> $project */
    private function mapProject(array $project): MarketplacePluginData
    {
        $owner = Arr::get($project, 'namespace.owner');
        $slug = Arr::get($project, 'namespace.slug');
        $projectId = "$owner/$slug";

        $links = collect(Arr::get($project, 'settings.links', []))
            ->flatMap(fn (array $group) => Arr::get($group, 'links', []));

        $findLink = fn (array $names) => Arr::get(
            $links->first(fn (array $link) => in_array(strtolower((string) Arr::get($link, 'name')), $names, true)),
            'url'
        );

        return new MarketplacePluginData(
            repository: MarketplaceRepository::Hangar,
            projectId: $projectId,
            slug: $slug,
            name: Arr::get($project, 'name', $slug),
            summary: Arr::get($project, 'description'),
            description: Arr::get($project, 'mainPageContent'),
            iconUrl: Arr::get($project, 'avatarUrl'),
            author: $owner,
            authorUrl: "https://hangar.papermc.io/$owner",
            categories: [$this->toNormalizedCategory(Arr::get($project, 'category', 'misc'))],
            downloads: (int) Arr::get($project, 'stats.downloads', 0),
            rating: null,
            followers: (int) Arr::get($project, 'stats.watchers', 0),
            latestVersion: null,
            minecraftVersions: array_values(Arr::get($project, 'supportedPlatforms.PAPER', [])),
            loaders: ['paper'],
            sourceUrl: $findLink(['source', 'github', 'git', 'repository']),
            issuesUrl: $findLink(['issues', 'bug tracker', 'bugs']),
            wikiUrl: $findLink(['wiki', 'documentation', 'docs']),
            externalHomepageUrl: $findLink(['homepage', 'website']),
            gallery: [],
            createdAt: $this->parseDate(Arr::get($project, 'createdAt')),
            updatedAt: $this->parseDate(Arr::get($project, 'lastUpdated')),
            license: Arr::get($project, 'settings.license.name') ?? Arr::get($project, 'settings.license.type'),
        );
    }

    /** @param array<string, mixed> $version */
    private function mapVersion(array $version): ?MarketplaceVersionData
    {
        $download = Arr::get($version, 'downloads.PAPER');

        if ($download === null) {
            return null;
        }

        $downloadUrl = Arr::get($download, 'downloadUrl') ?? Arr::get($download, 'externalUrl');
        if ($downloadUrl === null) {
            return null;
        }

        $dependencies = collect(Arr::get($version, 'pluginDependencies.PAPER', []))
            ->map(fn (array $dependency) => new MarketplaceDependencyData(
                name: Arr::get($dependency, 'name'),
                required: (bool) Arr::get($dependency, 'required', true),
                repository: Arr::get($dependency, 'projectId') !== null ? MarketplaceRepository::Hangar : null,
                projectId: $this->resolveNumericProjectId(Arr::get($dependency, 'projectId')),
                resolvable: Arr::get($dependency, 'projectId') !== null,
            ))
            ->values()
            ->all();

        return new MarketplaceVersionData(
            id: (string) Arr::get($version, 'id'),
            name: Arr::get($version, 'name'),
            versionNumber: Arr::get($version, 'name'),
            changelog: Arr::get($version, 'description'),
            downloadUrl: $downloadUrl,
            fileName: Arr::get($download, 'fileInfo.name'),
            fileSize: Arr::get($download, 'fileInfo.sizeBytes'),
            minecraftVersions: array_values(Arr::get($version, 'platformDependencies.PAPER', [])),
            loaders: ['paper'],
            dependencies: $dependencies,
            publishedAt: $this->parseDate(Arr::get($version, 'createdAt')),
            channel: strtolower((string) Arr::get($version, 'channel.name', 'release')),
            downloads: (int) Arr::get($version, 'stats.totalDownloads', 0),
        );
    }

    private function resolveNumericProjectId(?int $numericId): ?string
    {
        if ($numericId === null) {
            return null;
        }

        return $this->cache->remember("hangar.project-id.$numericId", function () use ($numericId) {
            try {
                $response = $this->http()->get("/projects/$numericId");
                if ($response->failed()) {
                    return null;
                }

                $owner = Arr::get($response->json(), 'namespace.owner');
                $slug = Arr::get($response->json(), 'namespace.slug');

                return $owner && $slug ? "$owner/$slug" : null;
            } catch (Throwable) {
                return null;
            }
        }, minutes: 60 * 24);
    }

    private function mapSort(MarketplaceSort $sort): string
    {
        return match ($sort) {
            MarketplaceSort::Popular => '-stars',
            MarketplaceSort::Downloads => '-downloads',
            MarketplaceSort::Updated => 'updated',
            MarketplaceSort::Rating => '-stars',
            MarketplaceSort::Name => 'slug',
        };
    }

    private function toHangarCategory(string $normalized): ?string
    {
        return array_search($normalized, self::CATEGORIES, true) ?: null;
    }

    private function toNormalizedCategory(string $hangarCategory): string
    {
        return self::CATEGORIES[$hangarCategory] ?? 'other';
    }

    private function parseDate(?string $date): ?Carbon
    {
        return $date ? Carbon::parse($date) : null;
    }
}
