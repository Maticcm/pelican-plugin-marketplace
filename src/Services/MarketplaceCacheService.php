<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper around the panel's configured cache store so every part of
 * the plugin (search results, plugin metadata, versions, icons,
 * categories, statistics) shares one prefix, one TTL source (the
 * admin-configurable "cache duration" setting) and one place to flush
 * everything from.
 */
class MarketplaceCacheService
{
    public function __construct(private readonly MarketplaceSettingsService $settings) {}

    private const PREFIX = 'plugin-marketplace';

    public function remember(string $key, Closure $callback, ?int $minutes = null): mixed
    {
        return Cache::remember($this->key($key), now()->addMinutes($minutes ?? $this->settings->cacheDurationMinutes()), $callback);
    }

    /**
     * Short-lived cache for things that change constantly (e.g. live
     * download counters) but are still worth debouncing across a burst
     * of requests.
     */
    public function rememberBriefly(string $key, Closure $callback, int $seconds = 30): mixed
    {
        return Cache::remember($this->key($key), now()->addSeconds($seconds), $callback);
    }

    public function forget(string $key): void
    {
        Cache::forget($this->key($key));
    }

    private function key(string $key): string
    {
        return self::PREFIX . '.' . $key;
    }
}
