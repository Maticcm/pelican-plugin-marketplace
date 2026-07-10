<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Support\Collection;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;

/**
 * Produces human-readable compatibility warnings before an install or
 * update goes ahead. This never blocks an action by itself - the
 * calling Filament page/job decides whether a warning requires
 * confirmation - it only classifies the situation.
 */
class CompatibilityCheckerService
{
    /**
     * @return array<int, array{level: string, message: string}>
     */
    public function check(MarketplaceVersionData $version, ?string $minecraftVersion, Collection $installedPlugins, string $incomingFileName, ?string $incomingName = null): array
    {
        $warnings = [];

        if ($minecraftVersion !== null && !$version->supportsMinecraftVersion($minecraftVersion)) {
            $warnings[] = [
                'level' => 'warning',
                'message' => "This version does not explicitly list Minecraft $minecraftVersion as supported (supports: " . (implode(', ', $version->minecraftVersions) ?: 'unspecified') . '). It may still work, but proceed with caution.',
            ];
        }

        $supportedLoaders = config('plugin-marketplace.supported_loaders', ['bukkit', 'spigot', 'paper', 'purpur', 'folia']);
        if ($version->loaders !== [] && array_intersect(array_map('strtolower', $version->loaders), $supportedLoaders) === []) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'This version does not declare support for any Bukkit-family server software (Bukkit/Spigot/Paper/Purpur/Folia).',
            ];
        }

        $duplicate = $installedPlugins->firstWhere('file_name', $incomingFileName);
        if ($duplicate !== null) {
            $warnings[] = [
                'level' => 'info',
                'message' => "A plugin file named \"$incomingFileName\" is already installed (version {$duplicate->version}). Installing will overwrite it.",
            ];
        }

        $conflicts = $this->detectNameConflicts($installedPlugins, $incomingFileName, $incomingName, excludeFileName: $duplicate?->file_name);
        foreach ($conflicts as $conflict) {
            $warnings[] = [
                'level' => 'warning',
                'message' => "\"{$conflict->name}\" ({$conflict->file_name}) is already installed and may provide overlapping functionality.",
            ];
        }

        return $warnings;
    }

    /**
     * Detects plugins that are very likely the *same* plugin as the one
     * being installed, even when the jar filename differs (e.g.
     * `EssentialsX-2.20.1.jar` already installed vs. an incoming
     * `EssentialsX-2.21.0.jar`) - matched by comparing both the
     * declared plugin.yml `name` and the filename with any trailing
     * version-number-like suffix stripped off.
     */
    private function detectNameConflicts(Collection $installedPlugins, string $incomingFileName, ?string $incomingName, ?string $excludeFileName): Collection
    {
        $incomingBaseName = $this->normalize(pathinfo($incomingFileName, PATHINFO_FILENAME));
        $incomingDisplayName = $incomingName !== null ? $this->normalize($incomingName) : null;

        return $installedPlugins->filter(function (InstalledPlugin $plugin) use ($incomingFileName, $incomingBaseName, $incomingDisplayName, $excludeFileName) {
            if ($plugin->file_name === $incomingFileName || $plugin->file_name === $excludeFileName) {
                return false;
            }

            $installedBaseName = $this->normalize(pathinfo($plugin->file_name, PATHINFO_FILENAME));
            $installedDisplayName = $this->normalize($plugin->name);

            return $installedBaseName === $incomingBaseName
                || $installedDisplayName === $incomingBaseName
                || ($incomingDisplayName !== null && ($installedDisplayName === $incomingDisplayName || $installedBaseName === $incomingDisplayName));
        });
    }

    /**
     * Lower-cases and strips a trailing version-number-like suffix
     * (`-2.21.0`, `_v1.4`, `2.0`, ...) so "EssentialsX-2.20.1" and
     * "EssentialsX-2.21.0" normalize to the same "essentialsx".
     */
    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[\s_-]*v?\d+(\.\d+)*[a-z]?$/', '', $value) ?? $value;

        return trim($value, " _-\t\n\r\0\x0B");
    }
}
