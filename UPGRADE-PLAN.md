# Upgrade plan — license server + EzIT Core 1.1.0

Two deploys, in order. Server first (it's backward-compatible), then Core.

## A. Server — `registration/v1.0` → license.ez-it-solutions.com

Safe to upload over the live copy — **all changes are additive**:

- **New file:** `src/ServerConfig.php` (autoloaded — must exist or
  `LicenseApi`/`AdminApi` fatal).
- **Changed:** `src/Database.php`, `src/LicenseApi.php`, `src/AdminApi.php`,
  `views/admin.php`, `assets/js/admin.js`, `assets/css/admin.css`.
- **Schema:** adds `server_configs` (`CREATE TABLE IF NOT EXISTS` + seeds
  config v1 on next request). No existing tables/columns touched.
- **Wire format:** every existing response is unchanged; `verify_license`
  and `check_update` now also return `config_version` (clients that don't
  read it are unaffected).

Steps:

1. Upload the folder contents over the live site.
2. Load any page once — the table creates and seeds itself.
3. Dashboard → **Server Config** tab → confirm `v1` renders; edit +
   publish to repoint `api_base` / endpoints / services / notices.
4. Rollback = swap the old `src/`/`views/`/`assets/` back; `server_configs`
   is inert on old code.

## B. EzIT Core — 1.0.2 → 1.1.0 (this plugin)

Not strictly required — old Core keeps working against the new server.
Required **only if** you want sites to follow server-published config
(the point of the feature).

New/changed behavior:

- **New module** `inc/class-ezit-config.php` (`EzIT_Config`) — cached
  server doc in `ezit_server_config`; `api_base()`, `endpoint()`,
  `service()`, `notices()` accessors; `maybe_sync()` version hint.
- **`EzIT_Core::api_url()`** now prefers the published `api_base`; the
  option/`EZIT_API_URL` value becomes the **bootstrap URL**
  (`bootstrap_url()`).
- **`EzIT_API::post()`** remaps bare endpoint names through the doc's
  `endpoints` map and accepts a base-override for bootstrap fallback.
- **Sync triggers:** `verify_license` + `check_update` responses carry
  `config_version` → `maybe_sync()` re-reads `get_config` when it moves.
  Hourly cron calls `refresh_if_stale()` (12h floor). Sync retry falls
  back to the bootstrap URL so a dead `api_base` can't wedge a site.
- **`uninstall.php`** drops `ezit_server_config`.

Deploy = replace the plugin folder (or just the changed files). No
migrations — option is created on first sync.

## C. Migration to EzOps when it launches

1. Stand up EzOps at `ops.ez-it-solutions.com` serving the same API.
2. Dashboard → Server Config → publish `api_base: https://ops.ez-it-solutions.com/api/`.
3. Every site re-points itself within one verify cycle (≤1h). Keep the
   old host serving `get_config` until all sites have moved.

## Contract reminder for themes

Themes never hardcode server URLs — they read licensing state via
`ezit_core()->licensing()->is_active($slug)` and stay gated via
`register_product('gated' => true)`. That rule is already enforced;
nothing theme-side changes in 1.1.0.
