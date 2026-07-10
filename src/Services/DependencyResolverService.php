<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceDependencyData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;

/**
 * Cross-references a version's declared dependencies against what is
 * already installed on the server, and - for dependencies the
 * repository couldn't resolve to a project id on its own (a plain
 * plugin.yml `depend` name, or a Hangar dependency that only has a
 * `name` and an `externalUrl`) - attempts to resolve them anyway using
 * the curated `known_dependencies` config map.
 */
class DependencyResolverService
{
    public function __construct(private readonly MarketplaceSearchService $search) {}

    /**
     * @param  MarketplaceDependencyData[]  $dependencies
     * @param  Collection<int, InstalledPlugin>  $installedPlugins
     * @return MarketplaceDependencyData[]
     */
    public function resolve(array $dependencies, Collection $installedPlugins): array
    {
        $installedNames = $installedPlugins->pluck('name')->map(fn (string $name) => strtolower($name))->all();

        return array_map(function (MarketplaceDependencyData $dependency) use ($installedNames) {
            $satisfied = in_array(strtolower($dependency->name), $installedNames, true);

            if ($dependency->resolvable || $satisfied) {
                return $dependency->withSatisfied($satisfied);
            }

            $known = $this->resolveKnownDependency($dependency->name);
            if ($known === null) {
                return $dependency->withSatisfied($satisfied);
            }

            return new MarketplaceDependencyData(
                name: $dependency->name,
                required: $dependency->required,
                repository: $known->repository,
                projectId: $known->projectId,
                slug: $known->slug,
                resolvable: true,
                satisfied: $satisfied,
            );
        }, $dependencies);
    }

    /**
     * @param  MarketplaceVersionData[]  $resolvedVersionsByDependency  keyed by dependency name, only for entries that need installing
     * @return array<int, MarketplacePluginData>
     */
    public function findInstallable(array $dependencies): array
    {
        $installable = [];

        foreach ($dependencies as $dependency) {
            if ($dependency->satisfied || !$dependency->resolvable || $dependency->repository === null || $dependency->projectId === null) {
                continue;
            }

            $plugin = $this->search->find($dependency->repository, $dependency->projectId);

            if ($plugin !== null) {
                $installable[$dependency->name] = $plugin;
            }
        }

        return $installable;
    }

    private function resolveKnownDependency(string $name): ?MarketplaceDependencyData
    {
        $map = config('plugin-marketplace.known_dependencies', []);
        $entry = Arr::get($map, $name);

        if ($entry === null) {
            return null;
        }

        $repository = MarketplaceRepository::tryFrom(Arr::get($entry, 'repository', ''));
        if ($repository === null) {
            return null;
        }

        return new MarketplaceDependencyData(
            name: $name,
            required: true,
            repository: $repository,
            projectId: Arr::get($entry, 'slug'),
            slug: Arr::get($entry, 'slug'),
            resolvable: true,
        );
    }
}
