# Module / System Documentation: Basic Custom E-Commerce

> **Status**: Active | **Last Updated**: 2026-08-26 | **Maintainer**: Iris / CoreSentinel

> **Planning.md approved 2026-08-26. Phases 2–3 complete** (commits `43035bf`, `23bb05a`).
> The database layer — migrations, enums, models, relationships, factories, seeders — is
> built and tested. Controllers, Blade views and the two integration services are still
> **planned targets**; they land in Phases 4–9. Sections are filled in as each phase
> lands — see §5.

All `§` references point at **`Prompt.txt`** (36 sections) unless prefixed "Planning".

---

## 1. Overview & Purpose

A single-vendor e-commerce storefront for a small business on **Laravel 12 / PHP 8.3 / MySQL 8.0**, deployed to a VPS.

**Business problem**: no way to sell online. Needs a catalogue with size/colour variations, guest checkout, Malaysian online-banking payment (ToyyibPay/FPX), real courier rate quotes **and shipment booking with AWB + tracking** (EasyParcel), and a simple admin panel — inside an **RM1,000** build budget.

**Requirements covered**: `REQ-001` … `REQ-013` (`../Planning.md` §1).

**Governing rule** (§36): at every stage — *is this actually required for the MVP?* If no, it is not built.

**Not built**: customer accounts · discounts · reviews · multi-currency · in-app refunds · email notifications · faceted filtering · persistent cart · warehouse management · queues. Reasons in `../Planning.md` §3.2.

---

## 2. Architecture & File Structure

Standard Laravel 12 structure (§19). Route → Controller → Form Request → Eloquent/Service → Blade. No repository layer, no interface over either integration service (§22).

### 2.1 Storefront

| File / Component | Type | Purpose & Responsibility |
|---|---|---|
| `app/Http/Controllers/HomeController.php` | Controller | Landing page |
| `app/Http/Controllers/ProductController.php` | Controller | Catalogue listing, category browse, product detail + variant selector |
| `app/Http/Controllers/CartController.php` | Controller | Add / update qty / remove; delegates to `CartService` |
| `app/Http/Controllers/CheckoutController.php` | Controller | Customer + address capture; order creation inside `DB::transaction()` |
| `app/Http/Controllers/ShippingController.php` | Controller | AJAX rate quote; delegates to `EasyParcelService` |
| `app/Http/Controllers/PaymentController.php` | Controller | ToyyibPay redirect, return handler, callback handler. **Never trusts the payload** |
| `app/Http/Controllers/OrderStatusController.php` | Controller | Public order lookup by order number + email |

### 2.2 Admin (§16)

| File / Component | Type | Purpose & Responsibility |
|---|---|---|
| `app/Http/Controllers/Admin/AuthController.php` | Controller | Login / logout on Laravel's auth guard, `throttle:5,1` |
| `app/Http/Controllers/Admin/DashboardController.php` | Controller | Four counters: total / pending / paid orders, total sales |
| `app/Http/Controllers/Admin/CategoryController.php` | Controller | Category CRUD + deactivate |
| `app/Http/Controllers/Admin/ProductController.php` | Controller | Product CRUD + deactivate, image upload |
| `app/Http/Controllers/Admin/VariationController.php` | Controller | Variant CRUD, stock edit |
| `app/Http/Controllers/Admin/OrderController.php` | Controller | Order list, detail, status update |
| `app/Http/Controllers/Admin/ShipmentController.php` | Controller | **Book shipment** (POST, admin-only — spends real credit), shipment list, reconciliation screen, label link (REQ-013) |
| `app/Http/Controllers/Admin/SettingController.php` | Controller | Store settings; credential **status** only, never values |
| `app/Http/Controllers/Admin/IntegrationController.php` | Controller | EasyParcel connect / callback / disconnect *(only if the account is on the Open API — OQ-03)* |

### 2.3 Domain models (§20)

