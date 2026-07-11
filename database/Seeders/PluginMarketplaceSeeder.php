<?php

namespace Database\Seeders;

use App\Services\Helpers\PluginService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs automatically on every `p:plugin:install`/`p:plugin:update` of
 * this plugin (Pelican auto-discovers and invokes a seeder named
 * `<PluginName>Seeder` under `database/Seeders/` - see
 * `App\Models\Plugin::getSeeder()` and
 * `App\Services\Helpers\PluginService::runPluginSeeder()`).
 *
 * Its only job is to make sure Tailwind actually generates the CSS
 * utility classes this plugin's Blade views use. The host panel's
 * Tailwind v4 build only scans paths it can find content in, and
 * `plugins/.gitignore` (`*` + `!.gitignore`) causes the default
 * scanner to treat the whole `plugins/` tree as out of scope - so
 * without this, most non-trivial utility classes used across this
 * plugin's views (hover states, tinted backgrounds, translucent
 * borders) silently compile to nothing, and the pages render
 * essentially unstyled. See docs/ARCHITECTURE.md.
 *
 * Uses an *absolute* filesystem path in the `@source` directive
 * rather than a path relative to app.css, deliberately: relative
 * `@source` resolution is confirmed to behave differently between
 * Tailwind's CLI and its `@tailwindcss/vite` plugin (see
 * https://github.com/tailwindlabs/tailwindcss/issues/18833), and two
 * different relative forms were both confirmed in production, via a
 * `max-w-[10rem]` canary utility class present in this plugin's views,
 * to compile to nothing under the Vite plugin used here. An absolute
 * path sidesteps that ambiguity entirely.
 */
class PluginMarketplaceSeeder extends Seeder
{
    /** Matches any @source line this seeder (in any prior version) may have added, so it can be normalized/replaced. */
    private const SOURCE_LINE_PATTERN = '/^@source\s+[\'"].*plugin-marketplace\/resources\/views\/\*\*\/\*\.blade\.php[\'"];\s*$/m';

    public function run(PluginService $pluginService): void
    {
        $this->registerTailwindSource($pluginService);
    }

    private function registerTailwindSource(PluginService $pluginService): void
    {
        $path = base_path('resources/css/app.css');

        if (!File::exists($path)) {
            return;
        }

        $directive = "@source '" . plugin_path('plugin-marketplace', 'resources/views') . "/**/*.blade.php';";

        $contents = File::get($path);

        if (str_contains($contents, $directive) && preg_match_all(self::SOURCE_LINE_PATTERN, $contents) === 1) {
            // Already patched with exactly this directive and nothing
            // stale left behind - nothing to do.
            return;
        }

        try {
            // Remove any previous attempt (this seeder has shipped more
            // than one @source form while the correct one was being
            // worked out) before inserting the current, known-good one.
            $withoutStaleLines = preg_replace(self::SOURCE_LINE_PATTERN, '', $contents) ?? $contents;
            $lines = array_values(array_filter(explode("\n", $withoutStaleLines), fn (string $line) => trim($line) !== ''));

            $insertAt = count($lines);
            foreach ($lines as $index => $line) {
                if (str_starts_with(trim($line), '@source') || str_starts_with(trim($line), '@import')) {
                    $insertAt = $index + 1;
                }
            }

            array_splice($lines, $insertAt, 0, [$directive]);

            File::put($path, implode("\n", $lines) . "\n");

            // The install flow already ran `yarn build` before seeders
            // run, using the *old* CSS. Rebuild once more now that the
            // @source directive is in place, so styling is correct
            // immediately after this install rather than only after
            // some unrelated future plugin install/update triggers the
            // next asset build.
            $pluginService->buildAssets();
        } catch (Throwable $exception) {
            Log::warning(
                "[plugin-marketplace] Could not register the Tailwind @source directive automatically in resources/css/app.css. "
                . "The plugin will still work, but some styling may be missing until this is added manually: $directive",
                ['exception' => $exception->getMessage()]
            );
        }
    }
}
