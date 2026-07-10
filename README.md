# Plugin Marketplace for Pelican

Browse, install, update and manage Minecraft plugins from **Hangar**, **Modrinth** and **SpigotMC** directly inside the [Pelican](https://pelican.dev) panel - no more downloading a jar on one tab and SFTP-ing it into `/plugins` on another.

> **Status**: This plugin was built end-to-end (services, jobs, Filament UI, permissions, API, tests, docs) against a local checkout of the Pelican panel source, with every third-party API integration (Hangar/Modrinth/Spiget) verified against the **live** APIs. It was **not** exercised against a running Pelican instance + Wings daemon, since none was available in the environment it was built in. See [docs/DEVELOPER.md](docs/DEVELOPER.md) for exactly what that means and the manual QA checklist to run through before you trust it in production.

## Features

- **Unified search** across Hangar, Modrinth and SpigotMC (SpigotMC is discovery-only - this plugin never proxies or scrapes SpigotMC downloads, per their ToS).
- **Only Bukkit-family results**: Modrinth results are filtered to Bukkit/Spigot/Paper/Purpur/Folia; Fabric/Forge/NeoForge/Quilt mods are hidden.
- **One-click install** with automatic Minecraft-version/loader compatibility checks, duplicate/conflict detection, and one-click installation of resolvable required dependencies (PlaceholderAPI, Vault, LuckPerms, ProtocolLib, ...).
- **Installed Plugins** page: scans a server's `/plugins` directory over Wings, extracts real metadata from each jar's `plugin.yml`/`paper-plugin.yml`, and lets you enable/disable, update or uninstall from the panel.
- **Plugin Updates** page with per-plugin and bulk "Update all", changelog preview before updating, automatic jar backup, and rollback on a failed update.
- **Plugin health warnings** for abandoned (no release in 12+ months) or known-deprecated plugins.
- **Favorites** and **recently viewed**, per user.
- **Background jobs** for every install/update/uninstall/scan - nothing blocks the HTTP request, and progress is visible live in the panel.
- **Admin-configurable settings**: toggle each repository, preferred repository, cache duration, max download size, download timeout, dependency auto-install, health warnings, backups, update notifications, automatic update checks.
- **Full permission integration**: admin role permissions (`view/install/update/delete/settings plugins`) and per-server subuser permissions (`plugins.view/install/update/delete`), wired through Pelican's own permission system - no bespoke auth code.
- A small HTTP API (session-authenticated, same as the rest of the panel) alongside the Filament UI - see [docs/API.md](docs/API.md).

## Requirements

- Pelican panel `^1.0.0` (Filament v5 / Laravel 13 based).
- PHP `^8.3` with the `zip` extension (already required by Pelican itself).
- A queue worker running (`php artisan queue:work`) - installs/updates/scans are queued jobs, same as the panel's own plugin installer.

## Quick install

```bash
cd /path/to/pelican-panel
cp -r plugin-marketplace plugins/plugin-marketplace   # this repository, copied in as-is
php artisan p:plugin:install plugin-marketplace
```

That single command runs the plugin's migrations, builds panel assets (`yarn build`), and enables it - it's the exact same flow the Admin UI's "Install" button uses. See [docs/INSTALLATION.md](docs/INSTALLATION.md) for the full walkthrough (including what to do if you'd rather install from the Admin UI instead of the CLI), and the **"What you need to do on your Pelican server"** checklist at the bottom of that file, which is the fastest path if you just want to get testing.

After installing, grant the `plugins.*` permissions to the roles/subusers who should use it (Admin → Roles, and each server's Subusers page) - see [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Where things live in the panel

| Panel | Navigation group | Pages |
|---|---|---|
| Server (per game server) | **Plugin Marketplace** | Marketplace, Installed Plugins, Plugin Updates (Plugin Details is reachable from a card/row, not a standalone nav item) |
| Admin | **Plugin Marketplace** | Settings |

Settings is admin-only by design: it configures instance-wide behavior (which repositories are reachable, cache/download limits, ...), not anything scoped to one server - see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#why-settings-lives-in-the-admin-panel) for the reasoning.

## Documentation

- [docs/INSTALLATION.md](docs/INSTALLATION.md) - installing, updating, uninstalling.
- [docs/CONFIGURATION.md](docs/CONFIGURATION.md) - every setting and permission, explained.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) - how the plugin is put together and why.
- [docs/DEVELOPER.md](docs/DEVELOPER.md) - running the test suite, manual QA checklist, extending the plugin.
- [docs/API.md](docs/API.md) - the plugin's own HTTP API.
- [CONTRIBUTING.md](CONTRIBUTING.md) - how to contribute.
- [CHANGELOG.md](CHANGELOG.md) - release history.

## License

MIT.