| File | Purpose & Responsibility |
|---|---|
| `app/Models/User.php` | Admin. Laravel's default table + `is_active`. No registration routes |
| `app/Models/Category.php` | `slug` UNIQUE, `is_active`. `hasMany(Product)` |
| `app/Models/Product.php` | **No price column** — price lives on the variant. `belongsTo(Category)`, `hasMany(ProductVariant)` |
| `app/Models/ProductVariant.php` | The purchasable unit. Holds the guarded atomic stock decrement |
| `app/Models/Order.php` | Enum-cast `order_status` / `payment_status`. `hasMany(OrderItem)`, `hasOne(Payment)` |
| `app/Models/OrderItem.php` | Immutable purchase-time snapshot |
| `app/Models/Payment.php` | Gateway audit trail. `provider_ref` UNIQUE |
| `app/Models/Setting.php` | Key/value config. **Non-secret only**. `updateOrCreate()` |
| `app/Models/IntegrationToken.php` | EasyParcel OAuth tokens, `encrypted` cast *(OQ-03)* |
| `app/Models/Shipment.php` | Courier booking, AWB, tracking. **`UNIQUE(order_id)`** — the anti-double-booking guard. `belongsTo(Order)` |
| `app/Enums/ShipmentStatus.php` | Backed string enum incl. **`needs_reconciliation`** (Planning §11.B.5.4) |
| `app/Enums/OrderStatus.php` | Backed string enum (§14) |
| `app/Enums/PaymentStatus.php` | Backed string enum, separate from order status (§14) |

### 2.4 Services (§22) — three, each justified

| File | Justification |
|---|---|
| `app/Services/ToyyibPayService.php` | External integration. API communication, response normalisation, meaningful exceptions, failure logging. **Fails closed** on any unrecognised response shape |
| `app/Services/EasyParcelService.php` | External integration. Quotations, **booking (`submit` + `pay`), AWB retrieval, tracking**, OAuth token lifecycle, flat-rate fallback. One service, not split by operation (§22) |
| `app/Services/CartService.php` | Session-cart logic genuinely shared between the cart and checkout controllers — not a service-per-model |

**Not built** (§22): `UniversalPaymentProviderFactory`, `UniversalShippingProviderFactory`, `AbstractIntegrationManager`, `IntegrationOrchestrator`, repositories, interfaces over either service.

### 2.5 Requests, middleware, config

| File | Purpose |
|---|---|
| `app/Http/Requests/CheckoutRequest.php` | Customer + address validation, reused by the quote and submit paths |
| `app/Http/Requests/{Product,Variation,Setting}Request.php` | Admin validation where non-trivial (§20) |
| `app/Http/Middleware/EnsureAdminIsActive.php` | Blocks a deactivated admin whose session is still live |
| `app/Http/Middleware/AssignRequestId.php` | Correlation id via `Log::withContext()` (§24) |
| `bootstrap/app.php` | Routing, middleware, CSRF exclusion for the payment callback, `withExceptions()` + `dontFlash()` |
| `config/services.php` | `toyyibpay` and `easyparcel` blocks, read from `.env` (§31) |
| `config/logging.php` | `daily` channel, 14-day retention |
| `app/Providers/AppServiceProvider.php` | `Model::shouldBeStrict()` outside production; `CartService` binding |

Laravel 11+ has **no `app/Http/Kernel.php` or `app/Console/Kernel.php`** — do not recreate them (§19).

---

## 3. Interfaces, Endpoints & Data Contracts

### 3.1 Public routes (`routes/web.php`, §21)

| Method | Path | Controller | Notes |
|---|---|---|---|
| GET | `/` | `HomeController@index` | |
| GET | `/products` | `ProductController@index` | `?category=<slug>` |
| GET | `/products/{product:slug}` | `ProductController@show` | Route model binding; variant selector |
| POST | `/cart` | `CartController@store` | CSRF |
| PATCH | `/cart/{variant}` | `CartController@update` | CSRF |
| DELETE | `/cart/{variant}` | `CartController@destroy` | CSRF |
| GET | `/checkout` | `CheckoutController@create` | |
| POST | `/shipping/quote` | `ShippingController@quote` | AJAX, CSRF |
| POST | `/checkout` | `CheckoutController@store` | `CheckoutRequest`; creates order + ToyyibPay bill |
| GET | `/payment/toyyibpay/return` | `PaymentController@handleReturn` | **Untrusted** — triggers server-side verification |
| POST | `/payment/toyyibpay/callback` | `PaymentController@handleCallback` | **Untrusted**, CSRF-excluded — triggers server-side verification |
| GET/POST | `/order-status` | `OrderStatusController` | Requires order number **+ matching email** |

