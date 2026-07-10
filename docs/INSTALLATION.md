# Installation

Plugin Marketplace is a standard Pelican plugin - it's installed exactly the way any other Pelican plugin is, using Pelican's own plugin system (`app/Services/Helpers/PluginService.php`, the `p:plugin:*` artisan commands, and the Admin → Plugins page). This document assumes you already have a working Pelican panel.

## 1. Copy the plugin into place

From the root of your Pelican panel checkout:

```bash
cp -r /path/to/plugin-marketplace plugins/plugin-marketplace
```

The folder name **must** be `plugin-marketplace` - it has to match the `id` in `plugin.json`, or Pelican will refuse to load it (`PluginIdMismatchException`).

## 2. Install it

Pick one:

### Option A - CLI (fastest)

```bash
php artisan p:plugin:install plugin-marketplace
```

This runs, in order: composer package sync (this plugin declares none, so it's a no-op), `yarn install && yarn build` (compiles the panel's frontend so the plugin's Blade/Livewire pages render), the plugin's own database migrations, and finally marks the plugin `Enabled`.

### Option B - Admin UI

1. Log in as a root admin.
2. Go to **Admin → Plugins**.
3. The plugin should already show up in the list (Pelican scans `plugins/*/plugin.json` automatically) with status `Not Installed`.
4. Click the install action on its row.

Either way, watch the output/panel logs for errors - if `yarn build` fails (missing Node/Yarn, or a dependency conflict), the panel will still function but this plugin's pages will 404 or render unstyled until it's fixed and re-run.

## 3. Restart the queue worker

Installs/updates/uninstalls/scans all run through Laravel's queue (see [docs/ARCHITECTURE.md](ARCHITECTURE.md#jobs--queues)). If you weren't already running one:

```bash
php artisan queue:work
```

(or however your deployment normally runs the queue - Supervisor, systemd, Horizon, etc.)

## 4. Grant permissions

Nothing is visible to anyone until you grant access - this is deliberate, matching how every other Pelican feature works.

- **Admin/staff**: Admin → Roles → edit a role → find the new **Plugins** section → check `view`, `install`, `update`, `delete`, and `settings` as appropriate.
- **Per-server subusers**: open a server → Subusers → edit a subuser → find the new **Plugin Marketplace** permission group → check `view`, `install`, `update`, `delete` as appropriate.
- **Server owners** always have full access to their own server's plugins regardless of the above (same rule Pelican uses for every other per-server permission).

See [docs/CONFIGURATION.md](CONFIGURATION.md#permissions) for exactly what each permission unlocks.

## 5. Configure it (optional)

Sensible defaults are seeded automatically (all three repositories enabled, 30-minute cache, 250 MB download cap, etc.). To change them: **Admin → Plugin Marketplace → Settings** (requires the `settings plugins` admin permission). See [docs/CONFIGURATION.md](CONFIGURATION.md) for what every field does.

## Updating

```bash
php artisan p:plugin:update plugin-marketplace
```

Requires `update_url` to be set in `plugin.json` pointing at a hosted update manifest - see Pelican's own plugin update mechanism (`App\Services\Helpers\PluginService::updatePlugin()`). If you're developing locally, just overwrite the `plugins/plugin-marketplace` folder with the new version and re-run `php artisan p:plugin:install plugin-marketplace` (safe to re-run - migrations are idempotent).

## Uninstalling

```bash
php artisan p:plugin:uninstall plugin-marketplace --delete
```

This rolls back the plugin's own migrations (dropping `plugin_marketplace_*` tables), rebuilds assets, and deletes the plugin's files. **It does not touch anything on your Minecraft servers** - jars this plugin installed stay exactly where they are in each server's `/plugins` folder; uninstalling the panel plugin only removes the panel-side tracking/UI.

## What you need to do on a real Pelican server before testing this plugin

This is the concrete checklist, since this plugin was built without access to a live Pelican + Wings environment:

1. `cp -r plugin-marketplace plugins/plugin-marketplace` in your Pelican panel checkout.
2. `php artisan p:plugin:install plugin-marketplace` (composer sync is a no-op since this plugin has no extra PHP dependencies beyond `ext-zip`, which Pelican itself already requires; this step's real work is `yarn build` + the 5 migrations under `database/migrations/`).
3. Make sure a queue worker is running (`php artisan queue:work`).
4. Grant `plugins.*` permissions to a test role and/or subuser (step 4 above).
5. Open a server in the panel and check the **Plugin Marketplace** navigation group appears with Marketplace / Installed Plugins / Plugin Updates.
6. Open **Admin → Plugin Marketplace → Settings** and confirm the form loads and saves.
7. Work through the manual QA checklist in [docs/DEVELOPER.md](DEVELOPER.md#manual-qa-checklist) - that is the authoritative list of what to click through, since none of it could be exercised automatically while building this.

No extra Composer packages need to be required on the host panel - this plugin only uses packages Pelican's `composer.json` already requires (`illuminate/*`, `symfony/yaml`, `ext-zip`). Nothing needs to be added to the host's `composer.json`/`package.json` by hand.
