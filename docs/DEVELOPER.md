# Developer guide

## Honesty about what has and hasn't been verified

This plugin was built inside a read-only checkout of the Pelican panel source, without a running Pelican instance, Wings daemon, or PHP/Composer/Node toolchain available in the build environment. Concretely, that means:

**Verified directly (either by reading the exact source of the mechanism being relied on, or by hitting the real live API):**
- The plugin-loading mechanism, panel/Filament registration, permission system, Wings file-repository API shapes, Filament page/resource/action patterns - all confirmed by reading the actual Pelican source (not guessed from general Filament/Laravel knowledge).
- Every field name and endpoint used by `HangarClient`/`ModrinthClient`/`SpigetClient` - confirmed by making real requests against `hangar.papermc.io`, `api.modrinth.com` and `api.spiget.org` and inspecting the actual JSON returned (see the fixtures in `tests/Feature/*ClientTest.php`, which are trimmed copies of genuine responses).
- Core business logic (version comparison, plugin.yml parsing, jar validation, dependency resolution, compatibility checks, settings persistence) - covered by this plugin's own automated test suite, see below.

**Not verified, because it requires a real Pelican + Wings environment:**
- That the Filament pages actually render correctly in the browser (dark mode, responsiveness, exact Blade component prop names on this specific Filament version).
- That installing/updating/uninstalling a plugin actually works end-to-end against a real Wings daemon.
- That the permission registration (`Role::registerCustomPermissions()`, `Subuser::registerCustomPermissions()`) renders correctly in the Admin Roles / Subusers UI.
- That the scheduled update-check task actually fires (needs the panel's cron entry to be configured, same as any Laravel scheduled task).
- Every Filament API call in `src/Filament/**` (e.g. `Action::make()->schema()->action()`, `Livewire\Attributes\Url`, `<x-filament::button tag="a">`) was written to match patterns confirmed elsewhere in this exact codebase wherever possible, but a handful of finer details (the very newest Filament v5 forms/schemas API surface) could not be checked against the installed package version, since no `vendor/` directory was available to inspect.

If you're reading this because you're about to deploy this plugin: **run the manual QA checklist below on a real (ideally non-production) Pelican instance before trusting it with a production server.**

## Running the automated test suite

This plugin ships its own test suite (Pest + Orchestra Testbench), separate from the host panel's - Pelican deliberately never loads plugin code while running its own tests (`PluginService::loadPlugins()` returns early when `runningUnitTests()`), so a plugin has to bring its own harness.

```bash
cd plugin-marketplace
composer install
vendor/bin/pest
```

You may need to adjust the `orchestra/testbench` version constraint in this plugin's `composer.json` to match your exact installed Laravel version (Testbench major versions track Laravel major versions).

What's covered:
- `tests/Unit/` - pure logic: version comparison, plugin.yml parsing, jar validation (path traversal, zip bombs, missing manifest), compatibility warnings, dependency resolution, search-result merging/sorting, plugin health classification, enum helpers.
- `tests/Feature/` - the three repository clients against `Http::fake()` with real captured API response shapes, and the settings service against a real (in-memory sqlite) database.

What's **not** covered by this suite, and why: anything that type-hints `App\Models\Server`, `App\Models\User`, or Filament's Notification/Action system directly - those are real Pelican application classes that don't exist in an isolated Testbench app. That's most of `src/Jobs/`, `src/Filament/**`, and the install/update/scan/removal orchestration services. This is a deliberate, honest boundary rather than a gap papered over with fragile fake stand-in classes for `App\Models\Server` etc. - see the manual QA checklist below for how to actually exercise that code.

## Manual QA checklist

Work through this on a real Pelican install after following [docs/INSTALLATION.md](INSTALLATION.md). Boxes are for your own tracking, not present in any automated report.

**Navigation & access**
- [ ] "Plugin Marketplace" nav group appears in the Server panel with Marketplace / Installed Plugins / Plugin Updates.
- [ ] "Plugin Marketplace" nav group appears in the Admin panel with Settings.
- [ ] A user/subuser with no `plugins.*` permission granted cannot see any of the above.
- [ ] A user without `settings plugins` cannot open/save Admin Settings.

**Settings**
- [ ] Settings form loads with the seeded defaults, saves successfully, and a repository toggle actually stops that repository's results from appearing in search.

**Search / Marketplace page**
- [ ] Searching returns results from Hangar and Modrinth; a search with no `query` still returns a "popular" default listing.
- [ ] SpigotMC results show a "Manual download" badge and never an Install button.
- [ ] Category, Minecraft-version, and repository filters narrow results correctly.
- [ ] Favoriting/unfavoriting a card persists (re-check "Favorites only").
- [ ] Recently-viewed strip updates after visiting a Plugin Details page.
- [ ] Pagination (Previous/Next) works.

**Plugin Details**
- [ ] Icon, description (rendered safely, no raw script execution even if you find a plugin with unusual formatting in its description), gallery, links, release history all render.
- [ ] Install action opens, lets you pick a version, shows a compatibility warning when you deliberately pick an obviously-incompatible Minecraft version.
- [ ] Installing a plugin with a resolvable required dependency (e.g. a plugin depending on Vault/LuckPerms) also queues that dependency.

**Installed Plugins**
- [ ] "Scan now" finds jars uploaded manually via SFTP/the file manager (not just marketplace-installed ones), with correct name/version/author pulled from `plugin.yml`.
- [ ] Enable/Disable actually renames the jar (`Name.jar` ↔ `Name.jar.disabled`) - confirm in the server's file manager.
- [ ] Uninstall removes the jar from `/plugins` and the row from the table.
- [ ] Update Available badge appears after a scan when a newer version exists upstream.

**Plugin Updates**
- [ ] Update All queues every outdated plugin and reports success/failure per plugin.
- [ ] A single Update shows the changelog before you confirm.
- [ ] With backups enabled, check `/.pelican-plugin-marketplace/backups/` on the server after an update - a timestamped `.bak` copy of the previous jar should be there, and it should **not** appear in the Installed Plugins list (it's outside `/plugins`, so Bukkit never loads it).
- [ ] Force an update to fail (e.g. temporarily point `config('plugin-marketplace.downloads.max_size')` at something tiny) and confirm the original jar is restored (rollback).

**Jobs / progress**
- [ ] Every install/update/uninstall/scan shows a panel notification on completion (success and failure paths).
- [ ] `plugin_marketplace_jobs` rows are created and reach a terminal `status`.

**Permissions**
- [ ] A subuser with only `plugins.view` can browse but every Install/Update/Uninstall/Enable-Disable action is hidden or 403s.
- [ ] The server owner always has full access regardless of subuser permissions.

**API** (see [docs/API.md](API.md) for the full list)
- [ ] `GET /plugin-marketplace/api/search` returns JSON matching the documented shape.
- [ ] Hitting any server-scoped endpoint for a server you don't have access to returns 403, not the data.

## Extending the plugin

- **Add a repository**: implement `Contracts\RepositoryClient`, register it in `PluginMarketplaceProvider::register()`'s `RepositoryClientManager` binding, add it to `Enums\MarketplaceRepository`.
- **Add a known dependency**: add an entry to `known_dependencies` in `config/plugin-marketplace.php` (verify the slug against the real API first - see the comment in that file for why this matters).
- **Add an admin/subuser permission verb**: extend the arrays passed to `Role::registerCustomPermissions()`/`Subuser::registerCustomPermissions()` in the provider, then gate whatever new action needs it the same way the existing ones are (`user()->can('verb plugins')` for admin, `user()->can('plugins.verb', $server)` for subuser).
