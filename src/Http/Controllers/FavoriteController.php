<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Http\Requests\FavoriteRequest;
use PelicanMarketplace\PluginMarketplace\Services\FavoritesService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use Symfony\Component\HttpFoundation\Response;

class FavoriteController extends Controller
{
    public function index(Request $request, FavoritesService $favorites): JsonResponse
    {
        return response()->json(['data' => $favorites->list($request->user())]);
    }

    public function store(FavoriteRequest $request, FavoritesService $favorites, MarketplaceSearchService $search): JsonResponse
    {
        $data = $request->validated();
        $repository = MarketplaceRepository::from($data['repository']);

        $plugin = $search->find($repository, $data['project_id']);
        abort_if($plugin === null, Response::HTTP_NOT_FOUND, 'Plugin not found.');

        $favorited = $favorites->toggle($request->user(), $plugin);

        return response()->json(['data' => ['favorited' => $favorited]]);
    }

    public function destroy(Request $request, string $repository, string $projectId, FavoritesService $favorites): JsonResponse
    {
        $repositoryEnum = MarketplaceRepository::tryFrom($repository);
        abort_if($repositoryEnum === null, Response::HTTP_NOT_FOUND);

        $favorites->remove($request->user(), $repositoryEnum, $projectId);

        return response()->json(['data' => ['favorited' => false]]);
    }
}
