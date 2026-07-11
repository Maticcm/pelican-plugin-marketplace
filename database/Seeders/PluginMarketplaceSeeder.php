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
 */
class PluginMarketplaceSeeder extends Seeder
{
    // Deliberately the fully-qualified path for this one plugin, not a
    // `plugins/*/...` wildcard: confirmed in production that Tailwind's
    // @source glob engine does not resolve a mid-path wildcard the same
    // way the host's own working example does (which only wildcards at
    // the very end, `**/*.blade.php`) - a canary utility class
    // (`max-w-[10rem]`) present in this plugin's views had zero matches
    // in the compiled CSS with the wildcard form, and was picked up
    // correctly once switched to this explicit path.
    private const SOURCE_DIRECTIVE = "@source '../../plugins/plugin-marketplace/resources/views/**/*.blade.php';";

    // An earlier version of this seeder inserted this mid-path-wildcard
    // form, which turned out not to work (see the comment above) - strip
    // it out if a prior run left it behind, so app.css doesn't end up
    // with both a dead line and the working one.
    private const STALE_SOURCE_DIRECTIVE = "@source '../../plugins/*/resources/views/**/*.blade.php';";

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

        $contents = File::get($path);
        $hasCorrectDirective = str_contains($contents, self::SOURCE_DIRECTIVE);
        $hasStaleDirective = str_contains($contents, self::STALE_SOURCE_DIRECTIVE);

        if ($hasCorrectDirective && !$hasStaleDirective) {
            return;
        }

        try {
            $lines = explode("\n", $contents);

            if ($hasStaleDirective) {
                $lines = array_values(array_filter($lines, fn (string $line) => trim($line) !== self::STALE_SOURCE_DIRECTIVE));
            }

            if (!$hasCorrectDirective) {
                $insertAt = count($lines);

                foreach ($lines as $index => $line) {
                    if (str_starts_with(trim($line), '@source') || str_starts_with(trim($line), '@import')) {
                        $insertAt = $index + 1;
                    }
                }

                array_splice($lines, $insertAt, 0, [self::SOURCE_DIRECTIVE]);
            }

            File::put($path, implode("\n", $lines));

            // The install flow already ran `yarn build` before seeders
            // run, using the *old* CSS. Rebuild once more now that the
            // @source directive is in place, so styling is correct
            // immediately after this install rather than only after
            // some unrelated future plugin install/update triggers the
            // next asset build.
            $pluginService->buildAssets();
        } catch (Throwable $exception) {
            Log::warning(
                '[plugin-marketplace] Could not register the Tailwind @source directive automatically in resources/css/app.css. '
                . 'The plugin will still work, but some styling may be missing until this is added manually: '
                . self::SOURCE_DIRECTIVE,
                ['exception' => $exception->getMessage()]
            );
        }
    }
}
