<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use PelicanMarketplace\PluginMarketplace\Exceptions\InvalidJarException;
use ZipArchive;

/**
 * Validates that downloaded bytes are actually a well-formed plugin
 * jar before they are ever written to a server's filesystem. A jar is
 * just a zip file, so this checks the zip magic bytes, that the
 * archive opens cleanly, that it isn't a zip bomb, and that it
 * contains a Bukkit-family plugin manifest.
 */
class JarValidatorService
{
    /**
     * A generous but finite cap on how many bytes the *uncompressed*
     * contents of the archive may total, independent of the compressed
     * download size limit enforced by {@see DownloadManagerService}.
     * Guards against zip bombs (a tiny compressed file that expands to
     * gigabytes) without being restrictive for legitimate plugins,
     * which are rarely more than a few hundred MB uncompressed even
     * when they bundle large asset packs.
     */
    private const MAX_UNCOMPRESSED_BYTES = 2 * 1024 * 1024 * 1024;

    /** @throws InvalidJarException */
    public function validate(string $bytes, string $fileName): void
    {
        if ($bytes === '' || !str_starts_with($bytes, 'PK')) {
            throw new InvalidJarException("\"$fileName\" is not a valid jar/zip file.");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'pmvalidate');
        if ($tempPath === false) {
            throw new InvalidJarException('Could not allocate a temporary file to validate the download.');
        }

        try {
            file_put_contents($tempPath, $bytes);

            $zip = new ZipArchive();
            $openResult = $zip->open($tempPath, ZipArchive::CHECKCONS);

            if ($openResult !== true) {
                throw new InvalidJarException("\"$fileName\" could not be opened as a zip archive (error code $openResult).");
            }

            try {
                $this->assertSafeContents($zip, $fileName);
                $this->assertHasPluginManifest($zip, $fileName);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tempPath);
        }
    }

    /** @throws InvalidJarException */
    private function assertSafeContents(ZipArchive $zip, string $fileName): void
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = $stat['name'];

            // Reject path traversal / absolute paths inside the archive,
            // the same class of check the panel's own plugin importer
            // uses for extension zips.
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_contains($entryName, "\0")) {
                throw new InvalidJarException("\"$fileName\" contains an unsafe path (\"$entryName\") and was rejected.");
            }

            $totalUncompressed += $stat['size'];

            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new InvalidJarException("\"$fileName\" expands to an unreasonably large size when decompressed and was rejected as a possible zip bomb.");
            }
        }
    }

    /** @throws InvalidJarException */
    private function assertHasPluginManifest(ZipArchive $zip, string $fileName): void
    {
        $hasManifest = $zip->locateName('plugin.yml') !== false || $zip->locateName('paper-plugin.yml') !== false;

        if (!$hasManifest) {
            throw new InvalidJarException("\"$fileName\" does not contain a plugin.yml or paper-plugin.yml and does not look like a Bukkit/Spigot/Paper plugin.");
        }
    }
}
