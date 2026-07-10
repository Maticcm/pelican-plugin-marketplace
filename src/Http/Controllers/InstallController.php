<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Http\Requests\InstallPluginRequest;
use PelicanMarketplace\PluginMarketplace\Jobs\InstallPluginJob;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use Symfony\Component\HttpFoundation\Response;

class InstallController extends Controller
{
    public function __invoke(InstallPluginRequest $request, Server $server): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.install', $server), Response::HTTP_FORBIDDEN);

        $data = $request->validated();

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'type' => InstallJobType::Install,
            'repository' => $data['repository'],
            'project_id' => $data['project_id'],
        ]);

        InstallPluginJob::dispatch(
            $request->user(),
            $server,
            $data['repository'],
            $data['project_id'],
            $data['version_id'],
            (bool) ($data['overwrite'] ?? false),
            $job->id,
        );

        return response()->json(['data' => ['job_id' => $job->id]], Response::HTTP_ACCEPTED);
    }
}
