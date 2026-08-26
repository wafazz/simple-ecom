# Session Memory - Basic Custom E-Commerce
> Last updated: 2026-08-26 23:05

## Session Context
- **Project**: Basic Custom E-Commerce
- **Profile**: `~/Desktop/CS/Projects/06-basic-ecom.md`
- **Branch**: (not a git repo yet — `git init` before Phase 2)
- **Status**: blocked — awaiting approval of `Planning.md` §21
- **Focus**: Init Protocol complete. `Prompt.txt` §35 halts all implementation until the plan is approved.

## Current Tasks
- [x] Phase 0 Intake — name, work mode, deploy target, database
- [x] Phase 1 Research (Scout) — version baseline + pattern library sweep
- [x] Init 3a–3f — profile, identity entry, stats label, session memory, Planning.md, docs/
- [x] Rewrite `Planning.md` against the client's 36-section spec (11 phases, Cart/Checkout Design sections)
- [ ] **BLOCKED**: client approval of `Planning.md` §21
- [ ] **OQ-13 blocks Phase 8b** — read `shipment/submit` + `shipment/pay` payloads from `github.com/easyparcel/OpenAPI` and record them. Booking code cannot be written first (§3)
- [ ] **OQ-03 first** — is EasyParcel on the Open API (OAuth) or legacy Connect (flat key)? Changes Phase 8 design + table count
- [ ] Verify ToyyibPay `getBillTransactions` field names against the official reference (human, browser)
- [ ] Phase 2 — Laravel 12 foundation

## Working Memory

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

### Where We Left Off
- Everything planned and documented. **Nothing scaffolded** — no Laravel install, deliberately, per §35.
- Next on approval: Phase 2 (Laravel foundation) → Phase 3 (migrations/models/seeders).

### Key Context for Next Session
- **The payment path fails closed on purpose.** If payments don't settle in testing, check `Planning.md` §11.A.6 before assuming a bug.
- **OQ-03 is the highest-leverage question** — it changes the Phase 8 design and whether `integration_tokens` exists at all.
- Full squad (17 agents) runs every phase — Fakrul's standing order. Empty findings are valid results; padded ones are not.
- Backups of the superseded documents are in the session scratchpad (`Planning.md.vanilla-php80.bak`, `Prompt.txt.vanilla-php.bak`).
