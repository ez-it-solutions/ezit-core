# Billing & Customer Management — detailed roadmap (Phase 9)

> Status: **planning** — nothing here is built yet. This document is the
> design surface; decisions marked ❓ need a call before build-out.

Billing lives at **billing.ez-it-solutions.com (Invoice Ninja)**. The site
never stores cards, never runs a checkout — no WooCommerce, minimal PCI
surface. The license server is the only thing that talks to the billing
server; sites only ever talk to the license server they already trust.

## Actors & trust boundaries

```
Invoice Ninja          License server           Client WP site
(billing.ez-it-…)      (licensing API)          (Ez IT Core)

clients, invoices, ──> customers, licenses, <── verify / activate /
payments, portal       activations, certs       update / catalog
        ▲                      ▲
        └── REST API + webhooks (server-to-server, signed)
```

- **Invoice Ninja** — money. Clients, recurring invoices, payment
  gateways (Stripe et al.), the client portal, card updates.
- **License server** — truth. Customers, licenses, entitlements, site
  activations, signed certs. It consumes billing events and turns them
  into license state.
- **Ez IT Core on the site** — display only. Reads entitlements from the
  stored cert; deep-links into the portal for anything payment-related.

**Hard rule:** the site never calls Invoice Ninja. Every billing fact a
site needs arrives inside the existing verify/cert payload.

## Phase 9a — Customer model (license server)

Licenses currently float free of people. First we need customers.

- **`customers` table** — name, email(s), company, phone, notes, tags,
  `invoice_ninja_client_id`, `created_at`. One customer → many licenses
  → many site activations. The same person can hold a theme license,
  a plugin license and a second site without duplicating the record.
- **License → customer link** — `licenses.customer_id` (nullable:
  internal/free/test licenses need no customer). The license stays the
  unit sites verify; the customer is the unit we bill and email.
- **Admin CRUD** — customer list (search, filter by tag/license status),
  detail page showing their licenses, sites, IN client link, and
  outstanding invoices (pulled live from IN, never stored).
- **Import / upload** — CSV import with a preview step: column mapping,
  dedupe by email (existing customer → link, don't clone), dry-run
  report ("12 create, 3 merge, 1 skip") before commit. Also the path to
  onboard legacy LicenseBox customers.
- **IN client mapping** — link by `client_id` when known, else match by
  email; "create IN client" one-click when missing. The mapping is
  explicit — fuzzy auto-linking is a footgun.

❓ **Decide:** does a customer auto-create on license *activation* (site
self-registers with the activating email) or only manually/import?
Auto-create is smoother, manual is cleaner data.

## Phase 9b — License server ↔ Invoice Ninja sync

Server-to-server only. Both directions:

**Inbound — IN → license server (webhooks):**
`invoice.paid`, `payment.failed`, `invoice.cancelled`, subscription
events. Each webhook → mapped customer → affected license(s) → status +
`features` transition. Sites learn of it on their next `verify_license`
(no push needed — the existing check cadence carries it).

- **Security** — HMAC signature header + timestamp; reject unsigned,
  stale (>5 min) or replayed events (store event ids).
- **Idempotent** — the same webhook twice is a no-op; status transitions
  record *who* (webhook/manual/cron) for the audit trail.

**Outbound — license server → IN (REST API):**
- New paid license → ensure IN client exists → create recurring
  invoice/subscription → the invoice id lands back on the license.
- Cancel/refund → credit note or invoice void → license status follows
  the same path as `payment.failed`.
- Renewal date on the cert = IN's next invoice date (sites show it in
  the Billing tab without a live call).

**Reconciliation** — a daily pull job diffs IN invoice status vs license
status for every paid license and reports drift. Webhooks are the fast
path; reconciliation is the safety net.

## Phase 9c — Entitlements (features ride the cert)

- **Cert gains `features:[]`** — e.g. `studio`, `page_builder`,
  `footer_credit_off`, `custom_login`, `alerts`. Set server-side per
  product/plan; sites just read them.
- **`ezit_feature_enabled($feature)`** — public API for themes/plugins.
  Products gate UI on features, not on "is licensed" — an unlicensed
  site can run a degraded experience instead of a hard wall.
- **License status changes already flow** — missed payment → server
  flips status/features → next verify updates the site → gate/feature
  machinery reacts. Entitlements are payload, not plumbing.

## Phase 9d — Billing tab (site-side, minimal)

- Shows: plan/features (decoded from the stored cert — no live call),
  renewal date, license status.
- **Portal deep-link** — a signed one-time URL minted by the license
  server that bounces into the IN client portal; no IN credentials on
  the site.
- Visible to the **owner** role (Phase 10 caps) — this is the one core
  screen a client should see alongside their theme tools.

## Phase 9e — Dunning, grace & overrides

- **Missed payment → grace, never instant kill** — `past_due` state
  with a configurable grace window (default mirrors the existing
  `ezit_license_grace_days`); premium features suspend first, core
  licensing never dies mid-grace.
- **Payment received → auto-restore** — features flip back on the same
  webhook path; the site self-heals on next verify.
- **Manual override** — admins can pause/restore/comp a license with a
  reason; every transition is audited (who, when, why).
- **Comms** — the license server emails the customer on
  past_due/grace_end/suspended (IN already emails on invoice events —
  ❓ dedupe: who owns which email?).

## Phase 9f — Data hygiene

- **PII placement** — financial data lives only in IN; contact PII lives
  on the license server; sites store *no* customer data at all.
- **Export/delete** — per-customer export (their licenses + sites) and
  anonymize-on-request (keep license history, strip identity) — needed
  for GDPR-adjacent asks.
- **Backups** — customer and license tables join whatever the license
  server's backup story is; IN handles its own.

## Build order

1. **9a** customer model + CSV import — unlocks real data entry today
2. **9b** webhooks + IN client mapping — money events start flowing
3. **9c** entitlements — sites get feature-gated tiers
4. **9e** dunning/grace — needs 9b's status transitions to hang on
5. **9d** Billing tab — thin display layer once data exists
6. **9f** hygiene tooling — alongside, not last (export is cheap early)

## Open questions to settle before build

- ❓ Customer auto-creation on activation vs manual only (9a)
- ❓ Which billing emails IN sends vs the license server (9e)
- ❓ Feature list per product/plan — where is that authored, the product
  record or the plan/tier record?
- ❓ Multi-site discounts / agency bundles — is that an IN construct
  (invoice line items) or a license-server construct (one license, N
  seats)? Current model is seats-per-license — probably stays.
- ❓ Do we need proration/mid-cycle upgrades in v1, or is every plan a
  flat recurring invoice until Phase 9 ships further?
