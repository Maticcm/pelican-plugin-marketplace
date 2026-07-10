<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Support\Arr;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\PluginHealthStatus;

/**
 * Flags plugins that are likely unsafe to depend on going forward:
 * nothing published in over a year, or explicitly known to be
 * deprecated/archived via the curated `known_replacements` config map.
 */
class PluginHealthService
{
    private const ABANDONED_AFTER_DAYS = 365;

    public function __construct(private readonly MarketplaceSettingsService $settings) {}

    public function status(MarketplacePluginData $plugin): PluginHealthStatus
    {
        if (!$this->settings->healthWarningsEnabled()) {
            return PluginHealthStatus::Healthy;
        }

        $known = $this->knownReplacement($plugin);
        if ($known !== null) {
            return PluginHealthStatus::Deprecated;
        }

        if ($plugin->updatedAt !== null && $plugin->updatedAt->diffInDays(now()) > self::ABANDONED_AFTER_DAYS) {
            return PluginHealthStatus::Abandoned;
        }

        return PluginHealthStatus::Healthy;
    }

    /** @return array{repository: MarketplaceRepository, slug: string}|null */
    public function knownReplacement(MarketplacePluginData $plugin): ?array
    {
        $map = config('plugin-marketplace.known_replacements', []);
        $entry = Arr::get($map, $plugin->key());

        if ($entry === null) {
            return null;
        }

        $repository = MarketplaceRepository::tryFrom(Arr::get($entry, 'repository', ''));
        if ($repository === null) {
            return null;
        }

        return ['repository' => $repository, 'slug' => Arr::get($entry, 'slug')];
    }

    public function warningMessage(MarketplacePluginData $plugin): ?string
    {
        return match ($this->status($plugin)) {
            PluginHealthStatus::Abandoned => 'No new version has been published in over a year. This plugin may be unmaintained.',
            PluginHealthStatus::Deprecated => 'This plugin is known to be deprecated. Consider using its suggested replacement instead.',
            PluginHealthStatus::Archived => 'This project has been archived by its author and will not receive further updates.',
            PluginHealthStatus::Healthy => null,
        };
    }
}
