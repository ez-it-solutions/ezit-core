# Ez IT WP Ecosystem — Roadmap

One **core plugin** (`core/ezit-core`) shared by every Ez IT theme, talking to the
self-hosted **License/Activation server** (`license.ez-it-solutions.com` —
LicenseBox-compatible, see `Legendary 3D Prints/-=WEBSITE=-/v1.0/registration/v1.0`).

Themes stay thin (markup + styles + assets). Everything cross-cutting —
licensing, updates, marketplace, comms — lives in the core so a theme zip is
"install + activate + enter license" and nothing more.

---

## Architecture

```
WP-Theme/v1.0/
├─ ROADMAP.md                    ← this file
├─ core/
│  ├─ ezit-core/                 ← THE shared plugin (install once per site)
│  │  ├─ ezit-core.php           ← bootstrap, constants, module loader
│  │  ├─ inc/
│  │  │  ├─ class-ezit-core.php      registry: themes/products announce themselves
│  │  │  ├─ class-ezit-api.php       LicenseBox client (wp_remote_post, LB-* headers)
│  │  │  ├─ class-ezit-licensing.php activate/verify/deactivate + stored cert
│  │  │  ├─ class-ezit-updater.php   check_update → WP update transient + licensed zip
│  │  │  ├─ class-ezit-marketplace.php  catalog UI (remote catalog in Phase 3)
│  │  │  └─ class-ezit-admin.php     admin menu/pages/notices
│  │  └─ assets/ (admin css/js)
│  └─ examples/donat/            ← reference theme + core (Themeholy) — study only
└─ Trezise Insurance/            ← theme #1 (port of index.htm)
```

### Contract between theme and core

A theme registers itself in `functions.php`:

```php
if (function_exists('ezit_core')) {
    ezit_core()->register_product([
        'slug'       => 'trezise-insurance',   // WP stylesheet dir / package slug
        'type'       => 'theme',               // theme | plugin | product
        'name'       => 'Trezise Insurance',
        'version'    => wp_get_theme()->get('Version'),
        'product_id' => 'XXXXXXXX',            // hex id created in license dashboard
        'api_key'    => '',                    // product api_key from dashboard
    ]);
}
```

Core never hard-codes theme names — the registry is the integration point.
Unlicensed = admin notices only; the front-end never breaks for a client visitor.

## Server API (already live — LicenseBox-compatible)

Client sends headers `LB-API-KEY`, `LB-URL`, `LB-IP`, `LB-LANG` on every call.

| Endpoint | Body | Used by core |
|---|---|---|
| `POST /api/check_connection_ext` | — | Settings → "Test connection" |
| `POST /api/activate_license` | `product_id, license_code, client_name, verify_type` | License page |
| `POST /api/verify_license` | `product_id, license_file` | daily cron + on-demand |
| `POST /api/deactivate_license` | `product_id, license_file` | License page |
| `POST /api/check_update` | `product_id, current_version` | Updater (12 h cache) |
| `HEAD /api/get_update_size/{id}` | — | update screen size display |
| `POST /api/download_update/main/{id}` | `product_id, license_file` | `upgrader_pre_download` |
| `POST /api/get_config` | — | `EzIT_Config` — versioned server config |

`license_file` = the signed cert returned as `lic_response` (HMAC + base64url JSON).
Core stores it in `ezit_licenses` option per product and echoes it back on verify.
`verify_license`/`check_update` responses also carry `config_version` — a
higher number makes the site re-read `get_config` (published `api_base`,
endpoint map, services, notices).

## Phases

### Phase 1 — Foundation *(this build)*
- [x] Core plugin skeleton + module loader + product registry
- [x] LicenseBox API client + License admin page (activate/deactivate/status)
- [x] Settings page (API URL, connection test)
- [x] Updater module (check_update → WP theme update transient → licensed download)
- [x] Marketplace page shell (lists registered products; remote catalog stub)
- [x] Trezise Insurance theme — `index.htm` ported to `header/front-page/footer`
- [x] Create `Trezise Insurance` product in license dashboard → paste `product_id`
      + `api_key` into the theme's `functions.php` *(done — `F43D2273`, live on demo.treziseinsurance.com)*
- [x] Site gate / maintenance mode (`class-ezit-gate.php`) — license enforcement
      + voluntary UC page with layouts, accent, countdown, client-access cookie

### Phase 2 — Licensing hardening
- [x] Per-theme activation caps surfaced in admin — the Licenses card
      decodes the cert and shows seats, domain, expiry, licensee,
      last-verified
- [x] Deactivation on theme switch — `register_product(
      'deactivate_on_switch' => true)` releases the seat on switch_theme
- [x] Grace periods — transport failures stamp `failing_since`; admin
      warning banner after `ezit_license_grace_days` (3) — front end never
      breaks
- [x] Offline tolerance: cert presence + `expires_at`/`domain` check without phoning
      home *(gate validates cert locally; transport failure keeps last verdict)*
- [x] Gate↔cache interop — armed gate emits `DONOTCACHEPAGE` + LiteSpeed
      no-cache (file caches ignore headers); every verdict flip purges 12+
      cache backends + reverse proxies, with a front-end retry flag for
      flips detected in cron/REST
