<?php

namespace PelicanMarketplace\PluginMarketplace\Services\Repositories\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait BuildsHttpClient
{
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => config('plugin-marketplace.user_agent'),
            ])
            ->timeout((int) config('plugin-marketplace.http.timeout', 10))
            ->connectTimeout((int) config('plugin-marketplace.http.connect_timeout', 5))
            ->retry(2, 250, throw: false);
    }

    abstract protected function baseUrl(): string;
}
