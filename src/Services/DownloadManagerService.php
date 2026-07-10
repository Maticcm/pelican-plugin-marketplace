<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PelicanMarketplace\PluginMarketplace\Exceptions\DownloadTooLargeException;
use PelicanMarketplace\PluginMarketplace\Exceptions\MarketplaceException;

/**
 * Downloads a plugin jar from an upstream repository into memory,
 * enforcing the admin-configured maximum size and timeout before a
 * single byte is handed to the installer/updater.
 */
class DownloadManagerService
{
    public function __construct(
        private readonly MarketplaceSettingsService $settings,
        private readonly MarketplaceCacheService $cache,
    ) {}

    /**
     * @throws DownloadTooLargeException
     * @throws MarketplaceException
     */
    public function download(string $url): string
    {
        $maxBytes = $this->settings->maxDownloadSizeBytes();
        $timeout = $this->settings->downloadTimeoutSeconds();

        try {
            $response = Http::withHeaders(['User-Agent' => config('plugin-marketplace.user_agent')])
                ->timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new MarketplaceException("Could not reach download URL: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            throw new MarketplaceException("Download failed with HTTP status {$response->status()}.");
        }

        $contentLength = $response->header('Content-Length');
        if ($contentLength !== '' && (int) $contentLength > $maxBytes) {
            throw new DownloadTooLargeException(
                'The plugin file (' . $this->humanBytes((int) $contentLength) . ') exceeds the configured maximum download size of ' . $this->humanBytes($maxBytes) . '.'
            );
        }

        $body = $response->body();

        if (strlen($body) > $maxBytes) {
            throw new DownloadTooLargeException(
                'The downloaded plugin file (' . $this->humanBytes(strlen($body)) . ') exceeds the configured maximum download size of ' . $this->humanBytes($maxBytes) . '.'
            );
        }

        return $body;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = max($bytes, 0);
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, 1) . ' ' . $units[$unitIndex];
    }
}