### 3.2 Admin routes — group middleware `auth`, `EnsureAdminIsActive`

`/admin/login` (`guest`, `throttle:5,1`) · `/admin` dashboard · resource routes for `categories`, `products`, `products/{product}/variations`, `orders` · `/admin/settings` · `/admin/integrations/easyparcel/{connect,callback,disconnect}` · `/admin/shipments` (list + reconciliation) · **`POST /admin/orders/{order}/ship`** (books the shipment — POST + CSRF + admin only, because it spends real money).

### 3.3 Outbound contracts

**ToyyibPay** (§12) — `POST {base}/index.php/api/createBill`, `POST {base}/index.php/api/getBillTransactions`.
Sandbox `https://dev.toyyibpay.com`, production `https://toyyibpay.com`.
`billAmount` is **in cents**, matching our storage exactly — no float conversion in the payment path.
⚠ The `getBillTransactions` **response field names are unverified** — `../Planning.md` §11.A.6. The service fails closed until a human confirms them.

**EasyParcel Open API 2026-06** (§13) — `POST https://api.easyparcel.com/open_api/2026-06/shipment/quotations`, Bearer token.
Geography is **ISO 3166-2** (`MY-07` = Penang). `pricing.total_amount` is a **decimal string**, not minor units — converted to sen once at the service boundary.
OAuth: `GET /oauth/login`, `POST /oauth/token` (Basic auth). Access ≈10h, refresh ≈1y, **the refresh token rotates on every use**.
⚠ `../Planning.md` §11.B.2 — §31's `EASYPARCEL_API_KEY` is the *legacy Connect API* shape. Which API the account uses is **OQ-03** and it changes this contract.

**EasyParcel booking (REQ-013)** — `POST …/shipment/submit`, `POST …/shipment/pay`, plus tracking.
⚠ **The request and response bodies are `NEEDS VERIFICATION`** (`../Planning.md` §11.B.5.1) — including where the AWB number appears, the tracking mechanism, and whether an idempotency key is accepted. **Phase 8b is blocked until these are read from the official specification (OQ-13).** The control design in §11.B.5.3 holds regardless of field names.

### 3.4 Invariants that must not be broken

1. **No payment status is written from a callback or return payload.** Always re-query, then match amount **and** external reference (`Planning.md` §11.A.5).
2. **Stock decrements exactly once**, via one guarded `UPDATE` with the affected-row count asserted. Never `SELECT` then `UPDATE`.
3. **Unused variant option slots store `''`, never `NULL`** — MySQL treats NULLs as distinct in a unique index.
4. **`order_items` is immutable** after creation — it is a purchase-time snapshot.
5. **All money is integer sen.** No float touches the payment path.
6. **Every total is computed server-side** from DB values. Client-side prices, stock, totals, redirects, hidden fields and query params are never trusted (§17).
7. **A shipment is booked at most once per order.** `UNIQUE(shipments.order_id)` + a guarded `pending_submit` → `submitting` transition. Booking is an admin POST, never a callback or a GET.
8. **An ambiguous booking outcome is `needs_reconciliation`, never `failed`, and is never auto-retried.** Retrying a `pay` that may have succeeded is how the store pays twice.

---

## 4. Configuration & Dependencies

### 4.1 Environment variables (§31)

```
APP_NAME="Basic Custom E-Commerce"
APP_ENV=production
APP_KEY=                      # key:generate ONCE — rotating it breaks integration_tokens
APP_DEBUG=false
APP_URL=https://…             # must be the public HTTPS host; the ToyyibPay callback depends on it

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=basic_ecom
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=daily
LOG_LEVEL=info

TOYYIBPAY_SECRET_KEY=
TOYYIBPAY_CATEGORY_CODE=
TOYYIBPAY_SANDBOX=false

EASYPARCEL_CLIENT_ID=          # OAuth 2.0 — NOT EASYPARCEL_API_KEY. See Planning §11.B.2 / OQ-03
EASYPARCEL_CLIENT_SECRET=
EASYPARCEL_SANDBOX=false
```

**Never call `env()` outside `config/`** — after `php artisan config:cache` it returns null in production. Silent, and only in prod.

**Tokens are not here.** EasyParcel access/refresh tokens rotate at runtime and live encrypted in `integration_tokens`.

**Never passed to Blade or client-side JS** (§16).

