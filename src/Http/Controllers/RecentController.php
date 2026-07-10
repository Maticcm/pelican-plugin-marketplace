<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Services\RecentPluginsService;

class RecentController extends Controller
{
    public function __invoke(Request $request, RecentPluginsService $recent): JsonResponse
    {
        return response()->json(['data' => $recent->list($request->user())]);
    }
}
