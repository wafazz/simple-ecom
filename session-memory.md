# Session Memory - Basic Custom E-Commerce
> Last updated: 2026-08-27 04:30

## Session Context
- **Project**: Basic Custom E-Commerce
- **Profile**: `~/Desktop/CS/Projects/06-basic-ecom.md`
- **Branch**: master — Ph2 `43035bf`, Ph3 `23bb05a`, Ph4 `448979a`, Ph5 `4c31125`, Ph6 `735219d`, Ph7 `5c42880`, Ph8a `14e57a1`, Ph9 `8979096`
- **Status**: active — Planning.md APPROVED 2026-08-26. Phase 2 complete, Phase 3 next.
- **Focus**: Phase 10 — Security & Testing (full purchase flow). **8b still blocked on OQ-13.**

## Current Tasks
- [x] Phase 0 Intake — name, work mode, deploy target, database
- [x] Phase 1 Research (Scout) — version baseline + pattern library sweep
- [x] Init 3a–3f — profile, identity entry, stats label, session memory, Planning.md, docs/
- [x] Rewrite `Planning.md` against the client's 36-section spec (11 phases, Cart/Checkout Design sections)
- [x] **Planning.md APPROVED** by client 2026-08-26
- [x] **Phase 2 — Laravel 12 foundation** (installed, configured, verified, committed)
- [x] **Phase 3 — Database**: 10 tables, 4 enums, 10 models, 8 factories, 3 seeders
- [x] **Phase 4 — Core Laravel MVC**: routes, middleware, controllers, Form Requests, Blade layouts + components, error views, Money support
- [x] **Phase 5 — Product**: admin CRUD for categories/products/variations + stock, storefront catalogue + detail
- [x] **Phase 6 — Cart & Checkout**: CartService, cart screens, CheckoutRequest, order creation + confirmation
- [x] **Phase 7 — Payment**: ToyyibPayService (fail-closed), PaymentController, settlement transaction. **Built; cannot settle live until OQ-11**
- [x] **Phase 8a — Shipping rates**: EasyParcelService (OAuth + rotation mutex), quotations, flat-rate fallback, checkout rate picker, admin Integrations screen
- [x] **Phase 9 — Admin**: order list/detail/status/refund, settings, integrations screen
- [ ] **Phase 10 — Security & Testing**: full purchase-flow coverage, security sweep
- [ ] **Phase 11 — Deployment**: production instructions + client handoff
- [ ] **Phase 8b — Booking/AWB/tracking** (REQ-013). **BLOCKED on OQ-13**
- [ ] **OQ-13 blocks Phase 8b** — read `shipment/submit` + `shipment/pay` payloads from `github.com/easyparcel/OpenAPI` and record them. Booking code cannot be written first (§3)
- [ ] **OQ-03 first** — is EasyParcel on the Open API (OAuth) or legacy Connect (flat key)? Changes Phase 8 design + table count
- [ ] Verify ToyyibPay `getBillTransactions` field names against the official reference (human, browser)


## Working Memory

### Active Context — ENVIRONMENT FINDINGS (2026-08-26, Phase 2)
- ⚠ **Local DB is MariaDB 10.4.28 (XAMPP) on PORT 3307**, not MySQL 8.0 and not 3306. Local `.env` uses `DB_CONNECTION=mariadb` + `DB_PORT=3307`. `.env.example` keeps `mysql`/3306 for the VPS target. **Dev/prod engine divergence is an OPEN DECISION — see OQ-17.**
- ⚠ **No PHP 8.3 on this machine.** Herd provides 8.2 and 8.4 only; local runtime is 8.4.10. `config.platform.php = "8.3"` in composer.json is what keeps Composer resolving for the 8.3 target. **OQ-18.**
- Something else answers on port 3306 (returns "access denied") — not investigated, not ours.
- Installed: Laravel **12.68.0**, PHPUnit **11.5.56**, 38 vendor packages. `composer audit` clean.

### Active Context
- **`Prompt.txt` is the client's own document** (36 sections, replaced mine at 22:31). It is authoritative — do not edit it.
- Spec section map: §5 stack · §9 products · §10 cart · §11 checkout · §12 ToyyibPay · §13 EasyParcel · §17 security · §18 DB · §19 structure · §20 architecture · §25 Planning.md contents · §27 phases (**11**) · §30 dependencies · §31 env · §32 testing · §33 deploy · §35 first-action halt.
- 10 application tables + Laravel's `migrations` only.
- The two integration services are the expensive code. Everything else is Laravel conventions.

