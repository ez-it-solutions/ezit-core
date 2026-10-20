=== Ez IT Core ===
Contributors: ezitsolutions
Tags: licensing, marketplace, updates, theme framework
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.5.25
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

== Self-Updates & Forced Releases ==

EzIT Core registers itself as a `plugin` product on the license server
(`EZIT_CORE_PRODUCT_ID` / `EZIT_CORE_PRODUCT_KEY` — wp-config or edit
`ezit-core.php`). Sites then see real WP "update available" badges for
the plugin and one-click updates through the licensed
download endpoint — no license code needed, the server authenticates
self-products by `api_key` and requires a **free + published** catalog
listing for package downloads.

Publishing an update with **Force update** ticked marks `forced:true`
in `check_update`: the site arms the release the moment it sees the
flag and applies it through `Plugin_Upgrader`/`Theme_Upgrader` (max 3
attempts, retracting the flag stops pending runs). WP-Cron still gets
an event, but the reliable trigger is the admin session itself — the
next time an admin opens any Ez IT page, a full-screen updater runs
the install live with a step checklist. Settings → Updates can exempt
individual products or the whole site. Works for themes too — same
flag.

== Changelog ==

= 1.5.25 =
* Settings → Updates can pin too — every product row now carries a
  Pin updates / Resume updates action and shows the Pinned badge
  inline; the old pins-only field merged into the list. The hint
  spells out the difference: ignore keeps updates visible, a pin
  hides them entirely

= 1.5.24 =
* Updates purge host caches now — every successful install (auto,
  forced, picker, marketplace) runs a best-effort flush: OPcache,
  WP object cache, WP Rocket / W3TC / SiteGround / LiteSpeed /
  Dreamhost Varnish, plus an ezit_purge_caches escape hatch.
  Stale admin pages and assets after an update are gone

= 1.5.23 =
* Pin updates on demand — hover the Installed badge on a card (it
  swaps to "Pin updates") or a chip in "Installed on this site" (a
  pill reveals Pin / Resume). Same protection a downgrade gets:
  auto + forced updates hold at the current version until resumed
* Pinned state is discoverable — Pinned badges carry an explaining
  tooltip and a hint line under the installed strip spells it out
* data-confirm now works on links too — the Pin action confirms via
  the branded modal before navigating

= 1.5.22 =
* Refresh button is green again (brand green + dark ink), hover goes
  darker green, and WP's blue :active/:focus paint is suppressed —
  keyboard focus keeps a soft green ring

= 1.5.21 =
* Fix: the card sort keyed the core card on EZIT_CORE_PRODUCT_ID —
  blank on sites using lazy self-credentials, so core sorted dead
  last. self_pid now falls back to self_credentials()
* Fix: the Refresh anchor carried both ezit-btn and ezt__refresh —
  the button class won on display/background, stacking the svg above
  the label and painting it green. One class, one rule set

= 1.5.20 =
* Fix: fatal "Call to undefined function section_label()" on the
  Marketplace tab — the restored heading prints the h4 markup
  directly like the strip above it

= 1.5.19 =
* Catalog cards now order like the "Installed on this site" strip —
  registered-product order first, then uninstalled items in catalog
  order (the strip and grid could disagree before)
* "Ez IT Marketplace" section label is back above the card grid
* Refresh button metrics pinned (38px tall, fixed 15px icon) —
  consistent at rest and while spinning
* Updates-applied banner gets a hover state; Dismiss underlines on
  hover and WP's blue :active/:focus paint is gone (keyboard focus
  keeps a green ring)

= 1.5.18 =
* Toast is now a dark pill — ink background, white text, green check
  icon (soft red on errors) — readable on any page instead of
  dark-on-green
* Refresh icon sits on the text baseline no longer — display:block
  centers it and stops the busy spin from stretching the button

= 1.5.17 =
* Card footers are real footers — the Installed / Update / pinned
  action row pins to the bottom edge of every card in a row, so a
  long excerpt no longer pushes the pill down relative to its
  neighbors

