<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use Symfony\Component\HttpFoundation\Response;

class JobController extends Controller
{
    public function __invoke(Request $request, PluginJob $job): JsonResponse
    {
        $user = $request->user();
        $allowed = $job->user_id === $user?->id
            || ($job->server !== null && $user?->can('plugins.view', $job->server));

        abort_unless($allowed, Response::HTTP_FORBIDDEN);

        return response()->json([
            'data' => [
                'id' => $job->id,
                'type' => $job->type->value,
                'status' => $job->status->value,
                'progress' => $job->progressPercent(),
                'plugin_name' => $job->plugin_name,
                'message' => $job->message,
                'meta' => $job->meta,
                'finished' => $job->status->isFinished(),
                'started_at' => $job->started_at?->toIso8601String(),
                'finished_at' => $job->finished_at?->toIso8601String(),
            ],
        ]);
    }
}