### Decisions Made
- **Laravel 12 + PHP 8.3** — client-mandated (§5).
- **VPS deploy**, **MySQL 8.0**, **client project** — settled at intake. MariaDB `renameColumn()` trap does not apply.
- **Session cart** (§10), keyed by `variant_id`; only `variant_id` + `qty` stored, price always re-read from DB.
- **Guest checkout** (§11); all totals computed server-side.
- **Variant design**: denormalised option columns on `product_variants`, `UNIQUE(product_id, option1_value, option2_value)`, unused slots `''` never `NULL`. Option dictionary documented as a non-destructive later upgrade.
- **`users` table reused for the admin** — §16 says use Laravel's standard auth, no large auth ecosystem.
- **Backed enums** for order/payment status; DB columns stay `VARCHAR`.
- **No SoftDeletes** on models with a UNIQUE slug/sku — `is_active` instead.
- **Three services only**: ToyyibPayService, EasyParcelService, CartService. No factories, no repositories (§22).
- **EasyParcel = rates AND booking.** Client moved shipment booking / AWB / tracking **into scope** (REQ-013) on 2026-08-26. Adds `shipments` (10th table), `ShipmentController`, `ShipmentStatus` enum, reconciliation screen. Estimate ~9 → ~11 days.
- **Booking is an ADMIN ACTION, not automatic on payment** — keeps real-money spend out of the ToyyibPay callback path (Planning §11.B.5.2).
- **`UNIQUE(shipments.order_id)`** is the anti-double-booking guard; an ambiguous `pay` outcome goes to `needs_reconciliation` and is **never auto-retried**.
- **No CI/CD pipeline** (§33) — the MySQL test run is a release step, not a pipeline.

### Blockers / Open Questions
- **Approval gate** — §35: no code until `Planning.md` is approved.
- **OQ-01** product `weight_g` — EasyParcel requires weight; spec never mentions it. Blocks REQ-006.
- **OQ-02** pickup origin postcode + state.
- **OQ-03** **EasyParcel Open API (OAuth) vs legacy Connect (flat key)?** §31 lists `EASYPARCEL_API_KEY` (legacy); verified docs describe OAuth. Legacy is ~1 day cheaper and drops `integration_tokens`. **Answer this before Phase 8.**
- **OQ-04** flat-rate fallback fee.
- **OQ-05** customer email confirmation — in scope or not?
- **OQ-06** one product image or many. **OQ-07** confirm MYR.
- **OQ-08** Laravel 12 past bug-fix EOL (2026-08-13) — who budgets the major upgrade?
- **OQ-09** recurring VPS cost (~RM25–60/mo) is outside the RM1,000 build budget.
- **OQ-10** asset pipeline — remove Vite (recommended) vs keep the §19 folder list.
- **OQ-11** human verification of ToyyibPay response field names. Blocks any real payment settling.
- **OQ-12** who funds/monitors the EasyParcel **credit balance**? Booking stops working when it empties. Recurring cost outside RM1,000.
- **OQ-13** booking payloads unverified — **blocks Phase 8b entirely**.
- **OQ-14** booking trigger: admin action (planned) vs automatic on payment.
- **OQ-15** label PDF: store URL (planned) vs re-host. **OQ-16** pickup date/address fields.
- **OQ-17** **dev/prod DB engine divergence** — local MariaDB 10.4.28 vs VPS MySQL 8.0. Match them, or accept and test on both?
- **OQ-18** PHP 8.3 absent locally (8.4.10 in use). Install 8.3, or develop on 8.4 with the platform pin holding the line?

## Recent Changes
| File | Change | Status |
|---|---|---|
| `Prompt.txt` | Replaced by the client with their own Laravel 12 / PHP 8.3 spec | done (client) |
| `Planning.md` | Written against the 36-section spec; §25 sections satisfied, §27 phases aligned; **REQ-013 booking added** | done |
| `docs/documentation.md` | Created + reconciled to the client's spec numbering | done |
| `session-memory.md` | Created + reconciled | done |
| `~/Desktop/CS/Projects/06-basic-ecom.md` | Project profile | done |
| `~/Desktop/CS/00-identity.md` | Registered under Active Projects | done |
| `~/.claude/project-labels.json` | Stats label registered | done |

