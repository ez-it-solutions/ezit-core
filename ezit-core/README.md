# Ez IT Core

Shared foundation for every Ez IT Solutions WordPress product — licensing,
update delivery, marketplace, site gate, alerts and admin chrome, backed by
the self-hosted license server at `license.ez-it-solutions.com`
(LicenseBox-compatible contract; server code: `registration/v1.0`).

Themes/plugins register themselves via `ezit_core()->register_product()`
(`product_id`, `api_key`, `gated`, optional `uc` branding). Every module in
`inc/` auto-loads; optional modules are `class_exists`-guarded so a partial
upload can never white-screen the site.

## Modules

| Class | Role |
|---|---|
| `EzIT_Core` | Product registry + shared licensing service + API URL resolution |
| `EzIT_API` | HTTP client → `{server}/api/*` (`LB-*` headers); exposes `Retry-After`, maps 503 `SERVER_UNAVAILABLE` to `ezit_server_unavailable` |
| `EzIT_Licensing` | Cert storage, hourly verify cron, verdict preservation, `stale_products()`, `server_state()`, `recovery_digest()` |
| `EzIT_Gate` | Front-end UC gate + emergency override + outage banner |
| `EzIT_Admin` | Ez IT dashboard (tabs), settings save, admin notices |
| `EzIT_Updater` | WP update transient → `check_update`/`download_update` (cert-gated) |
| `EzIT_Marketplace` | Product marketplace panel |
| `EzIT_Alerts` | Theme-declared front-end notice bars (label/detail/CTA/schedule) |
| `EzIT_Login` / `EzIT_Customizer` / `EzIT_Banners` / `EzIT_Mail_Log` / `EzIT_Studio` / `EzIT_Themes` | Branded login, Customizer panel, server announcements, mail log, Site Studio, product-header reader |

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

- Every wp-admin page: `maintenance` shows a warning notice, `down` an
  error notice, an armed override a warning notice — all immediately.
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
the updated server before overrides can arm during an outage.

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
