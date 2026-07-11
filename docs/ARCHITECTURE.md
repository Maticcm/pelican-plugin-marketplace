# Architecture

## Directory layout

```
plugin-marketplace/
├── plugin.json                    Pelican plugin manifest
├── composer.json                  ONLY for this plugin's own standalone test suite (see docs/DEVELOPER.md) - not
│                                   used by the host panel, which discovers PHP classes via plugin.json's
│                                   `namespace`/`class` fields and PluginService's runtime PSR-4 registration.
├── config/plugin-marketplace.php  Deployment-level config (env-overridable); admin-editable settings live in the DB instead - see docs/CONFIGURATION.md.
├── database/migrations/           5 tables: installed_plugins, favorites, recently_viewed, settings, jobs.
├── lang/en/                       marketplace.php (UI strings) + permissions.php (subuser permission-picker labels).
├── resources/views/filament/      Blade views for the 2 custom Pages (Marketplace, PluginDetails, MarketplaceSettings).
├── routes/api.php                 This plugin's own small HTTP API - see docs/API.md.
├── src/
│   ├── PluginMarketplacePlugin.php   Filament\Contracts\Plugin entry point - registers resources/pages per panel.
│   ├── Providers/
│   │   ├── PluginMarketplaceProvider.php        Container bindings, permissions, policy, scheduled task.
│   │   └── PluginMarketplaceRoutesProvider.php   This plugin's routes/api.php, per Pelican's documented convention
│   │                                             of registering plugin routes via a dedicated RouteServiceProvider.
│   ├── Contracts/                    RepositoryClient, DaemonFileRepositoryFactory interfaces.
│   ├── Data/                         Immutable DTOs unifying the 3 repositories' API shapes.
│   ├── Enums/                        MarketplaceRepository, MarketplaceSort, MarketplaceCategory, PluginHealthStatus, InstallJobStatus/Type.
│   ├── Models/                       InstalledPlugin, Favorite, RecentlyViewed, MarketplaceSetting, PluginJob.
│   ├── Services/                     All business logic - see "Services" below.
│   │   ├── Repositories/                HangarClient, ModrinthClient, SpigetClient.
│   │   └── Daemon/                      PelicanDaemonFileRepositoryFactory (the real Wings-backed implementation).
│   ├── Jobs/                         Install/Update/Uninstall/BulkUpdate/Scan - all queued.
│   ├── Filament/                     Admin + Server panel Resources/Pages.
│   ├── Http/                         Controllers/Requests for this plugin's own API.
│   └── Policies/                     InstalledPluginPolicy (Filament resource-level authorization).
└── tests/                         This plugin's own Orchestra Testbench + Pest suite - see docs/DEVELOPER.md.
```

## How this plugs into Pelican

This plugin follows Pelican's plugin conventions exactly (verified by reading `app/Services/Helpers/PluginService.php`, `app/Models/Plugin.php`, and the existing built-in `App\Filament\Admin\Resources\Plugins\PluginResource` before writing any code):