- [x] Server-published config (1.1.0) — `EzIT_Config` caches the server's
      `get_config` doc; `api_base` repoints every site without a plugin
      deploy. Repoints are honored only for HTTPS hosts under
      `ez-it-solutions.com` (`ezit_allowed_api_hosts` to extend) so an
      unsigned doc can't leak `LB-API-KEY` off-platform
- [x] Reason codes (1.2.0) — `status_code` from failed activate/verify is
      stored and mapped to admin copy (`PRODUCT_DISABLED` → "Product
      retired", `ACTIVATION_DISABLED`, `LICENSE_SUSPENDED`, …); retired
      products disable the activate form and block theme-switch to the
      affected theme

### Phase 3 — Marketplace
- [x] Server: `GET /api/catalog` (public product listing: slug, name, thumb,
      price, version, type, installed flag) + `GET /api/catalog/{slug}` detail —
      free items expose `api_key`; paid items need purchase first (Phase 5)
- [x] Server: `download_update` accepts published `is_free` listings without a
      license cert — api_key + LB-URL authenticate the install
- [x] Core: browse catalog in-admin with cards (name, type, version, category,
      free/paid, installed badge) + nonce'd `admin_post_ezit_market_install`
      install flow (detail → check_update → zip via download_update →
      WP_Upgrader; plugins auto-activate, themes switch via the Themes tab)
- Server: `POST /api/purchase` (Phase 5)
- Categories/tags mirroring the Envato browsing model (church, business, portfolio…)

### Phase 4 — Communication & help desk
- Server: `GET|POST /api/support/tickets` (per-license threads), announcement feed
- Core: "Support" admin page — open ticket, view replies, broadcast notices in dashboard

### Phase 5 — Payments
- Server: checkout endpoint (Stripe link / manual invoice), license auto-issue on payment
- Core: "buy" buttons in Marketplace that deep-link to purchase + auto-fill activation

### Phase 6 — Update polish
- Changelog modal in WP updates UI (`details_url` / `sections`)
- Rollback (keep previous zip server-side, `download_update/previous/{id}`)
- Staged rollouts (percent flag server-side; core respects `update_id` eligibility)

### Phase 7 — Fleet ops
- Opt-in telemetry: WP version, theme version, PHP (server `POST /api/telemetry`)
- Bulk license view: all activations across client sites in the license dashboard
- WP-CLI: `wp ezit license activate --code=…`, `wp ezit update --all`

### Phase 8 — Theme Hub & Site Studio *(client-facing site management)*

Guiding rule: **theme declares, core renders, WP stores.** Core never
hard-codes a theme — every capability arrives through `register_product()`
extensions or declared hooks, and all writes go to standard WP storage
(theme mods / options) so nothing is siloed inside core.

- [x] **Themes tab** — new dashboard tab listing `wp_get_themes()`; Ez IT
  products get license badges, version, author. Activate via `switch_theme()`
  through a nonce'd `admin-post` handler. Overview tab links here and
  shows the active registered theme's card. (Also feeds Phase 3 — the
  install flow lands themes into this same tab.)
- [x] **About tab** — Ez IT Solutions business/contact card + an auto-detected
  inventory of every Ez IT product on the site. Detection works at two
  levels (see Product Manifest below): `register_product()` registrations
  at runtime, plus a filesystem scan of `wp_get_themes()`/`get_plugins()`
  headers for installed-but-inactive or unregistered products — so the tab
  surfaces "detected, not registered" items too. Vertical, version,
  license state and declared capabilities shown per product.
- [x] **Footer credit** — "Made with ♥ by Ez IT Solutions" toggle + editable
  text. Stored as theme mods (`ezit_credit_enabled`, `ezit_credit_text`)
  so it lives with the theme; core's Themes tab exposes the same controls
  for every registered product. Can later become an entitlement (e.g.,
  credit required on unlicensed/base tier, removable on paid).
- [x] **Site Studio** — a friendlier face on the Customizer. Themes declare
  a `fields` schema on `register_product()` (callable or array — Trezise
  reuses `trezise_home_sections()`/`trezise_home_defaults()` via
  `trezise_studio_fields()`); `EzIT_Studio` renders grouped forms under
  Settings → Site Studio and writes **theme mods** — the same storage the
  Customizer uses, so both editors stay in sync and edits survive theme
  updates. Inactive registered themes are editable too (their
  `theme_mods_<slug>` option is read/written directly).
- [x] **Alerts/Banners** — theme registers named slots (e.g. the `enroll`
  enrollment bar). Core manages per-slot content, link, schedule and
  on/off as options; the theme renders whatever the slot config says.
- [x] **Custom login page** — core module: configurable slug (rewrite rule →
  dedicated template), branded with the active theme's palette/logo
  (reuse the `uc` config). Replaces wp-login for members; doubles as the
  gate's client-access front door.
- [ ] **Page builder (later)** — the field schema is the foundation: sections
  become draggable "blocks," each block's controls are the same schema
  fields the Studio already renders. Build Studio first; the builder is a
  UI layer over the same data.

