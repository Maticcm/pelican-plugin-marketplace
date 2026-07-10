<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Jobs\ScanInstalledPluginsJob;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use Symfony\Component\HttpFoundation\Response;

class ScanController extends Controller
{
    public function __invoke(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.view', $server), Response::HTTP_FORBIDDEN);

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'type' => InstallJobType::Scan,
        ]);

        ScanInstalledPluginsJob::dispatch($request->user(), $server, $job->id, notifyOnUpdatesFound: true);

        return response()->json(['data' => ['job_id' => $job->id]], Response::HTTP_ACCEPTED);
    }
}