## Session Recap
> This section survives resets. Keep it under 30 lines.

### What Was Done
- **2026-08-26 late**: client moved EasyParcel **booking/AWB/tracking into scope** (REQ-013). Planning.md, docs and profile updated; not merely a table row flipped — booking spends real courier credit, so the design adds a write-record-first guard, a `needs_reconciliation` state and an admin reconciliation screen.
- Ran CoreSentinel Init Protocol Phases 0–4 against `Prompt.txt`. Halted at Phase 5 by spec §35.
- The client replaced `Prompt.txt` mid-session with their own 36-section Laravel spec; `Planning.md` and `docs/` were rewritten against it rather than left stale.
- Scout verified: **Laravel 12 left bug-fix support 2026-08-13**; Laravel 13 is current; local PHP is 8.4.10 so `config.platform.php = "8.3"` is load-bearing; Composer 2.8.10 present; Bootstrap 5.3.8 current.
- Applied 5 patterns from `11-pattern-library.md`: atomic race-free guard, integer minor units, variants-without-EAV, soft-deletes/unique-index, encrypted secrets at rest.

### Phase 8a corrections
- `shipping_service_id` is validated input but **not** an `Order` attribute — mass assignment threw until it was excluded explicitly. The guard working as designed.
- `$quote` was used inside the order-creation closure without being captured in `use (...)`.

### Phase 7 note — the fail-closed contract
- `ToyyibPayService::verifyPayment()` returns `unverified` for any shape it cannot positively recognise, and the order **stays pending**. If someone later reports "payments don't settle", check OQ-11 BEFORE treating it as a bug — this is deliberate.
- Amount unit is also unconfirmed: `TOYYIBPAY_AMOUNT_FORMAT` (`decimal`|`cents`). A wrong value causes a MISMATCH refusal, never a silent wrong charge. The mismatch log prints both interpretations so the right setting is obvious.
- Correction made: the `ToyyibPayService` container binding silently no-op'd because Pint rewrote the FQCN to the imported short form between my edits. Python `str.replace()` fails silently — assert on the anchor.

### Phase 6 corrections
- Order numbers first used `random_int(1,9999)` — ~50% collision by ~120 orders/day — and the docblock claimed a retry the code never implemented. Now sequential within the day, with the UNIQUE key as the guard and a real retry loop.
- `CartController::store()` read `qtyFor()` **after** `add()` when deciding whether the quantity was capped, so it always claimed "capped". Now captures the before-value.
- `app/Services/` did not exist, so the first heredoc write silently produced nothing.

### Bugs found by tests in Phase 5 (both real, both fixed)
- `Product::getRouteKeyName()` returns `slug` for pretty storefront URLs, so **every admin product route was binding by slug**. Admin routes now pin `{product:id}` — renaming a product must not change its admin URL.
- `{variation:id}` turned on Laravel's scoped bindings, which looked for `Product::variations()`; the relation is `variants()`. Route param renamed to `{variant}`.

### Bugs found by tests in Phase 4 (both real, both fixed)
- View composer registered only on `layouts.*` → `$storeName` undefined in child views that render it in their own section. Now registered on `['layouts.*','storefront.*','admin.*']`.
- `components/alerts.blade.php` assumed `$errors` exists. An unmatched URL never passes through the `web` group, so `ShareErrorsFromSession` never ran and **every 404 returned a 500**. Component is now defensive.
- Also corrected: `Setting::all()/value()` clobbered Eloquent's own API (incompatible static signature) → renamed to `cached()/get()/getInt()`. And a CSRF test asserted the wrong property — Laravel 11+ stores `validateCsrfTokens(except:)` in the STATIC `$neverVerify`, not the instance `$except`.

