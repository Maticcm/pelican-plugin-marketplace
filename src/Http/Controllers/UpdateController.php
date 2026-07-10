<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Http\Requests\UpdatePluginRequest;
use PelicanMarketplace\PluginMarketplace\Jobs\BulkUpdatePluginsJob;
use PelicanMarketplace\PluginMarketplace\Jobs\UpdatePluginJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use Symfony\Component\HttpFoundation\Response;

class UpdateController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.view', $server), Response::HTTP_FORBIDDEN);

        $updates = InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->where('update_available', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $updates]);
    }

    public function update(UpdatePluginRequest $request, Server $server, InstalledPlugin $installedPlugin): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.update', $server), Response::HTTP_FORBIDDEN);
        abort_unless($installedPlugin->server_id === $server->id, Response::HTTP_NOT_FOUND);

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'type' => InstallJobType::Update,
            'plugin_name' => $installedPlugin->name,
        ]);

        UpdatePluginJob::dispatch(
            $request->user(),
            $server,
            $installedPlugin->id,
            $request->validated('version_id'),
            $job->id,
        );

        return response()->json(['data' => ['job_id' => $job->id]], Response::HTTP_ACCEPTED);
    }

    public function bulk(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.update', $server), Response::HTTP_FORBIDDEN);

        $ids = $request->validate([
            'installed_plugin_ids' => ['sometimes', 'array'],
            'installed_plugin_ids.*' => ['integer'],
        ])['installed_plugin_ids'] ?? null;

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'type' => InstallJobType::Update,
            'plugin_name' => 'Bulk update',
        ]);

        BulkUpdatePluginsJob::dispatch($request->user(), $server, $ids, $job->id);

        return response()->json(['data' => ['job_id' => $job->id]], Response::HTTP_ACCEPTED);
    }
}
