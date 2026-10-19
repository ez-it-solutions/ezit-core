=== Ez IT Core ===
Contributors: ezitsolutions
Tags: licensing, marketplace, updates, theme framework
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.3.1
License: GPL-2.0-or-later

Shared foundation for Ez IT Solutions WordPress themes: licensing, updates,
marketplace and support — backed by license.ez-it-solutions.com.

== Description ==

Ez IT Core is installed once per site alongside any Ez IT theme. Themes
register themselves via `ezit_core()->register_product()` and immediately
gain license activation, WP-native update notices, and the Ez IT
Marketplace — all backed by our self-hosted LicenseBox-compatible
activation server.

== Installation ==

1. Upload `ezit-core` to `/wp-content/plugins/` and activate.
2. Install an Ez IT theme (e.g. Trezise Insurance).
3. Ez IT → Licenses → paste the license code issued in the license
   dashboard → Activate.
4. Ez IT → Settings only if pointing at a non-default server.

== License Server Outages ==

Failed `verify_license` calls never count as a denial. Three states:

* Maintenance — server answered 503 `SERVER_UNAVAILABLE` (its planned
  switch). Prior verdict preserved; re-checks honor `Retry-After`
  (clamped 5 min – 1 h, default 10 min).
* Down — every other failure (HTTP error, malformed JSON, transport).
  Same preservation and pacing.
* Real denial — a successful `status:false` JSON: authoritative.

While the server isn't `up`: every wp-admin page shows an immediate
notice, and a red stripe pins under the admin bar on every front-end
page for logged-in staff. Guests never see it by default — Settings →
Alerts → "Show the banner to guests too" opts them into a generic
maintenance notice (no licensing details). Past the
`ezit_license_grace_days` window (default 3 days) the verify path emails
`admin_email` once a day — verdicts stay preserved indefinitely; only
the silence is bounded.

== Emergency Override ==

Ez IT → Settings → Site gate → Emergency override forces the gate `open`
(survives a total server outage) or `closed` for 4/24/72/168 h — always
auto-expires, and armed overrides announce themselves on every admin
screen.

Arming requires the product's recovery code (license server → Products).
Validation is fully offline: the server ships
`recovery_digest = HMAC-SHA256("recovery|" + code, api_key)` inside the
signed cert and every verify response; the site recomputes and
`hash_equals`-compares. The raw code never touches the site.

Caveat: the digest must already be on file when the server goes dark —
it arrives via cert/verify, so a site needs one successful verify against
the updated server before overrides can arm during an outage.

== license-push ==

`POST /wp-json/ezit/v1/license-push` with `key=<api_key>` is a server →
site wake-up only — it carries no verdict; the site re-verifies over
HTTPS itself, so a forged push can't disable anything. Throttled 15
s/site; redirects are never followed.

== Server-initiated admin login (EzIT_Sso) ==

The license server can mint one-time wp-login URLs for any account on a
licensed site (dashboard → Activations → Log in):

* `GET /wp-json/ezit/v1/sso/users?key=&nonce=&sig=` — user list
* `POST /wp-json/ezit/v1/sso/token` — one-time login URL

Requests must present the product `api_key` plus a timestamped nonce
signed `HMAC-SHA256(nonce, api_key)`; nonces older than 5 minutes are
rejected, so captured requests can't be replayed.

Modes: `first_admin` (classic panel-login), `user` (any account — role
testing), `credentials` (`wp_authenticate`, rate-limited 10/min),
`provision` (create/reset a user with any WP role).

Tokens land in `user_meta ezit_sso_tokens` as `sha256 → expiry` (2-minute
TTL, consumed on use); `wp-login.php?ezit_sso_uid&ezit_sso_token` does
`wp_set_auth_cookie` and redirects to wp-admin. Full protocol docs:
`docs/SITE-LOGIN.md` in the license-server repo.

== Security Notes ==

* Certs are `<hmac><base64url payload>` — `HMAC-SHA256(server_secret|api_key)`
  over a payload pinning domain + product_id. The site decodes payloads
  informationally; remote verify remains the authority.
* `recovery_code_valid` treats `recovery_digest` as a reference value
  only — arming still requires the raw code. Forging a matching digest
  would take option/DB writes, where an attacker already owns the site;
  the boundary defended is admin-session compromise, not database access.
* Gate-user cookies (`ezit_access`) are HMAC tokens verified locally.
* `EZIT_API_URL` (wp-config) or Settings overrides the server base;
  outbound calls are HTTPS to the Ez IT allowlist.

== Filters ==

* `ezit_license_grace_days` — days of failed verify before the product is
  reported stale + admin email escalation (default 3)
* `ezit_gate_reverify_ttl` — seconds between synchronous re-verifies on
  the gate path (default 60)
* `ezit_uc_layouts` — available UC page layouts
* `ezit_uc_stylesheets` — theme CSS pulled into UC pages