### Phase 9 — Billing & feature entitlements

Billing lives at **billing.ez-it-solutions.com (Invoice Ninja)** — the site
never stores cards or runs a checkout, so no WooCommerce and minimal PCI
surface.

- **License carries entitlements** — the cert payload gains a `features:[]`
  list (e.g. `studio`, `page_builder`, `footer_credit_off`, `custom_login`).
  Core exposes `ezit_feature_enabled($feature)`; themes/plugins gate UI on
  it. A license status change (missed payment → server deactivates) already
  flows through the existing verify/gate machinery — entitlements just ride
  inside the same cert.
- **Invoice Ninja integration** — recurring invoices/subscriptions per
  client+product; Invoice Ninja handles the payment gateway (Stripe et al.) —
  it *is* the payment frontend, no separate processor needed. Webhook /
  license-server sync flips `status` + `features` on payment events.
- **Billing tab in core** — shows plan, renewal date, enabled features
  (decoded from the stored cert — no live call needed), and deep-links into
  the Invoice Ninja client portal for card/payment-method updates.
- **Owner vs admin split** — site owners (client role) see Billing,
  Studio, and Themes; licensing internals stay behind `manage_options`.
  See Roles contract below.

### Phase 10 — Roles, verticals & onboarding

- **Role model** — core registers a custom capability set (`ezit_owner`,
  `ezit_studio`, `ezit_billing`…) mapped to a "Site Owner" role. Each
  vertical names its own owner: Pastor (church), Principal/Dean (school),
  City Manager (gov). A manifest `roles` map lets a theme suggest the
  roles/caps its vertical needs; core provisions them on theme activation.
- **Verticals** — manifest field `vertical: church|school|athletics|gov|
  dental|law|business|generic` drives Studio sections, marketplace
  categories, suggested roles, and industry-specific field sets
  (service times, staff directories, schedules…).
- **Multi-product sites** — an Athletics site can run as a second
  registered product on the same core (separate license, separate billing,
  separate features) or as a linked sub-site — the registry already keys
  everything per-slug, so both work without core changes.
- **Onboarding wizard** — first-run checklist per vertical: license →
  owner account → payment method → branding → content import.

### Phase 11 — Observability: system log, filters & retention

One place to see exactly what the plugin is doing — every outbound call,
verdict and event lands in a unified stream instead of scattered
transients and ad-hoc logs.

- [ ] **System log** — a single event table under Settings recording
  licensing calls (activate/verify/deactivate), update checks, catalog
  fetches, config syncs, gate decisions and mail sends. Each entry:
  timestamp, source, level, summary — plus a **raw request/response
  viewer** so the exact Marketplace and license-server exchange is
  inspectable end-to-end.
- [ ] **Thunderbird-style filters** — saved filter rules built from
  match conditions (source, level, text match, date range) with
  include/exclude logic; a quick-filter bar plus column sorting and
  search. Filters are named, reorderable, and apply to both the view
  and retention plans below.
- [ ] **Smart retention plans** — per-source / per-level policies (e.g.
  keep errors 90 days, routine verifies 7, strip raw bodies after 30)
  pruned on cron, with size caps and an export-before-purge escape
  hatch. Filter-based rules reuse the same condition engine, so "keep
  all `source=gate` failures" is one clause.
- [ ] **Email Log migrates in** — Settings → Email log becomes the
  `mail` source of the unified stream, gaining the same filtering and
  retention as every other source.

## Conventions

- Core classes are `EzIT_*`, prefixed functions `ezit_*`, options `ezit_*`,
  nonces `ezit_*_nonce`, text domain `ezit-core`. **Never collide:**
  theme/plugin internals get their own prefix (`trezise_*`, etc.) — only
  the integration points (hooks, options, caps) share `ezit_*`.
- Nothing in core assumes a theme name — everything flows through `register_product()`.
- Server stays product-agnostic; each product just needs `product_id` + `api_key`.

### Product manifest — how any Ez IT product declares itself

Every future theme/plugin ships two things, which double as the starter
templates for new products:

1. **`ezit.json`** in the product root — static metadata:
   `slug, type, name, vertical, product_id, api_key, fields, slots, roles`
2. **Header marker** — `EzIT Product: {product_id}` in `style.css` (themes)
   or the plugin header, so the About tab can detect installed-but-inactive
   or not-yet-registered products by scanning `wp_get_themes()` /
   `get_plugins()` headers.

`register_product()` args stay the canonical runtime registration; the
manifest is the declarative layer Studio/About/Marketplace read from, and
the template repo will contain one theme + one plugin scaffold pre-wired
with manifest, registration call, license prefill, and credit support.

### Roles contract

- `manage_options` → Ez IT admin (us) — licensing internals, gate mode.
- `ezit_owner` → client owner (Pastor/Principal/…) — Studio, Themes tab,
  billing portal link, alerts/banners.
- `ezit_billing` → payment-method management (defaults to owner).
- `ezit_studio` → content/field editing (defaults to editor+).
- Vertical manifests may propose extra roles; core creates them on theme
  activation only if absent — never mutates existing WP/core/plugin roles.
