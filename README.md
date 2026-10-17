# Ez IT Core

Shared foundation plugin for every Ez IT Solutions WordPress product —
licensing, updates, marketplace, site gate and admin hub, backed by the
Ez IT license server (`license.ez-it-solutions.com`,
LicenseBox-compatible).

The plugin itself lives in `ezit-core/` (upload to `wp-content/plugins/`).

## Install

1. Upload `ezit-core/` to `wp-content/plugins/` and activate it.
2. **Ez IT → Settings**: confirm the license-server URL (defaults to
   `https://license.ez-it-solutions.com`; override via `EZIT_API_URL`
   in `wp-config.php`) and run the connection test.
3. Install an Ez IT product (e.g. the Trezise Insurance theme) — it
   registers itself automatically, then activate its license under
   **Ez IT → Licenses**.

## Architecture

| File | Purpose |
|---|---|
| `ezit-core.php` | bootstrap, constants, module loader |
| `inc/class-ezit-core.php` | singleton + product registry (`register_product()`) |
| `inc/class-ezit-api.php` | LicenseBox API client (`check_update`, `activate_license`, …) |
| `inc/class-ezit-config.php` | server-published config (`get_config`) — repoints `api_base`, endpoint map, services, notices |
| `inc/class-ezit-licensing.php` | per-product license state, verify cron, cert signing, `status_code` reason labels |
| `inc/class-ezit-updater.php` | injects licensed updates into WP's update transients |
| `inc/class-ezit-themes.php` | Ez IT product inventory (themes + plugins) for the Themes/About tabs |
| `inc/class-ezit-marketplace.php` | registered-product list + remote catalog fetch |
| `inc/class-ezit-gate.php` | maintenance-mode gate + "Under Construction" page |
| `inc/class-ezit-admin.php` | tabbed admin dashboard (Overview/Licenses/Marketplace/Settings) |
| `templates/admin/` | dashboard + tab templates |

## Product contract

Products integrate entirely through `register_product()` — core never
hard-codes a theme name:

```php
ezit_core()->register_product([
    'slug'         => get_stylesheet(),
    'type'         => 'theme',
    'name'         => 'My Theme',
    'version'      => '1.0.0',
    'product_id'   => 'XXXXXXXX',
    'api_key'      => 'xxxxxxxxxxxxxxxx',
    'license_code' => '',   // optional prefill for the activate form
    'uc'           => ['accent' => '#…', 'bg' => '…'],
]);
```

Then the product gets licensing, update checks, the site gate and a
marketplace listing for free. See `ROADMAP.md` for the build plan
(product manifests, roles, verticals, billing).

## Conventions

- Classes `EzIT_*`, functions/options/nonces `ezit_*`, text domain `ezit-core`.
- Product internals use their own prefix — only integration points share `ezit_*`.
- License state never breaks the front end for logged-in editors.

## Repo layout

`examples/` is reference material (design comps, original site export)
and is intentionally not tracked.