- **Discovery**: `plugin.json` at the root, with `namespace`/`class` pointing at `PluginMarketplacePlugin`. Pelican's `Plugin` (Sushi) model reads this file; `PluginService::loadPlugins()` dynamically registers `src/` as PSR-4 root `PelicanMarketplace\PluginMarketplace\` at runtime via Composer's `ClassLoader` - no `composer install` inside the plugin folder is needed for the plugin to work inside the host panel (the `composer.json` in this repo is exclusively for running this plugin's own isolated test suite - see docs/DEVELOPER.md).
- **Service providers**: every file in `src/Providers/*.php` is auto-discovered and registered by `PluginService`, exactly like every other Pelican plugin. This plugin ships two: `PluginMarketplaceProvider` (container bindings, permissions, the policy binding, the scheduled update-check task) and `PluginMarketplaceRoutesProvider` (this plugin's HTTP API routes). Routes get their own dedicated provider - extending `Illuminate\Foundation\Support\Providers\RouteServiceProvider` and registering via `$this->routes(fn () => ...)` - because that's what Pelican's plugin docs specify, and what correctly integrates with Laravel's route caching; a plain `Route::group()` call from an arbitrary provider's `boot()` doesn't.
- **Filament registration**: `PluginMarketplacePlugin::register(Panel $panel)` is called once per panel by `PluginService::loadPanelPlugins()` (from inside each `*PanelProvider`), which is the same injection point the panel's own third-party Filament plugins (e.g. the log viewer) use.
- **Permissions**: registered via `Role::registerCustomPermissions()` / `Role::registerCustomModelIcon()` / `Subuser::registerCustomPermissions()` - the exact same extensibility hooks the panel's own code documents for this purpose, called from `PluginMarketplaceProvider::register()` (not `boot()`), matching the documented convention.
- **Migrations**: `database/migrations/` is auto-discovered and run by `PluginService::runPluginMigrations()` during install.

## Why Settings lives in the Admin panel

The task list groups "Marketplace / Installed Plugins / Plugin Details / Plugin Updates / Settings" under one navigation entry. Filament's multi-panel architecture (Admin, Server, App - each with an entirely separate URL prefix and sidebar) makes a single navigation group spanning two panels structurally impossible, so a decision had to be made:

- **Marketplace, Installed Plugins, Plugin Updates** (and Plugin Details, reached via a card/row rather than the sidebar) are inherently *per-server* - you're always installing a plugin onto one specific game server - so they live in the **Server** panel, tenant-scoped exactly like the built-in Files/Backups/Databases resources.
- **Settings** (which repositories are reachable, cache duration, download limits, ...) is an *instance-wide operator* concern, not a per-server one - identical in spirit to every other entry on the panel's own Admin → Settings page. It lives in the **Admin** panel, gated by the admin-only `settings plugins` permission (deliberately **not** a subuser permission - see docs/CONFIGURATION.md).

Both panels register a **"Plugin Marketplace"** navigation group (`trans('plugin-marketplace::marketplace.nav_group')`), so from a user's perspective the feature still presents as one coherent "Plugin Marketplace" area - it just, correctly, has an admin-facing configuration screen and a server-facing usage screen, same as the rest of the panel.

## Services

Kept deliberately small and single-purpose (SOLID's single-responsibility principle), each constructor-injected rather than resolved via the `app()` helper wherever practical, so they're independently testable:

| Service | Responsibility |
|---|---|
| `RepositoryClientManager` | Registry of the 3 `RepositoryClient` implementations. |
| `HangarClient` / `ModrinthClient` / `SpigetClient` | One per upstream API, implementing the shared `RepositoryClient` contract. Every response field mapping in these was verified against the **live** APIs (not just documentation) while building this plugin. |
| `MarketplaceSearchService` | Fans a query out to every enabled/requested repository client and merges + re-sorts the results. |
| `MarketplaceCacheService` | Thin wrapper around the panel's cache store, single TTL source (the admin-configured cache duration). |
| `MarketplaceSettingsService` | Single point of access to the admin-editable settings row. |
| `DownloadManagerService` | Downloads a jar into memory, enforcing the configured max size/timeout before installer/updater ever sees the bytes. |
| `JarValidatorService` | Validates a downloaded jar: zip magic bytes, opens cleanly, no path-traversal entries, bounded uncompressed size (zip-bomb guard), and contains a `plugin.yml`/`paper-plugin.yml`. |
| `PluginMetadataService` | Parses `plugin.yml`/`paper-plugin.yml` (via `symfony/yaml`, already a Pelican dependency) into structured metadata. |
| `PluginScannerService` | Lists a server's `/plugins` directory over Wings, reconciles it against the `installed_plugins` table. |
| `PluginInstallerService` / `PluginUpdaterService` / `PluginRemovalService` | Download+validate+write, backup+replace+rollback, and delete/rename, respectively - all via Wings, never a local filesystem call. |
| `PluginUpdateCheckerService` | Compares marketplace-sourced installed plugins against their repository's latest version. |
| `CompatibilityCheckerService` | Minecraft-version/loader/duplicate/conflict warnings before an install proceeds. |
| `DependencyResolverService` | Resolves a version's declared dependencies against what's installed + the curated `known_dependencies` map. |
| `VersionComparatorService` | Tolerant version-string comparison (`version_compare()` first, numeric-segment fallback for non-semver strings). |
| `PluginHealthService` | Abandoned/deprecated/archived classification. |
| `FavoritesService` / `RecentPluginsService` | Per-user favorites and recently-viewed, with automatic pruning. |
| `MarketplaceNotificationService` | One place responsible for user-facing notifications (Filament database notifications), so every job/controller reports outcomes consistently. |

### Wings file access is behind an interface

Every service that needs to read/write a server's filesystem depends on `Contracts\DaemonFileRepositoryFactory` rather than instantiating `App\Repositories\Daemon\DaemonFileRepository` directly. This is the one place this plugin diverges from "just do it inline like the panel's own code does" - deliberately, so `PluginInstallerService`/`PluginUpdaterService`/`PluginRemovalService`/`PluginScannerService` can be unit-tested against a mock instead of a real (or even fake) Wings daemon. `PelicanDaemonFileRepositoryFactory` (bound in the provider) is the real implementation, and mirrors exactly the pattern the panel's own `App\Models\File::getRows()` uses.

## Dependency resolution

When you click Install, the selected version's declared dependencies are resolved in this order:

1. **Already installed?** Matched by plugin.yml `name` against what's already on the server (case-insensitive) → marked satisfied, nothing happens.
2. **Repository-native?** Hangar's `pluginDependencies` sometimes includes a `projectId` for a same-repository dependency; Modrinth's `dependencies` always includes a `project_id`. These resolve directly.
3. **Curated fallback**: for a dependency that's only known by name (a plain plugin.yml `depend:` entry, or a Hangar dependency pointing at an `externalUrl` instead of a `projectId` - e.g. Vault, which has never been published to Hangar or Modrinth by its author), `config('plugin-marketplace.known_dependencies')` is checked.

If any **required** dependency remains unresolved after all three steps, installation still proceeds (a plugin author may have just been wrong that it's required, or the panel operator may not want blocked installs) but the notification calls it out by name so the operator can install it manually. If dependency auto-install is enabled (the default), every dependency that *was* resolved gets queued for install in the same click - deliberately **not** recursive into that dependency's own dependencies, to avoid an unbounded/cyclical install chain from one click.

## Jobs & queues

Every install/update/uninstall/scan runs as a queued job (`ShouldQueue` + `ShouldBeUnique`, keyed per server+plugin so a double-click can't queue the same operation twice) - never inline on the HTTP request, matching the panel's own `App\Jobs\Plugin\*` jobs exactly. Progress is tracked in the `plugin_marketplace_jobs` table (`PluginJob` model) and surfaced to the UI by simply re-querying that row (no websocket/broadcast channel was added - Livewire's own polling, or a manual refresh, is enough for a background operation that finishes in seconds to a couple of minutes). A separate hourly scheduled task (registered in the provider, not a job) re-checks every server with marketplace-sourced plugins for updates, independent of anyone opening the panel - see docs/CONFIGURATION.md's "Automatic update checks" setting.

## Security

- **Path traversal**: jar filenames from an upstream API are reduced to a safe basename before ever being used in a Wings file path (`PluginInstallerService::safeFileName()`); the jar's own zip contents are checked for `..`/absolute-path entries before anything is trusted (`JarValidatorService`).
- **Zip bombs**: uncompressed size is bounded during validation, independent of the compressed download-size cap.
- **XSS**: plugin descriptions come from three different third-party APIs (two Markdown, one raw HTML decoded from base64). None are rendered as trusted HTML - Markdown is converted via Laravel's `Str::markdown()` (raw HTML escaped by default) and the result is further passed through `strip_tags()` with a conservative allow-list that deliberately excludes `<a>`/`<img>` (since `strip_tags()` only filters tag *names*, not attributes, so allowing either would let an `href="javascript:..."` or `onerror="..."` payload straight through untouched).
- **Download limits**: every download is capped (admin-configurable), checked both via `Content-Length` and actual byte count.
- **Overwrite protection**: installing over an existing file requires an explicit `overwrite` flag; the API/UI always asks first.
- **Every request re-checks authorization**: this plugin's HTTP API (`routes/api.php`) is a *real* surface independent of the Filament UI, so every controller re-validates the `plugins.*` permission itself rather than trusting that "the UI already checked" - see docs/API.md.

## CSS/Tailwind note

This plugin's Blade views live under `plugins/plugin-marketplace/resources/views/`, outside the host panel's own `resources/` tree. The host's `vite.config.js` already globs `plugins/*/resources/{css,js}/**/*` as build *inputs*, but Tailwind v4's automatic content-detection skips scanning `plugins/*/resources/views/**/*.blade.php` for class usage - `plugins/.gitignore` (`*` + `!.gitignore`) causes Tailwind's default scanner to treat the whole `plugins/` tree as out of scope. This was confirmed in production, not just a theoretical risk: most non-trivial utility classes this plugin's views used (hover states, tinted backgrounds, translucent borders) compiled to nothing, and the pages rendered essentially unstyled.

**Fix**: `database/Seeders/PluginMarketplaceSeeder.php` runs automatically on every `p:plugin:install`/`p:plugin:update` (Pelican's plugin system auto-invokes a seeder named `<PluginName>Seeder` - see `App\Models\Plugin::getSeeder()`). It idempotently appends an `@source` directive to the host's `resources/css/app.css`, mirroring the pattern already used there for a vendor package's views, then triggers one more `yarn build` (the install flow's own build already ran *before* seeders, using the old CSS, so this second build is what actually picks up the new directive). If the host file is missing or unwritable, it logs a warning and leaves the plugin otherwise fully functional rather than failing the install.

A couple of things are still hand-written CSS rather than Tailwind utilities, kept even after the `@source` fix since they have zero build-pipeline dependency either way:
- `@tailwindcss/typography`'s `.prose` classes are avoided in favor of a small `<style>` block scoped under `.pm-content`, for rendering third-party plugin descriptions.
- `line-clamp-*` utilities are replaced the same way, via `.pm-clamp-2`/`.pm-clamp-3`.
