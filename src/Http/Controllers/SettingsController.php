<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Http\Requests\UpdateSettingsRequest;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function show(Request $request, MarketplaceSettingsService $settings): JsonResponse
    {
        abort_unless($request->user()?->can('view plugins'), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $settings->current()]);
    }

    public function update(UpdateSettingsRequest $request, MarketplaceSettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->update($request->validated())]);
    }
}
