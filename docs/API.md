# API

This plugin ships a small HTTP API alongside its Filament UI, registered in `PluginMarketplaceProvider::registerRoutes()` under `web` + `auth.session` middleware (session-authenticated, same as the rest of the panel - **not** a separate API-key surface). Every endpoint is prefixed `/plugin-marketplace/api` and re-validates the relevant `plugins.*` permission itself, independent of whatever the Filament UI already checked, since this is a real HTTP surface a browser (or anything else with a valid panel session) can call directly.

All responses are JSON. Server-scoped endpoints resolve `{server}` by UUID (`{server:uuid}`) and return `404` for an unknown server, `403` if the current user lacks the relevant permission on it.

## Search & discovery

### `GET /plugin-marketplace/api/search`

Query params (all optional, validated by `Http\Requests\SearchRequest`):

| Param | Type | Notes |
|---|---|---|
| `query` | string | Free-text search term. |
| `repositories[]` | string[] | Any of `hangar`, `modrinth`, `spigot`. Omit for all enabled repositories. |
| `categories[]` | string[] | Normalized category keys - see `Enums\MarketplaceCategory`. |
| `minecraft_version` | string | e.g. `1.21.1`. |
| `sort` | string | `popular` (default) \| `downloads` \| `updated` \| `rating` \| `name`. |
| `page` | int | 1-based. |
| `per_page` | int | Max 50. |

```json
{
  "items": [ { "repository": "modrinth", "project_id": "...", "name": "...", "...": "..." } ],
  "page": 1,
  "per_page": 20,
  "total": 42,
  "has_more": true,
  "errors": { "hangar": null }
}
```

### `GET /plugin-marketplace/api/plugins/{repository}/{projectId}`

`{projectId}` may contain `/` (Hangar identifies projects as `owner/slug`) - the route allows it. Returns `404` if not found. Records the view in the current user's "recently viewed" list as a side effect.

```json
{
  "data": { "repository": "hangar", "project_id": "owner/slug", "...": "..." },
  "health": "healthy",
  "health_message": null,
  "versions": [ { "id": "...", "version_number": "1.2.3", "download_url": "...", "...": "..." } ]
}
```

## Per-server installed plugins

All under `/plugin-marketplace/api/servers/{server}/plugins`.

| Method | Path | Permission | Notes |
|---|---|---|---|
| `GET` | `/` | `plugins.view` | List installed plugins. |
| `POST` | `/install` | `plugins.install` | Body: `repository`, `project_id`, `version_id`, `overwrite?`. Returns `202` + `{ "data": { "job_id": 1 } }`. |
| `POST` | `/{installedPlugin}/update` | `plugins.update` | Body: `version_id?` (omit for latest). Returns `202` + job id. |
| `POST` | `/{installedPlugin}/toggle` | `plugins.update` | Enable/disable (renames the jar). |
| `DELETE` | `/{installedPlugin}` | `plugins.delete` | Uninstall. Returns `202` + job id. |
| `POST` | `/scan` | `plugins.view` | Re-scan `/plugins` and check for updates. Returns `202` + job id. |
| `GET` | `/updates` | `plugins.view` | List plugins with an update available. |
| `POST` | `/updates/bulk` | `plugins.update` | Body: `installed_plugin_ids[]?` (omit for every outdated plugin). Returns `202` + job id. |

Install/update/uninstall/scan are all **asynchronous** - the response is a `job_id`, not the result. Poll it:

### `GET /plugin-marketplace/api/jobs/{job}`

```json
{
  "data": {
    "id": 1,
    "type": "install",
    "status": "downloading",
    "progress": 30,
    "plugin_name": "EssentialsX",
    "message": "Downloading EssentialsX-2.21.2.jar from Modrinth...",
    "meta": null,
    "finished": false,
    "started_at": "2026-07-10T12:00:00+00:00",
    "finished_at": null
  }
}
```

`status` is one of `pending`, `downloading`, `validating`, `backing_up`, `installing`, `completed`, `failed`, `rolled_back`. `finished` is `true` once `status` is one of the last three. Authorized for the job's own creator or anyone with `plugins.view` on the job's server.

## Favorites & recently viewed

| Method | Path | Notes |
|---|---|---|
| `GET` | `/plugin-marketplace/api/favorites` | Current user's favorites. |
| `POST` | `/plugin-marketplace/api/favorites` | Body: `repository`, `project_id`. Toggles - returns `{ "data": { "favorited": true } }`. |
| `DELETE` | `/plugin-marketplace/api/favorites/{repository}/{projectId}` | Explicit remove. |
| `GET` | `/plugin-marketplace/api/recent` | Current user's recently-viewed list (max 25, oldest pruned automatically). |

## Settings (admin only)

| Method | Path | Permission |
|---|---|---|
| `GET` | `/plugin-marketplace/api/settings` | `view plugins` |
| `PUT` | `/plugin-marketplace/api/settings` | `settings plugins` (enforced inside `Http\Requests\UpdateSettingsRequest::authorize()`) |

See [docs/CONFIGURATION.md](CONFIGURATION.md) for every field and its validation range.
