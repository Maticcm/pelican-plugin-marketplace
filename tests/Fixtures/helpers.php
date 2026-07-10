<?php

use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

if (!function_exists('makeMarketplacePlugin')) {
    /** @param array<string, mixed> $overrides */
    function makeMarketplacePlugin(array $overrides = []): MarketplacePluginData
    {
        return new MarketplacePluginData(
            repository: $overrides['repository'] ?? MarketplaceRepository::Modrinth,
            projectId: $overrides['projectId'] ?? 'abc123',
            slug: $overrides['slug'] ?? 'example-plugin',
            name: $overrides['name'] ?? 'Example Plugin',
            summary: $overrides['summary'] ?? 'An example plugin.',
            description: $overrides['description'] ?? null,
            iconUrl: $overrides['iconUrl'] ?? null,
            author: $overrides['author'] ?? 'Someone',
            authorUrl: $overrides['authorUrl'] ?? null,
            categories: $overrides['categories'] ?? [],
            downloads: $overrides['downloads'] ?? 100,
            rating: $overrides['rating'] ?? null,
            followers: $overrides['followers'] ?? null,
            latestVersion: $overrides['latestVersion'] ?? null,
            minecraftVersions: $overrides['minecraftVersions'] ?? ['1.21'],
            loaders: $overrides['loaders'] ?? ['paper'],
            sourceUrl: $overrides['sourceUrl'] ?? null,
            issuesUrl: $overrides['issuesUrl'] ?? null,
            wikiUrl: $overrides['wikiUrl'] ?? null,
            externalHomepageUrl: $overrides['externalHomepageUrl'] ?? null,
            gallery: $overrides['gallery'] ?? [],
            createdAt: $overrides['createdAt'] ?? null,
            updatedAt: $overrides['updatedAt'] ?? null,
            license: $overrides['license'] ?? null,
        );
    }
}
