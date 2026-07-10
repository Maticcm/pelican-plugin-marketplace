<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

use Illuminate\Support\Carbon;

final readonly class MarketplaceVersionData
{
    /**
     * @param  string[]  $minecraftVersions
     * @param  string[]  $loaders
     * @param  MarketplaceDependencyData[]  $dependencies
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $versionNumber,
        public ?string $changelog,
        public ?string $downloadUrl,
        public ?string $fileName,
        public ?int $fileSize,
        public array $minecraftVersions,
        public array $loaders,
        public array $dependencies,
        public ?Carbon $publishedAt,
        public string $channel = 'release',
        public ?string $javaVersion = null,
        public ?int $downloads = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version_number' => $this->versionNumber,
            'changelog' => $this->changelog,
            'download_url' => $this->downloadUrl,
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'minecraft_versions' => $this->minecraftVersions,
            'loaders' => $this->loaders,
            'dependencies' => array_map(fn (MarketplaceDependencyData $d) => $d->toArray(), $this->dependencies),
            'published_at' => $this->publishedAt?->toIso8601String(),
            'channel' => $this->channel,
            'java_version' => $this->javaVersion,
            'downloads' => $this->downloads,
        ];
    }

    public function supportsMinecraftVersion(string $version): bool
    {
        if ($this->minecraftVersions === []) {
            return true;
        }

        return in_array($version, $this->minecraftVersions, true);
    }

    public function supportsLoader(string $loader): bool
    {
        if ($this->loaders === []) {
            return true;
        }

        return in_array(strtolower($loader), array_map('strtolower', $this->loaders), true);
    }
}
