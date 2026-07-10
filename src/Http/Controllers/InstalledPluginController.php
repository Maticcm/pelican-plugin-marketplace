<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Jobs\UninstallPluginJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Services\PluginRemovalService;
use Symfony\Component\HttpFoundation\Response;

class InstalledPluginController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.view', $server), Response::HTTP_FORBIDDEN);

        $installedPlugins = InstalledPlugin::query()
            ->where('server_id', $server->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $installedPlugins]);
    }

    public function toggle(Request $request, Server $server, InstalledPlugin $installedPlugin, PluginRemovalService $removal): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.update', $server), Response::HTTP_FORBIDDEN);
        $this->assertBelongsToServer($server, $installedPlugin);

        $installedPlugin = $removal->setEnabled($server, $installedPlugin, !$installedPlugin->enabled);

        return response()->json(['data' => $installedPlugin]);
    }

    public function destroy(Request $request, Server $server, InstalledPlugin $installedPlugin): JsonResponse
    {
        abort_unless($request->user()?->can('plugins.delete', $server), Response::HTTP_FORBIDDEN);
        $this->assertBelongsToServer($server, $installedPlugin);

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'type' => InstallJobType::Uninstall,
            'plugin_name' => $installedPlugin->name,
        ]);

        UninstallPluginJob::dispatch($request->user(), $server, $installedPlugin->id, $job->id);

        return response()->json(['data' => ['job_id' => $job->id]], Response::HTTP_ACCEPTED);
    }

    private function assertBelongsToServer(Server $server, InstalledPlugin $installedPlugin): void
    {
        abort_unless($installedPlugin->server_id === $server->id, Response::HTTP_NOT_FOUND);
    }
}
