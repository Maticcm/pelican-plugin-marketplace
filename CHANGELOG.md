# Changelog

All notable changes to this project are documented in this file.

## [1.0.0] - 2026-07-10

Initial release.

### Added

- Unified search across Hangar, Modrinth and SpigotMC, with Modrinth results filtered to Bukkit-family software (Bukkit/Spigot/Paper/Purpur/Folia) and SpigotMC restricted to discovery-only (no automated downloads, per their ToS).
- Marketplace, Plugin Details, Installed Plugins and Plugin Updates pages in the Server panel; Settings page in the Admin panel.
- One-click install with Minecraft-version/loader compatibility checks, duplicate/conflict detection, and automatic one-click installation of resolvable required dependencies.
- Plugin scanning: reads real metadata from each installed jar's `plugin.yml`/`paper-plugin.yml`.
- Update checking (manual scan + hourly scheduled task) with per-plugin and bulk update, changelog preview, automatic backup, and rollback on failure.
- Plugin health warnings (abandoned / known-deprecated).
- Favorites and recently-viewed, per user.
- Full admin role + subuser permission integration.
- Admin-configurable settings (repository toggles, cache duration, download limits, dependency/backup/notification toggles, automatic update checks).
- A small session-authenticated HTTP API alongside the Filament UI.
- Standalone Pest/Testbench test suite covering version comparison, plugin.yml parsing, jar validation, compatibility/dependency logic, and all three repository clients against real captured API fixtures.
