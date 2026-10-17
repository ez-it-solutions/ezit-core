# License Server — Phase 3+ Build Spec

What `license.ez-it-solutions.com` needs to build for core's Marketplace,
Support, and Payments phases. Client-side (`ezit-core`) is written against
these contracts — build to these shapes and the sites light up with no
core changes.

**Reference client:** `core/ezit-core/inc/class-ezit-api.php` +
`class-ezit-marketplace.php`.

---

## 1. Communication protocol (already live — do not change)

Every request from a site carries the LicenseBox header contract:

| Header | Value |
|---|---|
| `LB-API-KEY` | the product's `api_key` (per-product, from the dashboard) |
| `LB-URL` | the site's `home_url()` |
| `LB-IP` | `$_SERVER['SERVER_ADDR']` |
| `LB-LANG` | site locale (e.g. `en_US`) |

- POST endpoints use `application/x-www-form-urlencoded` bodies and return
  `{status: bool, message: string, ...}` JSON.
- Errors: HTTP ≥ 400 with `{"message": "…"}` — core maps that to WP_Error
  and shows `message` to the admin.
- `license_file` / `lic_response` = `<64 hex hmac><base64url(json)>` —
  core decodes the JSON tail for display only. Any fields you add to the
  cert payload (e.g. `features[]`, `max_activations`, `expires_at`,
  `domain`, `client_name`) are automatically surfaced in wp-admin —
  **additive changes are free; never rename/remove existing keys.**

### Server → site push (already live)

`POST {site}/wp-json/ezit/v1/license-push` with `key={product api_key}`
forces an immediate re-verify. Call it whenever a license status/feature
set changes so sites converge in seconds instead of at the next cron.

### Reason codes (client ≥ 1.2.0)

Failed `activate_license` / `verify_license` responses may carry
`status_code` — core persists it and maps it to admin-facing copy:

| `status_code` | Client behavior |
|---|---|
| `PRODUCT_DISABLED` | "Product retired" — activate form disabled, theme-switch to the product blocked |
| `ACTIVATION_DISABLED` | "Activation disabled server-side" — form stays open for a different code |
| `LICENSE_SUSPENDED` / `LICENSE_EXPIRED` / `LICENSE_INACTIVE` | Labeled notice; form open |
| `DOMAIN_NOT_ACTIVATED` | "Activate this license for this domain" prompt |

Unknown/missing codes fall back to the response `message`. Always send
`message` as the human string; `status_code` is the machine discriminator.

### Recovery code + digest (client ≥ 1.2.0 — server must ship)

Each product needs a **recovery code** — a human-readable secret
(e.g. `XXXX-XXXX-XXXX-XXXX`) generated per product and shown in the
dashboard under Products → Recovery code. Site admins use it to arm the
emergency gate override *while the server is offline*, so validation is
fully offline:

```
recovery_digest = HMAC-SHA256( "recovery|" + UPPERCASE(trim(code)), product.api_key )
```

Ship the digest (never the raw code) in **two places**:

1. Inside the signed cert payload as `recovery_digest` — bound to the
   product by the cert HMAC.
2. Top-level in every `verify_license` response — so a site picks it up
   on routine verify without needing a re-activation.

Client behavior: `EzIT_Gate::recovery_code_valid()` recomputes the HMAC
against any registered product's stored digest (`hash_equals`) — any
product's code arms the override on its site. If no digest is on file
yet the arming UI shows "No recovery code on file" — additive rollout
is safe; old clients ignore the field.

Storage/rotation (already live server-side): raw codes are show-once +
sha256-hashed; rotating a product's `api_key` automatically mints a new
recovery code — so key rotation rotates the digest too, and the old code
stops working site-side once the new digest arrives via cert/verify.

### Maintenance switch (client ≥ 1.2.0)

A global outage switch the license dashboard can flip. While on, **every**
`/api/*` endpoint answers:

```
HTTP 503
Retry-After: <seconds until expected restoration>
{ "status": false, "status_code": "SERVER_UNAVAILABLE",
  "message": "License server is down for maintenance" }
```

Client behavior: `EzIT_API::post()` maps `503 + SERVER_UNAVAILABLE` to
`WP_Error('ezit_server_unavailable', …, {retry_after, http})`; the client
then keeps every product's last verdict, stamps `server_state=maintenance`,
paces re-checks to `Retry-After` (5 min floor, 1 h cap), and shows a
distinct "maintenance" banner instead of "unreachable". Any other failure
shape (timeout, 5xx without the code, malformed JSON) classifies as
`down` — same preservation, same pacing.

Note: while the switch is on, even `verify_license` answers 503 — that's
the point. No verdicts are issued during a window, and client-side
verdict preservation means nothing is revoked. A real denial outside a
maintenance window still requires a normal `status:false` JSON.

---

## 2. Already implemented (nothing to build)

`check_connection_ext`, `activate_license`, `verify_license`,
`deactivate_license`, `check_update`, `get_update_size/{id}`,
`download_update/main/{id}`, `get_config` (+ Server Config admin tab).

### `POST /api/get_config` — published server config (client ≥ 1.1.0)

