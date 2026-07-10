<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PelicanMarketplace\PluginMarketplace\Http\Requests\SearchRequest;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, MarketplaceSearchService $search): JsonResponse
    {
        $result = $search->search($request->toQuery());

        return response()->json($result->toArray());
    }
}
