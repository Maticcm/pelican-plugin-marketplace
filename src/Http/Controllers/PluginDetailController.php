<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\PluginHealthService;
use PelicanMarketplace\PluginMarketplace\Services\RecentPluginsService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;
use Symfony\Component\HttpFoundation\Response;

class PluginDetailController extends Controller
{
    public function __invoke(
        Request $request,
        string $repository,
        string $projectId,
        MarketplaceSearchService $search,
        RepositoryClientManager $clients,
        PluginHealthService $health,
        RecentPluginsService $recent,
    ): JsonResponse {
        $repositoryEnum = MarketplaceRepository::tryFrom($repository);
        abort_if($repositoryEnum === null, Response::HTTP_NOT_FOUND, 'Unknown repository.');

        $plugin = $search->find($repositoryEnum, $projectId);
        abort_if($plugin === null, Response::HTTP_NOT_FOUND, 'Plugin not found.');

        if ($request->user()) {
            $recent->record($request->user(), $plugin);
        }

        $versions = $clients->for($repositoryEnum)?->versions($projectId) ?? [];

        return response()->json([
            'data' => $plugin->toArray(),
            'health' => $health->status($plugin)->value,
            'health_message' => $health->warningMessage($plugin),
            'versions' => array_map(fn ($version) => $version->toArray(), $versions),
        ]);
    }
}