```json
{
  "status": true,
  "config_version": 1,
  "config": {
    "api_base": "https://license.ez-it-solutions.com/api/",
    "endpoints": { "verify_license": "verify_license" },
    "services":  { "marketplace": "…", "support": "…" },
    "notices":   []
  }
}
```

- `config_version` rides every `verify_license`/`check_update` response;
  bump it to force sites to re-read (hourly staleness floor otherwise).
- `api_base` repoints ALL client calls — **trust rule**: clients only
  honor HTTPS hosts under `*.ez-it-solutions.com` (`ezit_allowed_api_hosts`
  filter for customer mirrors). Publishing an off-platform base is ignored.
- `endpoints` remaps *bare* endpoint names only — parameterized paths
  (`download_update/main/x`) pass through unchanged.

---

## 3. PHASE 3 — Marketplace (the blocker)

### `GET /api/catalog` — **build first**

Public listing, no auth needed beyond the `LB-*` headers (reject requests
missing `LB-URL` entirely if you want a soft gate).

```json
{
  "items": [
    {
      "slug": "trezise-insurance",
      "type": "theme",
      "name": "Trezise Insurance",
      "version": "1.2.0",
      "thumb": "https://license.ez-it-solutions.com/media/thumbs/trezise.png",
      "excerpt": "Insurance agency theme — one-page, contact-first.",
      "category": "business",
      "price": 0,
      "product_id": "F43D2273",
      "installed": false
    }
  ]
}
```

Core reads `items[]` and renders cards; every field optional except
`slug`, `name`, `product_id`. `installed` is a nice-to-have (server can
match `LB-URL` against activation records).

### `GET /api/catalog/{slug}` — detail for the install flow

```json
{
  "slug": "trezise-insurance",
  "name": "Trezise Insurance",
  "type": "theme",
  "version": "1.2.0",
  "product_id": "F43D2273",
  "api_key": "…",                // needed so core can register_product()
  "description": "…html…",
  "screenshots": ["https://…"],
  "changelog": "…",
  "requires": "7.4",             // min PHP (optional)
  "download_endpoint": "download_update/main/F43D2273"
}
```

`api_key` here is the product's server-side key — returning it is safe
(the site needs it to activate), but only return it for items flagged
installable/free; paid items return it after purchase.

### Server-side admin CRUD to support this

- **Catalog item** = a product + marketing fields (thumb, excerpt,
  category, screenshots). One product may exist without a catalog entry
  (internal products); a catalog entry may be `published: false`.
- **Categories** — flat list, mirror Envato-ish: `business, church,
  school, athletics, gov, dental, law, portfolio, generic`.
- **Version bumps** — catalog `version` should drive `check_update`
  responses; keep one source of truth.

### Auth model for install

Core's install flow will call `download_update/main/{product_id}` — it
already exists. Decide per item:

- **Free item** → return the zip for any request with valid `LB-URL`.
- **Paid item** → require `license_file` of a purchased license (same
  check as updates). Returns HTTP 402/403 + `{"message": "Purchase required."}`.

---

## 4. PHASE 4 — Support tickets & announcements

### `GET|POST /api/support/tickets`

Auth: `license_file` in the body (same as verify) — a ticket belongs to a
license+site pair.

- `GET` → `{"tickets":[{"id","subject","status":"open|answered|closed","updated_at","replies":[{"from":"client|staff","body","at"}]}]}`
- `POST` → `{"license_file","product_id","subject","body"}` creates;
  `{"license_file","ticket_id","body"}` replies.
- Core renders a Support admin page; keep `status` values stable.

### `GET /api/announcements?product_id=…`

Broadcast feed for the dashboard banner module (`class-ezit-banners.php`
already caches a transient — wire it to this):

```json
{"items":[{"id","title","body","severity":"info|warning|critical",
"starts_at","ends_at","product_id"|null,"dismissible":true}]}
```

Null `product_id` = broadcast to all products. Site dismisses by `id`.

---

## 5. PHASE 5 — Payments

Site never stores cards; Invoice Ninja (billing.ez-it-solutions.com) is
the payment frontend. License server only needs:

- `POST /api/purchase` → `{"product_id","license_file"|null}` → returns
  `{"checkout_url": "https://billing.ez-it-solutions.com/…"}` —
  core deep-links, Stripe/etc. handled by Invoice Ninja.
- **Webhook handler** — on Invoice Ninja payment events: issue/extend
  license, update `features[]` in the cert, then POST
  `license-push` to every activated site (instant propagation).

---

## 6. Cert payload — fields the client already displays

Core's Licenses tab decodes and renders these (all optional):

`client_name`/`registered_name`, `domain`, `expires_at`/`expiry_date`,
`max_activations`, `used_activations`, `features[]` (Phase 9).

Add them to the cert as they become available — no client deploy needed.

---

## 7. Build order

1. `GET /api/catalog` (+ minimal catalog CRUD in the dashboard)
2. `GET /api/catalog/{slug}` with `api_key` for free items
3. Install flow in core (client-side — starts once #2 returns)
4. Announcements feed → banners module
5. Support tickets
6. `/api/purchase` + Invoice Ninja webhook → license-push

Items 1–2 unblock the entire Phase 3 client.
