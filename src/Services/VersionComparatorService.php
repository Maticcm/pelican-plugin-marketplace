<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

/**
 * Plugin version strings in the wild are not reliably semver
 * (`1.2.3-SNAPSHOT`, `v2.0`, `Release 4`, `build-118`, ...), so this
 * service tries `version_compare()` first - which tolerates most
 * real-world formats including pre-release suffixes - and only falls
 * back to a naive numeric-segment comparison when that produces an
 * equal result but the raw strings actually differ, which is a strong
 * signal `version_compare()` couldn't parse them meaningfully.
 */
class VersionComparatorService
{
    /**
     * Returns 1 if $a is newer than $b, -1 if older, 0 if equal/unknown.
     */
    public function compare(?string $a, ?string $b): int
    {
        if ($a === null || $b === null) {
            return 0;
        }

        $a = trim($a);
        $b = trim($b);

        if ($a === $b) {
            return 0;
        }

        $normalizedA = $this->normalize($a);
        $normalizedB = $this->normalize($b);

        $result = version_compare($normalizedA, $normalizedB);

        if ($result !== 0) {
            return $result;
        }

        return $this->compareNumericSegments($normalizedA, $normalizedB);
    }

    public function isNewer(?string $candidate, ?string $current): bool
    {
        return $this->compare($candidate, $current) > 0;
    }

    private function normalize(string $version): string
    {
        // Strip common leading labels ("v", "Version ", "Release ") so
        // "v2.0.1" and "2.0.1" compare equal.
        $version = preg_replace('/^(v|ver|version|release)\.?\s*/i', '', $version) ?? $version;

        return trim($version);
    }

    private function compareNumericSegments(string $a, string $b): int
    {
        preg_match_all('/\d+/', $a, $matchesA);
        preg_match_all('/\d+/', $b, $matchesB);

        $segmentsA = array_map('intval', $matchesA[0]);
        $segmentsB = array_map('intval', $matchesB[0]);

        $length = max(count($segmentsA), count($segmentsB));

        for ($i = 0; $i < $length; $i++) {
            $valueA = $segmentsA[$i] ?? 0;
            $valueB = $segmentsB[$i] ?? 0;

            if ($valueA !== $valueB) {
                return $valueA <=> $valueB;
            }
        }

        return 0;
    }
}