### 4.2 Database tables (§18)

Ten application tables: `users`, `categories`, `products`, `product_variants`, `orders`, `order_items`, `payments`, `shipments`, `settings`, `integration_tokens`.

Plus Laravel's `migrations` bookkeeping table and nothing else — the skeleton's `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` and `password_reset_tokens` migrations are deleted (file session/cache, `sync` queue, no reset flow).

Per-table purpose, fields, PK, FKs, relationships, indexes and status fields: `../Planning.md` §12.2.

### 4.3 Dependencies (§30)

Runtime: the Laravel 12 skeleton only. Dev: `phpunit/phpunit ^11.5`, `laravel/pint`, `fakerphp/faker`, `mockery/mockery` — all skeleton defaults.

**No package may be added beyond the skeleton without passing the §30 gate and recording the justification in `../Planning.md`.** `composer.lock` is committed; `composer audit` runs before release.

`"config": {"platform": {"php": "8.3"}}` is load-bearing — this machine runs PHP 8.4.10 and the target is 8.3.

---

## 5. Change History & Log

| Date | Change Summary | Impacted Files | Author / Ref |
|---|---|---|---|
| 2026-08-26 | Spec replaced with the client's Laravel 12 / PHP 8.3 instruction (36 sections, 11 phases) | `Prompt.txt` | Client |
| 2026-08-26 | Init Protocol Phase 0 intake: client project / VPS / MySQL 8.0 / name confirmed | — | Iris |
| 2026-08-26 | Phase 1 research: Laravel 12 support-window and toolchain facts verified; 5 patterns pulled from `11-pattern-library.md` | — | Iris (Scout) |
| 2026-08-26 | `Planning.md` written against the 36-section spec — traceability matrix `REQ-001`…`REQ-012`, Cart Design and Checkout Design sections added per §25, phases aligned to §27 | `Planning.md` | Iris |
| 2026-08-26 | This document created per `53-documentation-protocol.md` §2.2 | `docs/documentation.md` | Iris |
| 2026-08-26 | **REQ-013 added**: client moved EasyParcel shipment booking, AWB and tracking into scope. Adds `shipments` (10th table), `ShipmentController`, `ShipmentStatus`, booking/reconciliation design, and 5 new open questions (OQ-12…OQ-16). Estimate ~9 → ~11 days | `Planning.md`, `docs/documentation.md` | Iris |
| 2026-08-26 | Implementation **halted** at the §35 approval gate | — | Iris |
| 2026-08-26 | **Planning.md APPROVED.** Phase 2 — Laravel 12.68.0 foundation: Vite removed, cipher AES-256-GCM, file/file/sync drivers, config/services.php | `composer.json`, `config/*`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php` | Iris (`43035bf`) |
| 2026-08-27 | **Phase 3 — Database.** 10 tables, 4 backed enums, 10 Eloquent models, 8 factories, 3 seeders. Atomic guards implemented and tested: stock decrement, paid transition, shipment booking | `database/migrations/*`, `app/Enums/*`, `app/Models/*`, `database/factories/*`, `database/seeders/*`, `tests/Feature/*` | Iris (`23bb05a`) |

---

## 6. Anchors

Referenced by the traceability matrix in `../Planning.md` §1.

- `#catalogue` — REQ-001 · §2.1, §2.3
- `#variations` — REQ-002 · §2.3, and the `''`-not-`NULL` invariant in §3.4
- `#cart` — REQ-003 · `CartService` in §2.4; design in `Planning.md` §8
- `#checkout` — REQ-004 · §3.1; design in `Planning.md` §9
- `#payment` — REQ-005 · §3.3 and the verification invariant in §3.4
- `#shipping` — REQ-006 · §3.3 EasyParcel contract
- `#orders` — REQ-007 · §2.3
- `#inventory` — REQ-008 · stock invariant in §3.4
- `#admin` — REQ-009 · §2.2
- `#security` — REQ-010 · `Planning.md` §14
- `#settings` — REQ-011 · §2.2
- `#logging` — REQ-012 · §2.5, `Planning.md` §15
- `#booking` — REQ-013 · §2.2 `ShipmentController`, §2.3 `Shipment`, §3.3 booking contract, invariants 7–8 in §3.4; design in `Planning.md` §11.B.5
