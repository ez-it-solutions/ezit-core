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
| `inc/class-ezit-api.php` | LicenseBox API client (`check_update`, `activate_license`, …); surfaces `Retry-After`, maps 503 `SERVER_UNAVAILABLE` to `ezit_server_unavailable` |
| `inc/class-ezit-config.php` | server-published config (`get_config`) — repoints `api_base`, endpoint map, services, notices |
| `inc/class-ezit-licensing.php` | per-product license state, verify cron, cert signing, `status_code` reason labels, `server_state()`, `recovery_digest()` |
| `inc/class-ezit-updater.php` | injects licensed updates into WP's update transients |
| `inc/class-ezit-themes.php` | Ez IT product inventory (themes + plugins) for the Themes/About tabs |
| `inc/class-ezit-marketplace.php` | registered-product list + remote catalog fetch |
| `inc/class-ezit-gate.php` | maintenance-mode gate + "Under Construction" page, emergency override, staff outage banner |
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

## License server outage behavior

Three distinct states, classified per failed `verify_license`:

- **Maintenance** — server answered 503 `SERVER_UNAVAILABLE` (its planned
  switch). Prior verdict preserved; re-checks honor `Retry-After`
  (clamped 5 min – 1 h, default 10 min).
- **Down** — every other failure: HTTP error, malformed JSON, transport
  error. Same verdict preservation and `Retry-After` pacing.
- **Real denial** — successful `status:false` JSON: authoritative, the
  verdict flips now.

A failed verify never counts as a denial. `stale_products()` warns admins
once a product's `failing_since` exceeds the grace window (default 3 days,
filter `ezit_license_grace_days`) — a warning only, never auto-revocation.

### Outage visibility

- Every wp-admin page: `maintenance` shows a warning stripe, `down` an
  error stripe, an armed override a warning notice — all immediately.
- Every front-end page: `EzIT_Gate::outage_banner()` pins a red stripe
  under the admin bar for **logged-in staff** (`edit_posts`) while
  `server_state() ≠ up`. Guests and gate users never see it. Rendered via
  `wp_body_open` with a `wp_footer` fallback — theme-agnostic.

## Emergency override

**Ez IT → Settings → Site gate → Emergency override** forces the gate
`open` (survives a total server outage) or `closed` (gates everyone but
editors), for 4/24/72/168 h. Always auto-expires; armed overrides announce
themselves on every admin screen.

Arming requires the product's **recovery code** (license server →
Products). Validation is fully offline: the server ships
`recovery_digest = HMAC-SHA256("recovery|" + code, api_key)` inside the
signed cert payload and every `verify_license` response; the site
recomputes and `hash_equals`-compares. The raw code never touches the
site. Clearing early needs no code.

Caveat: the digest must already be on file when the server goes dark —
it arrives via cert/verify, so a site needs one successful verify against
the updated server before overrides can arm during an outage. Rotating a
product's `api_key` server-side mints a new recovery code (and digest) —
the old code stops working once the site picks up the new digest.

## license-push

`POST /wp-json/ezit/v1/license-push` with `key=<api_key>` — a server →
site **wake-up** only. It carries no verdict; the site re-verifies over
HTTPS itself, so a forged push can't disable anything. Throttled
15 s/site; server never follows redirects when pushing.

## Security notes

- Certs are `<hmac><base64url payload>` — `HMAC-SHA256(server_secret|api_key)`
  over a payload pinning domain + product_id. The site decodes payloads
  for display but treats remote verify as the authority.
- Gate-user cookies (`ezit_access`) are HMAC tokens verified locally —
  no per-request server call.
- `EZIT_API_URL` (wp-config) or Settings overrides the server base;
  outbound calls are HTTPS to the Ez IT allowlist.

## Repo layout

`examples/` is reference material (design comps, original site export)
and is intentionally not tracked.