### Where We Left Off
- **Phases 2, 3 and 4 done and committed** (`43035bf`, `23bb05a`, `448979a`).
- **32 tests / 73 assertions green** on SQLite; the 24 guard tests **re-run green against real MariaDB 10.4.28** (Planning §16 requires this — SQLite does not tell the truth about guarded UPDATEs). Pint clean, `composer audit` clean.
- Proven by test, not assumed: variant combination uniqueness incl. the two-option-less-variants NULL trap · atomic stock decrement refusing to oversell · idempotent paid transition · shipment double-booking rejected · `needs_reconciliation` never retryable · tokens encrypted at rest and hidden from `toArray()`.
- Test DB `basic_ecom_test` exists on port 3307 for the MariaDB run.
- **76 tests / 160 assertions green on SQLite AND MariaDB 10.4.28.** Pint clean, audit clean. Routes smoke-tested live: `/` `/order-status` `/admin/login` `/up` 200, `/nope` 404, guest `/admin` → 302 to login, `X-Request-Id` present.
- Working now: admin login (throttled, is_active in credentials, session regeneration), dashboard with 4 counters, public order lookup gated on order-no + matching email, Bootstrap 5.3.8 vendored locally, `App\Support\Money` for integer sen.
- **101 tests / 237 assertions green on SQLite AND MariaDB 10.4.28.**
- Live smoke test passed: `/products`, category filter, both seeded products, admin auth-gate, admin catalogue screens after login.
- Working now: admin category/product/variation CRUD with deactivate-not-delete, stock adjustment (logged), image upload to the `uploads` disk (public/uploads, no storage:link), storefront listing with cheapest-variant price and the per-combination detail table.
- **124 tests / 297 assertions green on SQLite AND MariaDB 10.4.28.**
- **Live E2E passed**: add 2× T-Shirt M/Black → cart → checkout → `ORD-20260826-0001` created `pending_payment/pending`, 6000+1000=7000, snapshot correct, **stock still 20 (correctly not decremented)**.
- Working now: session cart (prices always re-read from DB), quantity clamping, checkout with ISO 3166-2:MY state select, server-side totals, order + snapshot, confirmation page.
- Shipping is the **flat rate from settings** until Phase 8 wires EasyParcel quotations. `orders.shipping_rate_source` is set to `'flat'`.
- **139 tests / 343 assertions green on SQLite AND MariaDB 10.4.28**, 15 payment-specific, all `Http::fake()` — no live calls.
- **Live: a forged callback returned 200 but settled nothing** — order stayed pending, stock untouched.
- Proven by test: verified payment settles + decrements once · duplicate callback decrements exactly once · forged callback ignored · amount mismatch refuses · reference mismatch refuses · unrecognised shape leaves pending · HTML error page leaves pending · gateway outage leaves pending · oversell → `needs_review` · unknown bill code answered 200.
- **159 tests / 400 assertions green on SQLite AND MariaDB 10.4.28**, 20 shipping/OAuth-specific, all `Http::fake()`.
- Proven by test: decimal-string → sen conversion · cheapest-first ordering · ISO 3166-2 codes in the request · flat-rate fallback on API failure / non-JSON body / disconnected / failed refresh · **refresh token rotation persisted** · `state` nonce mismatch rejected and single-use · tokens encrypted at rest and never rendered · **chosen courier re-priced server-side, posted price ignored** · unavailable service falls back to flat.
- ⚠ **Weight unit is unverified (OQ-13)** — `EASYPARCEL_WEIGHT_UNIT` defaults to `kg`.
- **179 tests / 469 assertions green on SQLite AND MariaDB 10.4.28.**
- **Key admin constraint, tested**: the admin **cannot mark an order paid** — payment status is gateway-driven only. The single permitted payment-status change is recording a refund, allowed only from `paid`, moving no money.
- Live: all six admin screens 200 after login; order detail renders the snapshot, resolves `MY-07` → Pulau Pinang, shows the flat-rate badge, hides "Mark as refunded" on an unpaid order.
- Next: **Phase 10 — Security & Testing.** Full purchase-flow coverage per spec §32, security sweep per §17. Then **Phase 11 deployment + client handoff** (`52-handoff-protocol.md`). **8b booking stays blocked on OQ-13.**

### Key Context for Next Session
- **The payment path fails closed on purpose.** If payments don't settle in testing, check `Planning.md` §11.A.6 before assuming a bug.
- **OQ-03 is the highest-leverage question** — it changes the Phase 8 design and whether `integration_tokens` exists at all.
- Full squad (17 agents) runs every phase — Fakrul's standing order. Empty findings are valid results; padded ones are not.
- Backups of the superseded documents are in the session scratchpad (`Planning.md.vanilla-php80.bak`, `Prompt.txt.vanilla-php.bak`).
