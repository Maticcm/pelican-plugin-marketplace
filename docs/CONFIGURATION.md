# Configuration

## Settings page

**Admin → Plugin Marketplace → Settings** (requires the admin `settings plugins` permission). Backed by a single-row `plugin_marketplace_settings` database table (see `src/Models/MarketplaceSetting.php`), not `.env` - unlike most core Pelican settings, so changes take effect immediately with no `queue:restart` needed.

| Field | Default | What it does |
|---|---|---|
| Enable Hangar | on | Whether Hangar is searched/shown at all. |
| Enable Modrinth | on | Whether Modrinth is searched/shown at all. |
| Enable SpigotMC | on | Whether SpigotMC discovery results are searched/shown. Always install-by-hand only, regardless of this toggle. |
| Preferred repository | Hangar | Currently informational (surfaced via the API/settings); a future version could use it to break ties when the same plugin exists on multiple repositories. |
| Automatic update checks | on | Whether the hourly scheduled task (`plugin-marketplace-update-check`, registered in `PluginMarketplaceProvider::registerSchedule()`) re-checks marketplace-installed plugins for updates on every server, independent of anyone opening the panel. |
| Cache duration | 30 minutes | How long search results, plugin metadata, versions and category lists are cached for. Lower = fresher data, more upstream API calls. |
| Maximum download size | 250 MB | Hard cap enforced by `DownloadManagerService` before a single byte of a jar is written to your server - both via `Content-Length` (fast rejection) and actual downloaded size (in case the header lied). Also used as the cap when reading an existing jar back off a server for metadata extraction during a scan. |
| Download timeout | 120 seconds | HTTP timeout for downloading a jar from Hangar/Modrinth. |
| Enable dependency installation | on | Whether clicking "Install" also queues installs for any *resolvable* required dependency (see [ARCHITECTURE.md](ARCHITECTURE.md#dependency-resolution)). When off, you just get a warning naming the missing dependencies instead. |
| Enable plugin health warnings | on | Whether "no release in 12+ months" / "known deprecated" badges are computed and shown. |
| Enable backups before updates | on | Whether `PluginUpdaterService` copies the current jar to `/.pelican-plugin-marketplace/backups/` (outside `/plugins`, so it's never itself loaded as a plugin) before overwriting it, and restores it automatically if the update fails validation or upload. |
| Enable update notifications | on | Whether users get a panel notification when a scan finds updates available. |

Every non-toggle numeric field has a sane min/max enforced both in the form and in `UpdateSettingsRequest` (the API-side validation) - see [docs/API.md](API.md).

## Repository connection settings (config file, not the UI)

A handful of lower-level settings live in `config/plugin-marketplace.php` (loaded automatically by Pelican's `PluginService` into `config('plugin-marketplace.*)`) rather than the database, since they're deployment-level rather than per-install-operator concerns:

- `repositories.hangar.base_url` / `repositories.modrinth.base_url` / `repositories.spigot.base_url` - override if you're pointing at a self-hosted mirror or a staging API.
- `user_agent` - the `User-Agent` header sent on every upstream request. Modrinth's API *requires* a uniquely-identifying one; the default is reasonable but you may want to customize it.
- `http.timeout` / `http.connect_timeout` - low-level HTTP client timeouts (separate from the "download timeout" setting above, which only applies to the jar download itself).
- `known_dependencies` - the curated plugin-name → marketplace-listing map used to resolve dependencies like `Vault`/`ProtocolLib`/`PlaceholderAPI` that a `depend:` entry in a plugin.yml only names, not links. Every entry was verified against the live APIs while building this plugin (see inline comments in the config file) - extend this list for any other common dependency you want one-click-resolvable.
- `known_replacements` - map of `"{repository}:{project_id}"` to a suggested replacement project, merged into the automatic "abandoned" health heuristic.
- `supported_loaders` / `excluded_loaders` - the Bukkit-family vs. mod-loader tag lists used to filter Modrinth results.

Every one of these can also be overridden via environment variables (`PLUGIN_MARKETPLACE_*`) if you'd rather not edit the file - see the file itself for the exact variable names.

## Permissions

### Admin (role) permissions

Registered via `Role::registerCustomPermissions(['plugins' => ['view', 'install', 'update', 'delete', 'settings']])` in the plugin's provider - shows up as its own **Plugins** section on Admin → Roles → (edit a role), exactly like every built-in permission group.

| Permission | Unlocks |
|---|---|
| `view plugins` | Read the settings via the API (`GET /plugin-marketplace/api/settings`). |
| `install plugins` | (Reserved for a future admin-level bulk-install feature; not currently required for anything.) |
| `update plugins` | (Reserved.) |
| `delete plugins` | (Reserved.) |
| `settings plugins` | View **and** save the Settings page. |

Root admins bypass all permission checks, as with every other Pelican feature.

### Subuser (per-server) permissions

Registered via `Subuser::registerCustomPermissions('plugins', ['view', 'install', 'update', 'delete'], ...)` - shows up as its own **Plugin Marketplace** group on a server's Subusers page.

| Permission | Unlocks |
|---|---|
| `plugins.view` | See the Marketplace, Plugin Details, and Installed Plugins/Updates pages for that server. |
| `plugins.install` | Use the Install button (and its automatic dependency installs). |
| `plugins.update` | Update a single plugin, bulk-update, enable/disable a plugin, and re-scan. |
| `plugins.delete` | Uninstall a plugin. |

The server **owner** always has all four, the same rule Pelican applies to every other subuser permission (`ServerPolicy::before()` short-circuits to `true` for the owner).
