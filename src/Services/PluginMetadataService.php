<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * Extracts Bukkit/Spigot/Paper plugin metadata (name, version, authors,
 * description, main class, api-version, dependencies) from a jar file's
 * `plugin.yml` (or Paper's `paper-plugin.yml`), so the Installed Plugins
 * page never has to guess anything from a bare filename.
 */
class PluginMetadataService
{
    /**
     * @return array{name: string, version: ?string, authors: string[], description: ?string, main: ?string, api_version: ?string, depend: string[], softdepend: string[]}|null
     *         null if the jar does not contain a readable manifest at all.
     */
    public function extractFromJarBytes(string $bytes, string $fallbackName): ?array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'pmjar');
        if ($tempPath === false) {
            return null;
        }

        try {
            file_put_contents($tempPath, $bytes);

            return $this->extractFromJarPath($tempPath, $fallbackName);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @return array{name: string, version: ?string, authors: string[], description: ?string, main: ?string, api_version: ?string, depend: string[], softdepend: string[]}|null
     */
    public function extractFromJarPath(string $path, string $fallbackName): ?array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $manifest = $zip->getFromName('plugin.yml') ?: $zip->getFromName('paper-plugin.yml');

            if ($manifest === false) {
                return null;
            }

            return $this->parseManifest((string) $manifest, $fallbackName);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{name: string, version: ?string, authors: string[], description: ?string, main: ?string, api_version: ?string, depend: string[], softdepend: string[]}|null
     */
    public function parseManifest(string $yaml, string $fallbackName): ?array
    {
        try {
            $data = Yaml::parse($yaml);
        } catch (ParseException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $authors = Arr::get($data, 'authors', []);
        if (!is_array($authors)) {
            $authors = [$authors];
        }

        $author = Arr::get($data, 'author');
        if ($author !== null && !in_array($author, $authors, true)) {
            $authors[] = $author;
        }

        return [
            'name' => (string) Arr::get($data, 'name', $fallbackName),
            'version' => Arr::get($data, 'version') !== null ? (string) Arr::get($data, 'version') : null,
            'authors' => array_values(array_map('strval', $authors)),
            'description' => Arr::get($data, 'description'),
            'main' => Arr::get($data, 'main'),
            'api_version' => $this->normalizeApiVersion(Arr::get($data, 'api-version')),
            'depend' => $this->normalizeStringList(Arr::get($data, 'depend', [])),
            'softdepend' => $this->normalizeStringList(Arr::get($data, 'softdepend', [])),
        ];
    }

    /** @return string[] */
    private function normalizeStringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return [(string) $value];
        }

        return array_values(array_map('strval', $value));
    }

    private function normalizeApiVersion(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Some plugin.yml files declare api-version as a YAML list
        // (e.g. for multi-version support); we only need one label.
        if (is_array($value)) {
            return (string) Arr::first($value);
        }

        return (string) $value;
    }
}