= 1.5.16 =
* Marketplace Refresh is instant — the arrow spins while the catalog
  re-fetches, the card grid + fetched stamp + version modals swap in
  place, and a toast confirms "Marketplace refreshed". No page
  reload (URL still works with JS off)
* Version modal shows a "Current" row even when the running build
  isn't a published release (a local build, or a release published
  after this site installed it) — it's inserted in version order,
  labeled "this site", instead of leaving the list with no highlight
* "Updates applied" Dismiss now fades the strip out and clears the
  log in the background — no more full page reload (the link still
  works when JS is off)

= 1.5.15 =
* Fix: opening Version History on one card could stack every modal
  after it — the checkbox toggle used a general-sibling selector
  (~), so with modals printed back-to-back at admin_footer the last
  modal always rendered on top (wrong product's history) and its
  close labels toggled the wrong input, leaving it stuck open.
  Adjacent sibling (+) — one input, one modal

= 1.5.14 =
* Toast redesigned — solid green pill bottom-right (errors solid red),
  springs in, slides down on dismiss, full-width on mobile; the
  top-center white banner is gone
* Version modal scrollbar no longer flattens the card's rounded
  corner — thin overlay thumb on a transparent track
* No more native confirm() dialogs — forms carry data-confirm and a
  branded modal asks instead (reusable for any future confirm)
* install_version() now uses Automatic_Upgrader_Skin — the stock skin
  echoed "Unpacking the package…" to the browser, sent headers, and
  the admin-post redirect never fired
* Pinned cards still show the Update button when a newer release
  exists — installing it un-pins automatically; "Resume updates"
  remains as a quiet link

= 1.5.13 =
* Updates are no longer invisible — every applied install (auto/forced,
  marketplace button, version picker) is logged to an "Updates applied"
  strip shown on every Ez IT tab and as a WP notice everywhere else
  until dismissed (name, from → to, how, when)
* Version history is a modal opened from a hover pill on the card
  thumbnail — release rows with version/date/badges/summary and
  per-row Install or Downgrade, Current marked, pin state + Resume
  inline. Cards stay symmetrical (nothing new in the card body) and
  the modal prints at admin_footer so the sticky top bar can never
  cover it
* Card hover no longer lifts the card — the green border stays and
  the thumbnail gets a gentle zoom instead
* Empty release history now reports WHY — the real API error (auth,
  transport, unknown endpoint) instead of a blanket "no releases" note,
  and the reason is cached alongside the list for 15 min
* "Fetched N ago" moved under the Refresh button in the page title row;
  the duplicate section heading above the cards is gone

= 1.5.12 =
* Marketplace: Refresh button moved up beside the page title — the
  catalog "Fetched N ago" stamp stays inline with the card grid
* Version picker now stays visible on every installed product — when
  the server can't serve release history it says so inside the
  dropdown instead of hiding the control entirely
* License server: releases can be disabled (invisible to check_update,
  /api/versions and downloads, record kept) and forced rollouts can be
  scoped to specific site domains (updates.force_targets)

= 1.5.11 =
* Forced updates could report "up_to_date" and fail: apply_update()
  relied on wp_update_plugins()/wp_update_themes() to rebuild the
  transient, which can skip firing the inject filter on a fresh
  last_checked — the upgrader then saw no response entry. The update
  transient is now primed directly before the upgrader runs
* Fixed WP 6.7 _load_textdomain_just_in_time notice: the update-scan
  event scheduled at plugins_loaded ran wp_get_schedules(), firing the
  cron_schedules filter's __() before init. Scheduling is deferred
  to init
* Version history on marketplace cards: a Versions dropdown on any
  installed product lists every published release (date, forced flag)
  and can install it — upgrades and downgrades alike — via the same
  licensed download path as normal updates
* Choosing anything but the newest release pins the product: forced
  updates, update badges, and the WP update transient all skip it until
  "Resume updates" is clicked (card or Settings → Updates). Picking the
  newest release unpins automatically
* EZIT_PIN_PRODUCTS in wp-config.php pins products before files are
  even swapped — the escape hatch for debugging on a manually installed
  older version
* License server: new /api/versions endpoint returns the release
  history powering the picker; any listed release remains downloadable
  through the existing download_update route

= 1.5.0 =
* Toasts moved to top-center below the admin bar — the top-right slot
  was easy to miss before auto-dismiss

= 1.4.9 =
* Forced releases actually apply: WP-Cron only runs on a hit, so a quiet
  site could sit on a forced update forever. Pending forced releases are
  now recorded in an option and the next admin page load on any Ez IT
  screen swaps straight to a full-screen updater — no tabs, real
  progress steps from WP's upgrader, retry and back links on failure
* Settings → Updates: new section with "Ignore all forced updates" and
  per-product checkboxes — the site's own opt-out, evaluated before the
  marketplace/license server flag is honored
* Update visibility: a "no update" response now caches for 15 minutes,
  not 12 hours — a release published between checks no longer stays
  invisible half a day. Marketplace Refresh also clears per-product
  update caches and the forced sweep, so it doubles as a manual
  "check now"

= 1.4.8 =
* Dashboard: EzIT Core shows Active with "Licensed to: Ez IT Solutions" —
  self products are license-free by design, no more INACTIVE/blank row.
  Licenses tab shows a matching read-only card; the "not licensed" admin
  nag no longer fires for self products
* Action notices (?ezit_msg) are now toasts: fixed top-right card with a
  circled-X dismiss button, auto-fades after 5s (7s for errors), and the
  query params are stripped so a refresh doesn't replay the toast

= 1.4.7 =
* Self-update out of the box: EzIT Core registers unconditionally and
  resolves its product_id/api_key lazily from the free catalog listing
  (EzIT_Core::self_credentials, 12h cache). Sites without constants now
  call check_update, register in product_sites, and accept update-push —
  forced releases reach them with zero licenses and zero configuration

= 1.4.6 =
* Marketplace: Install/Update buttons get a real busy state — dark
  spinner docked left of a readable label (data-busy-label), fixing the
  green-on-green spinner + transparent text that read as a blank pill

= 1.4.5 =
* Forced releases: update_for() arms the forced flag when a UI badge
  path surfaces it — a release no longer waits on the inject filter
  or the cron scan to be noticed

= 1.4.4 =
* Marketplace: card hover — lift + green ring + thumbnail brighten,
  matching the installed-theme cards

= 1.4.3 =
* Forced releases: new POST /wp-json/ezit/v1/update-push — the license
  server pushes a forced release and the site applies it within
  seconds (spawn_cron runs the upgrade in a background request)
* Forced releases: ezit_updates_scan tightened to a custom 15-minute
  schedule (self-reschedules from the old hourly event) as the
  fallback for sites a push can't reach
* Marketplace: installing over an existing plugin dir reports
  "updated" not "installed"; self products skip the license prompt

= 1.4.2 =
* Forced releases: ezit_updates_scan fresh-checks every product so a
  flag set after the last check propagates quickly instead of waiting
  on the 12h transient + an admin page load
* Marketplace: empty-state tile shows the product name (no initials)

= 1.4.1 =
* Marketplace: locally registered products (incl. EzIT Core itself)
  count as installed — no more Install button on running products
* Marketplace: branded empty-state tile (icon + initials) for
  listings without a thumbnail; self products badge as Core

= 1.4.0 =
* Self-update: EzIT Core registers itself as a plugin product and
  updates through the license server like every other product
* Forced releases: `check_update` `forced` flag auto-applies updates
  via WP-Cron without a manual click
* Plugin update support in the shared updater (plugins_api details
  overlay included)

= 1.3.1 =
* SSO users endpoint returns the site's registered role map
