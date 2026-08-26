# Planning.md — Basic Custom E-Commerce (Laravel 12 / PHP 8.3)

> **Status**: **APPROVED 2026-08-26.** Phases 2–7 complete; Phase 8 (Shipping) next.
> **Last Updated**: 2026-08-26
> **Spec source**: `Prompt.txt` — *CoreSentinel Development Instruction — Laravel 12 Basic Custom E-Commerce* (36 sections)
> **Agent**: Iris / CoreSentinel · Init Protocol `05-init-protocol.md`
> **Work mode**: Client project — `52-handoff-protocol.md` applies at Phase 11
> **Deliverable rule**: per spec **§35** this is the only Phase-1 artifact. No implementation before approval.

All `§` references below point at **`Prompt.txt`** unless prefixed with "Planning".

---

## 0. Intake Record (CoreSentinel Phase 0)

Four items were not in the spec. Each changes downstream defaults, so each was settled before planning:

| Item | Answer | Consequence |
|---|---|---|
| Project name | **Basic Custom E-Commerce** | `APP_NAME`, DB `basic_ecom`, MemoryCore profile `06-basic-ecom.md` |
| Work mode | **Client project** | Handoff document + credential transfer at Phase 11; residual risks need signed acknowledgement |
| Deploy target | **VPS** | Composer, `artisan`, cron and Let's Encrypt all available — satisfies §33 comfortably |
| Database | **MySQL 8.0** | `DB_CONNECTION=mysql`. The MariaDB `renameColumn()` driver trap does not apply (Planning §16.4) |

### 0.1 Verified version facts (spec §3 — do not hallucinate)

Verified by Scout against `11-pattern-library.md` (baseline verified 2026-08-14) and this machine. Recorded because two of them affect the client's cost:

- **Laravel 12 left bug-fix support on 2026-08-13** — 13 days before this document. Security fixes run to ~2027-02-24. **Laravel 13 is the current release; there is no LTS.** Laravel 12 is a legitimate target and the instruction stands, but a major upgrade falls due inside ~6 months → **OQ-08**.
- **PHP 8.3** receives security fixes to **2027-12-31**. Nothing will use a feature above 8.3, so a later move to 8.4 is a config change, not a rewrite.
- **Local PHP is 8.4.10**; target is 8.3. `"config": {"platform": {"php": "8.3"}}` is therefore **load-bearing** — without it Composer resolves against 8.4 and can select a package the VPS rejects. Composer 2.8.10 is present.
- **Breeze/Jetstream were removed from the Laravel installer in 12**; starter kits now use Fortify. Consistent with §16 ("do not install a large authentication ecosystem") — we install none.
- **Bootstrap 5.3.8** is current. Bootstrap 6 does not exist.

---

## 1. Requirements & Traceability Matrix (§26 · `53-documentation-protocol.md` §3.1)

Status vocabulary: `Planned` | `In-Progress` | `Verified` | `Done`.
**No item may be marked `Done` without real implementation paths, real passing test paths, and a real docs anchor.**

| Req ID | Feature / Objective | Implementation Files | Routes | Test Files | Documentation | Status |
|---|---|---|---|---|---|---|
| `REQ-001` | Product & Category Management | `app/Http/Controllers/Admin/{Product,Category}Controller.php`<br>`app/Models/{Product,Category}.php` | `routes/web.php` (admin group) | `tests/Feature/Admin/CatalogueTest.php` | `docs/documentation.md#catalogue` | `Verified` — Phase 5 |
| `REQ-002` | Product Variations | `app/Http/Controllers/Admin/VariationController.php`<br>`app/Models/ProductVariant.php` | admin group | `tests/Feature/VariationTest.php` | `docs/documentation.md#variations` | `Verified` — Phase 5 |
| `REQ-003` | Shopping Cart | `app/Http/Controllers/CartController.php`<br>`app/Services/CartService.php` | `/cart/*` | `tests/Feature/CartTest.php` | `docs/documentation.md#cart` | `Verified` — Phase 6 |
| `REQ-004` | Checkout & Order Creation | `app/Http/Controllers/CheckoutController.php`<br>`app/Http/Requests/CheckoutRequest.php`<br>`app/Models/{Order,OrderItem}.php` | `/checkout` | `tests/Feature/CheckoutTest.php` | `docs/documentation.md#checkout` | `Verified` — Phase 6 |
| `REQ-005` | ToyyibPay Payment | `app/Http/Controllers/PaymentController.php`<br>`app/Services/ToyyibPayService.php`<br>`app/Models/Payment.php` | `/payment/*` | `tests/Feature/PaymentTest.php` | `docs/documentation.md#payment` | `In-Progress` — model + migration |
| `REQ-006` | EasyParcel Shipping Rates | `app/Http/Controllers/ShippingController.php`<br>`app/Http/Controllers/Admin/IntegrationController.php`<br>`app/Services/EasyParcelService.php`<br>`app/Models/IntegrationToken.php` | `/shipping/quote` | `tests/Feature/ShippingTest.php` | `docs/documentation.md#shipping` | `In-Progress` — model + migration + tests |
| `REQ-007` | Order Management | `app/Http/Controllers/Admin/OrderController.php`<br>`app/Http/Controllers/OrderStatusController.php`<br>`app/Enums/OrderStatus.php` | admin group, `/order-status` | `tests/Feature/OrderTest.php` | `docs/documentation.md#orders` | `In-Progress` — model + migration + tests |
| `REQ-008` | Inventory / Stock | `app/Models/ProductVariant.php` (guarded decrement)<br>`app/Http/Controllers/Admin/VariationController.php` | admin group | `tests/Feature/InventoryTest.php` | `docs/documentation.md#inventory` | `Verified` — Phase 5 |
| `REQ-009` | Admin Panel & Auth | `app/Http/Controllers/Admin/{Auth,Dashboard}Controller.php`<br>`app/Models/User.php`<br>`app/Http/Middleware/EnsureAdminIsActive.php` | `/admin/*` | `tests/Feature/Admin/AuthTest.php` | `docs/documentation.md#admin` | `Planned` |
| `REQ-010` | Security Controls | `bootstrap/app.php`<br>`app/Http/Requests/*`<br>route middleware groups | all | `tests/Feature/SecurityTest.php` | `docs/documentation.md#security` | `Planned` |
| `REQ-011` | Store Settings | `app/Http/Controllers/Admin/SettingController.php`<br>`app/Models/Setting.php` | admin group | `tests/Feature/SettingTest.php` | `docs/documentation.md#settings` | `In-Progress` — model + seeder |
| `REQ-012` | Error Handling & Logging | `bootstrap/app.php` (`withExceptions`)<br>`config/logging.php`<br>`app/Http/Middleware/AssignRequestId.php` | all | `tests/Feature/ErrorHandlingTest.php` | `docs/documentation.md#logging` | `Planned` |
| `REQ-013` | **Shipment Booking, AWB & Tracking** | `app/Http/Controllers/Admin/ShipmentController.php`<br>`app/Services/EasyParcelService.php` (booking methods)<br>`app/Models/Shipment.php`<br>`app/Enums/ShipmentStatus.php` | admin group, `/order-status` | `tests/Feature/ShipmentBookingTest.php` | `docs/documentation.md#booking` | `In-Progress` — model + migration + tests |

Every commit message references its `REQ-0NN`. No orphan code.

---

## 2. Project Overview (§25)

A single-vendor **Basic Custom E-Commerce Website** for a small business, on **Laravel 12 / PHP 8.3 / MySQL**, deployed to a VPS.

**Problem it solves**: the business has no way to sell online. It needs a storefront where customers browse products that come in size/colour variations, pay by Malaysian online banking (ToyyibPay/FPX), and have the parcel costed and **booked** with a real courier (EasyParcel) — with an AWB and tracking number returned to the customer — plus a simple admin panel for catalogue, stock, orders and shipments.

**What it is not**: a multi-vendor marketplace, a headless platform, or a system with customer accounts, loyalty, discounts or analytics. Budget is **RM1,000**; the architecture fits that.

**Governing principle (§34, §36)**: at every stage — *is this actually required for the MVP?* If no, it is not built. Laravel is used rather than re-created.

---

## 3. Scope (§25)

### 3.1 In Scope (MVP)

| # | Capability | Req | Spec |
|---|---|---|---|
| 1 | Product catalogue with categories | REQ-001 | §8, §9 |
| 2 | Product variations with own SKU, price, stock, status | REQ-002 | §9 |
| 3 | Session-based cart, variation-aware | REQ-003 | §10 |
| 4 | Guest checkout (name, email, phone, shipping address) | REQ-004 | §11 |
| 5 | EasyParcel **rate calculation + selection** | REQ-006 | §13 |
| 6 | ToyyibPay payment with **server-side** verification | REQ-005 | §12 |
| 7 | Order + payment status tracking, customer status page | REQ-007 | §14 |
| 8 | Basic inventory: stock qty, out-of-stock block, admin edit | REQ-008 | §15 |
| 9 | Admin panel: login, dashboard, catalogue, orders, settings | REQ-009, REQ-011 | §16 |
| 10 | Security baseline | REQ-010 | §17 |
| 11 | Laravel logging of order/payment/shipping events | REQ-012 | §23, §24 |
| 12 | **EasyParcel shipment booking, AWB & tracking** | REQ-013 | §13 |

### 3.2 OUT OF SCOPE

| Item | Why excluded |
|---|---|
| Customer accounts / login / order history | §11 — guest checkout is *preferred* for MVP. Accounts add auth, password reset, email delivery and personal-data handling. |
| EasyParcel drop-off points, insurance, coupons, e-invoice | Exposed by the Open API; none requested. |
| Discount codes / vouchers / promotions | Not in spec. Touches cart, checkout, totals and reporting. |
| Product reviews, ratings, wishlist | Not in spec. |
| Multi-currency | §16 lists currency as a *setting*; single currency (MYR) assumed. |
| In-app refund processing | §14 lists `Refunded` as a payment *status* only — set manually by admin. No gateway refund call. |
| Email notifications (order confirmation, receipts) | **Not in spec** — see OQ-05. Flagged because customers usually expect it. ~half a day on Laravel, but it introduces mail config and possibly queue tables (§25 "queue requirements only if actually used"). |
| Advanced reporting / charts | §28 ranks basic reporting last. Dashboard shows the four counters §16 requires. |
| Catalogue-wide faceted filtering | Requires the normalised option dictionary — Planning §6, Option B. |
| Persistent/DB cart, abandoned-cart recovery | §10: prefer session cart, do not create a DB cart unnecessarily. |
| Warehouse mgmt, purchase orders, stock transfers, multi-warehouse, inventory ledgers | §15 names each of these as excluded. |
| Queues, background workers, Redis, Docker, K8s, CI/CD | §2.1, §33. Not switched on. |
| Repository/service abstraction per model, generic factories | §2.1, §22. See Planning §13.1. |

### 3.3 Scope-Control Findings (§29)

- **EasyParcel booking + tracking** — identified as the largest scope-inflating item in the project: it adds shipment submit/pay/tracking calls, a **real-money credit-balance dependency**, a 10th table, label/AWB handling, and a failure mode where money has left the EasyParcel wallet but the DB write failed. §29's procedure was followed — identified, costed, marked optional — and the client has **explicitly approved it into scope**. It is therefore built, with the safeguards in Planning §11.B.5. **Cost: ~+2 days and a recurring courier-credit obligation (OQ-12, OQ-13).**
- **Normalised option/attribute engine** — +3 tables and pivot-sync logic, to buy a filter feature nobody asked for. → **Optional.**
- **Email delivery** — SMTP config, deliverability, bounce handling, templates. → **Optional.**
- **Laravel's own optional surface** — starter kits, Livewire, database queues, Telescope, Pulse. Each is one command away and each is a permanent maintenance obligation. All declined per §2.1 and §30.

---

## 4. Budget Constraint (§4, §25)

**How the architecture stays inside RM1,000**: by letting Laravel supply everything it already supplies, and building only the two things it does not — the ToyyibPay and EasyParcel integrations.

### 4.1 What is NOT built because Laravel provides it (§2.1 "do not recreate Laravel internally")

| Not built | Laravel provides | Saving |
|---|---|---|
| Router / dispatcher | `routes/web.php`, route model binding (§21) | ~0.5 d |
| CSRF token handling | `@csrf` + CSRF middleware, built-in 419 page (§17) | ~0.3 d |
| Validation layer | Validation + Form Requests (§20) | ~1.0 d |
| Auth + login throttling | Laravel auth + `throttle` middleware (§16) | ~0.5 d |
| Logger with daily rotation | Laravel logging (§24) | ~0.4 d |
| HTTP client with timeout/retry | `Http` facade + `Http::fake()` for tests | ~0.5 d |
| Encryption helper | `Crypt` + Eloquent `encrypted` cast | ~0.4 d |
| Output escaping helper | Blade `{{ }}` (§17) | ongoing |
| Hand-written schema + import script | Migrations + seeders (§7, §18) | ~0.3 d |
| Model base class + PDO plumbing | Eloquent (§20) | ~0.5 d |

**~4 days of infrastructure the project simply does not write.**

### 4.2 What the stack costs

| Cost | Detail |
|---|---|
| **~30 runtime packages** | The Laravel skeleton's transitive tree (`symfony/*`, `monolog`, `carbon`, `guzzle`). Controlled by §30: no package added beyond the skeleton without justification. `composer.lock` committed; `composer audit` before release. |
| **Framework upgrade obligation** | Laravel 12 is past bug-fix support; security ends ~2027-02-24 (§0.1). A major upgrade falls due inside ~6 months → **OQ-08**. |
| **Recurring VPS cost** | ~RM25–60/month **against a one-off RM1,000 build**. Not in the build budget → **OQ-09**. |
| Framework setup + deploy scripting | ~0.5 d |
| **Shipment booking (REQ-013)** | ~+2 days: submit/pay/tracking calls, `shipments` table, AWB + label handling, a reconciliation screen, and the failure-mode tests. Plus an **ongoing courier-credit balance** the store must fund and monitor → **OQ-12, OQ-13**. |

**Revised estimate: ~11 working days** (was ~9 before REQ-013 entered scope).

### 4.3 Design decisions that hold cost down

| Decision | Cheaper because | Spec |
|---|---|---|
| Session cart, no cart tables | Removes 2 tables + merge logic + cleanup | §10 |
| Denormalised variant row | Removes 3 tables + pivot-sync code | §9 |
| One `shipments` row per order, no line-level splitting | Single-parcel assumption keeps booking to one API round trip | §13, §18 |
| Booking is **admin-triggered**, not automatic on payment | Keeps real-money spend out of the payment callback path (Planning §11.B.5) | §13, §17 |
| Secrets in `.env`, not admin-editable DB rows | Removes a key-rotation story and a credential-leak path from the admin UI | §16, §31 |
| Blade + locally hosted Bootstrap | No SPA, no framework build chain | §6 |
| **9 tables** | Every one justified in Planning §12 | §18 |

**Estimated build: ~9 working days** at MVP scope. The two integrations are the only real unknowns and each stays behind one service class (§22).

---

## 5. Technology Stack (§5, §6, §7, §25)

| Layer | Choice | Spec |
|---|---|---|
| Framework | **Laravel 12** (`laravel/framework ^12.0`), standard skeleton, **no starter kit** | §5, §16 |
| Language | **PHP 8.3** | §5 |
| Architecture | Laravel MVC — routes → controller → Form Request → Eloquent/service → Blade | §19, §20 |
| ORM | **Eloquent**, with relationships defined explicitly | §5, §7, §20 |
| Database | **MySQL 8.0**, InnoDB, `utf8mb4` (`utf8mb4_unicode_ci`) | §7 |
| Schema | **Laravel migrations**; seeders for admin, settings, demo catalogue | §7, §18 |
| Routing | `routes/web.php`, route model binding, middleware | §21 |
| Validation | Laravel validation + Form Requests where non-trivial | §20 |
| Sessions | Laravel sessions | §5 |
| CSRF | Laravel CSRF middleware | §5, §17 |
| Logging | Laravel logging, `daily` channel | §24 |
| Config | Laravel config + `.env` | §5, §31 |
| Templating | **Blade**, auto-escaping | §6 |
| Frontend | HTML5, CSS3, **Bootstrap 5.3**, vanilla JS where necessary | §6 |
| HTTP client | `Http` facade (Guzzle, ships with Laravel) — `timeout()`, `retry()`, `Http::fake()` | §22 |
| Testing | Laravel testing (**PHPUnit ^11.5**, Laravel 12 default) | §32 |
| Tooling | Composer; Laravel Pint | §5, §30 |

**Explicitly not used** (§5, §6, §2.1): Vanilla PHP MVC, Symfony directly, any other framework · React, Vue, Angular, Inertia, SPA · Docker, Kubernetes, Redis, Elasticsearch · queues, background workers · repository/service abstraction per model, generic factories.

### 5.1 Dependency policy (§30)

Before any Composer package: (1) does Laravel 12 already provide it? (2) is it genuinely necessary? (3) is it compatible with PHP 8.3 + Laravel 12? (4) why is it required? (5) is this the minimum?

**Planned runtime dependencies beyond the Laravel skeleton: none.** Dev: `phpunit/phpunit ^11.5`, `laravel/pint`, `fakerphp/faker`, `mockery/mockery` — all skeleton defaults.

```json
"require":     { "php": "^8.3", "laravel/framework": "^12.0" },
"config":      { "platform": { "php": "8.3" } }
```

`composer.lock` is committed. `composer audit` runs before release.

### 5.2 ⚠ Decision needing confirmation — the asset pipeline

**§19 lists `/resources/css` and `/resources/js`**, which in a stock Laravel 12 skeleton means **Vite + Node**. §6 asks only for Blade, Bootstrap and vanilla JS, and §33 does not list Node as a production requirement. Two readings, and they lead to different builds:

- **Option 1 — keep the skeleton as-is (Vite retained).** Fully conventional §19 structure. Cost: Node is required to build assets before deploy; one more thing on the VPS or in the release step.
- **Option 2 — remove Vite (recommended).** Delete `package.json`, `vite.config.js`, `resources/css`, `resources/js`. Bootstrap and one hand-written stylesheet are served from `public/` and referenced with `asset()`. No Node anywhere. Re-adding Vite later needs no application-code change.

**Recommendation: Option 2** — it is the simplest thing that satisfies §6, and §33's environment list (PHP, MySQL, Composer, web server) contains no Node. **Flagged rather than assumed, because it deviates from the folder list in §19.** → approval item, Planning §18.

---

## 6. Functional Requirements (§8, §25)

### 6.1 Customer Storefront
- Browse products; browse categories
- View product detail; select variation combination; see that combination's price and stock
- Add to cart, update qty, remove; cart distinguishes `T-Shirt/M/Black` from `T-Shirt/L/Black`
- Enter customer information and shipping information
- Select a shipping option from live courier rates
- Proceed to payment; pay via ToyyibPay
- View order status

### 6.2 Admin Panel (§16)
- Login
- Dashboard: **total orders, pending orders, paid orders, total sales**
- Products: list / add / edit / deactivate; manage variations; manage stock
- Categories: list / add / edit / deactivate
- Orders: list / view details / payment status / order status / customer info / shipping info / order items
- Settings: store name, email, phone, currency, ToyyibPay configuration, EasyParcel configuration

Credentials are **never** rendered into a form field or into JavaScript (§16) — Settings shows a *Configured / Not configured* badge and connection state only.

---

## 7. Product Variation Design (§9, §25)

§9 requires variation **combinations** with per-variation SKU, price, stock and status, and forbids "an unnecessarily complicated product attribute engine", asking for **the simplest normalized structure that correctly supports variations**.

**Structure**: `categories` → `products` → `product_variants`. **Every product has at least one variant**, even an option-less one — this removes `IF variant_id IS NULL` branching from every cart, order, stock and reporting path. **Price, stock, SKU and status live only on the variant**; `products` carries no price.

### 7.1 Chosen design — variant row carrying its own option labels

```
product_variants
  id, product_id, sku, price_minor, stock_qty, weight_g, status,
  option1_name, option1_value,     -- 'Size',  'M'
  option2_name, option2_value      -- 'Color', 'Black'
  UNIQUE (product_id, option1_value, option2_value)
```

Reproduces the spec's §9 example table exactly:

| Product | Size | Color | Price | Stock |
|---|---|---|---:|---:|
| T-Shirt | S | Black | RM30 | 10 |
| T-Shirt | M | Black | RM30 | 20 |
| T-Shirt | L | Black | RM32 | 15 |
| T-Shirt | S | White | RM30 | 8 |

**On "normalized"**: this is normalised with respect to what the system actually stores — price/stock/SKU depend on the variant and are stored once, on the variant. What is *not* normalised is the option **vocabulary** ("Size", "M"), which repeats as text across rows. That repetition is the deliberate trade: it buys a one-query product page and structural uniqueness, and it costs catalogue-wide faceting. §9's instruction to avoid an attribute engine is what settles it.

- Duplicate combinations are **structurally impossible** via the unique key.
- Reading a product page is **one query**, no joins.
- Limit: two option axes (sufficient for size + colour).
- **Gotcha**: unused option slots store `''`, **never `NULL`**. MySQL treats NULLs as distinct in a unique index, so `NULL` would permit two "no-option" variants on the same product. Migration: `->default('')`, **not** `->nullable()`.

### 7.2 Option B — normalised option dictionary · OPTIONAL, not MVP

`options` / `option_values` / pivot + `option_signature VARCHAR(191)` with `UNIQUE(product_id, option_signature)` — the shape recorded in `11-pattern-library.md` ("E-Commerce — Product Variants Without EAV"). Unlimited option axes and indexed catalogue-wide filtering.

**Cost: +3 tables, a pivot-sync routine maintaining the signature inside the same transaction, and a reverse index `(option_value_id, product_variant_id)`.** Real budget spent on a filter feature not requested anywhere in the spec.

> **Decision**: §7.1 for MVP. Option B is a clean, non-destructive upgrade later (backfill the dictionary from the denormalised columns). **Migrating does not require rebuilding the storefront.**

---

## 8. Cart Design (§10, §25)

**The cart is session-based.** §10 states the preference plainly and adds "do not create a database cart system unnecessarily". Guest checkout (§11) means there is no account to attach a persistent cart to, and no requirement for cross-device carts.

**Structure**: a Laravel session array keyed by `variant_id`. The variant **is** the variation identity, so `T-Shirt/M/Black` and `T-Shirt/L/Black` are naturally distinct keys — §10's requirement is satisfied by the key itself, not by comparison logic.

```
session('cart') => [ variant_id => qty, … ]
```

**Only `variant_id` and `qty` are stored.** Product name, variation label and price are **re-read from the database on every render and at order creation** — so a price change cannot be exploited from a stale session, and a hidden form field cannot alter a price (§17: do not trust client-side prices).

**Operations** (§10): add product + variation, update quantity, remove item, calculate subtotal. Subtotal is computed in integer sen from live DB prices.

**Stock is validated server-side on add and on quantity update** (§17), and again at order creation. Out-of-stock variants cannot be added.

Implemented as `app/Services/CartService.php` — a service is justified here because the logic is shared between the cart and checkout controllers, not because every model gets one (§2.1, §20).

**Not built**: `carts` / `cart_items` tables, cart merging, abandoned-cart recovery, cart expiry sweeper.

---

## 9. Checkout Design (§11, §25)

**Guest checkout.** No account is created, no password, no customer login (§11).

### 9.1 Collected fields

| Group | Fields |
|---|---|
| Customer | Name, Email, Phone |
| Shipping address | Address, City, State, Postcode, Country |

Validated by `app/Http/Requests/CheckoutRequest.php` — a Form Request is justified here because the rule set is large and reused between the quote and submit paths (§20: do not create one for trivial validation).

### 9.2 Flow

1. Customer reviews the cart and opens checkout.
2. Customer enters details and address.
3. **Shipping rates are fetched** for the postcode + state (`POST /shipping/quote`, AJAX, CSRF-protected). Customer selects a courier/service.
4. Customer submits. The server then, inside **one `DB::transaction()`**:
   - re-reads every variant price from the DB — **the browser's prices are ignored**;
   - re-validates stock for every line;
   - **re-validates the selected shipping fee** against the `service_id` — the posted fee is ignored;
   - computes `subtotal_minor`, `shipping_fee_minor`, `grand_total_minor` **server-side** (§17);
   - creates the `order` (`pending_payment` / `pending`) and its `order_items`;
   - snapshots product name, variation label, SKU and unit price onto each `order_item`.
5. The ToyyibPay bill is created and the customer is redirected (Planning §10).

### 9.3 Order totals

All money is **integer sen** (`INT UNSIGNED`, `_minor` suffix — Planning §12.1).

```
subtotal_minor    = Σ (unit_price_minor × qty)
shipping_fee_minor = selected courier rate, re-validated server-side
grand_total_minor = subtotal_minor + shipping_fee_minor
```

**Order number** `ORD-YYYYMMDD-NNNN`, with the `orders.order_no` UNIQUE key as the real guard — retry once on collision, never `SELECT MAX()`.

**Snapshot rule**: `order_items` is immutable after creation. A later catalogue edit or price change must never rewrite a historical order.

---

## 10. Order Flow (§12, §25)

```
Browse
  → Product
  → Variation
  → Cart
  → Checkout
  → Shipping (EasyParcel rate → courier selected)
  → Order Creation      (pending_payment / pending)
  → Payment             (ToyyibPay bill → redirect → customer pays)
  → Payment Verification (server-side re-query — never the redirect)
  → Order Confirmation
```

**Order status** (§14) — `App\Enums\OrderStatus`, a PHP 8.3 backed string enum cast on the model:
`pending_payment` · `paid` · `processing` · `shipped` · `completed` · `cancelled` (+ `needs_review`, Planning §11.5).

**Payment status** (§14) — `App\Enums\PaymentStatus`, deliberately separate:
`pending` · `paid` · `failed` · `refunded`.

Enums make an invalid status unrepresentable in PHP and `match` over them exhaustive. **The database columns stay `VARCHAR`** — the schema is identical to a non-enum design, so this costs nothing. No workflow engine (§14): status is plain Laravel logic and database state.

---

## 11. EasyParcel & ToyyibPay Integration Plans

### 11.A ToyyibPay Integration Plan (REQ-005, §12)

#### 11.A.1 Verification status

| | |
|---|---|
| Official reference `https://toyyibpay.com/apireference/` | **Returned HTTP 403 to automated fetch on 2026-08-26.** Not machine-verifiable in this session. |
| Corroborating sources | Community documentation mirror, `sitehandy/omnipay-toyyibpay`, `Akim95/toyyibpay-js-sdk`, `xputerax/toyyibpay` |
| Confidence | Endpoints, request fields and status codes agree across ≥2 independent sources. **`getBillTransactions` response field names do not.** |

**Per §3, a human must open the official reference in a browser and confirm §11.A.2–§11.A.5 before Phase 7.** This is the one hard blocker on settling a real payment.

#### 11.A.2 Endpoints — *verified across multiple sources*

| Env | Base |
|---|---|
| Sandbox | `https://dev.toyyibpay.com` |
| Production | `https://toyyibpay.com` |

- Create bill: `POST {base}/index.php/api/createBill`
- Verify payment: `POST {base}/index.php/api/getBillTransactions`

Bases and the secret key live in `config/services.php`, read from `.env` (§31). **Never `env()` outside `config/`** — after `php artisan config:cache` it returns null in production.

#### 11.A.3 `createBill` request fields — *verified*

Required: `userSecretKey`, `categoryCode`, `billName`, `billDescription`, `billPriceSetting`, `billAmount`.
Optional/used: `billPayorInfo`, `billReturnUrl`, `billCallbackUrl`, `billExternalReferenceNo`, `billTo`, `billEmail`, `billPhone`, `billPaymentChannel`.

Constraints that shape the design:

- **`billAmount` is in cents** — matches our integer-sen storage exactly, so the grand total passes straight through with **no float conversion anywhere in the payment path**.
- **`billName` ≤ 30 chars** → the order number.
- **`billDescription` ≤ 100 chars** → short item summary, `Str::limit()`.
- `billExternalReferenceNo` = our order number — the reconciliation key.

The response contains a **`BillCode`**; the customer is redirected to `{base}/{BillCode}`.

#### 11.A.4 Return vs Callback — *verified*

| | Fields |
|---|---|
| `billReturnUrl` (browser GET) | `status_id`, `billcode`, `order_id` |
| `billCallbackUrl` (server POST) | `refno`, `status`, `reason`, `billcode`, `order_id`, `amount` |

Status codes: `1` = success, `2` = pending, `3` = fail (`4` = pending, seen in SDKs).

The callback route is **excluded from CSRF verification** in `bootstrap/app.php` (ToyyibPay carries no token). Safe **only** because §11.A.5 never trusts the body.

#### 11.A.5 Verification rule — non-negotiable (§12, §17)

> §12: *"Never trust only the browser redirect as proof of successful payment."*
> §17: do not trust browser redirects, hidden form fields or query parameters.

Both the return URL **and** the callback are **untrusted notifications**. Neither `status_id` nor the callback's `status`/`amount` is written to the order. On either event the server:

1. Takes `billcode` only.
2. Calls `getBillTransactions` server-side with our `userSecretKey`.
3. Confirms status = paid, **and** the returned amount equals the order's stored `grand_total_minor`, **and** the external reference matches the order number.
4. Only then transitions the order — inside one `DB::transaction()` together with the stock decrement.

**Idempotent duplicate-callback handling** (§17, §23) — a guarded update, the *Atomic Race-Free Action Guard* pattern:

```php
$affected = Order::query()
    ->whereKey($order->id)
    ->where('payment_status', PaymentStatus::Pending)
    ->update([
        'payment_status' => PaymentStatus::Paid,
        'order_status'   => OrderStatus::Processing,
    ]);
```

Only the first caller gets `$affected === 1` and proceeds to decrement stock. A second callback is a no-op returning `200 OK`. `UNIQUE(payments.provider_ref)` is a second line of defence.

**Fails closed**: `ToyyibPayService::verifyPayment()` returns `unverified` for any response shape it cannot positively recognise, and the controller leaves such orders **pending**. A wrong guess about a field name would risk false "paid" transitions; refusing to settle is the safer failure.

#### 11.A.6 `NEEDS VERIFICATION`

- Exact JSON field names in the `getBillTransactions` response (`billpaymentStatus`, `billpaymentAmount`, `billExternalReferenceNo` are *probable*, unconfirmed).
- Whether `getBillTransactions` requires `userSecretKey` (the mirror lists only `billCode`; SDK usage suggests it is required).
- Whether the callback is `application/x-www-form-urlencoded`, and whether it retries on a non-200 response.
- Whether ToyyibPay provides any signature/HMAC on the callback. **Assumed: no** — precisely why §11.A.5 re-queries.
- Whether `categoryCode` must be created via `createCategory` or in the dashboard.
- **Operational**: the callback URL must be publicly reachable over HTTPS. On the chosen VPS this is a DNS record + Let's Encrypt certificate.

Response bodies are checked with `json_validate()` (PHP 8.3) before decoding, so a gateway HTML error page becomes a clean `unverified` result rather than a decode warning.

### 11.B EasyParcel Integration Plan (REQ-006, §13)

#### 11.B.1 Verification status — VERIFIED against the official specification

Source: `github.com/easyparcel/OpenAPI`, `source/includes/2026-06/` (`_authentication.md`, `_quotation.md`, `_references.md`), read 2026-08-26.

| Item | Resolution |
|---|---|
| Base URL | `https://api.easyparcel.com/open_api/2026-06/` |
| Quotations | `POST …/shipment/quotations`, JSON body |
| Auth | **OAuth 2.0** — `Authorization: Bearer <access_token>` |
| Request shape | `{ "shipment": [ { sender{postcode,subdivision_code,country}, receiver{…}, weight, … } ] }` |
| Geography | **ISO 3166-2** — the spec states: *"we follow the ISO 3166-2 standard … the code MY-07 represents the state of Penang"* |
| **Price unit** | **`pricing.total_amount` is a DECIMAL STRING in `pricing.currency`** (`"10.84"` MYR) — **not** minor units |
| Response shape | `data[].quotations[].courier{service_id,courier_name,service_name}` + `.pricing{}` |
| Sandbox | Real sandbox with free test credits, via the developer portal |

`total_amount` already includes tax and bundled feature charges, so it is the figure to charge the customer. **Conversion to sen happens once, at the service boundary** — `(int) round(((float) $totalAmount) * 100)` — and the value is never handled as a float again.

#### 11.B.2 ⚠ Divergence from the spec's §31 example — and why

**§31 lists `EASYPARCEL_API_KEY`.** That is the shape of the **legacy Connect API**. The current Open API 2026-06 uses **OAuth 2.0 (authorization-code + refresh)**, so a single static API key does not exist.

§31 itself anticipates this: *"The exact ToyyibPay and EasyParcel environment variables must only be added after verifying the official API documentation. Do not assume the variable names represent actual API requirements."* Following that instruction, the verified variables are:

```
EASYPARCEL_CLIENT_ID=
EASYPARCEL_CLIENT_SECRET=
EASYPARCEL_SANDBOX=
```

**This divergence is flagged for confirmation** (OQ-03). If the client's EasyParcel account is still on the legacy Connect API, the plan changes: a flat key, no token table, no OAuth flow — **simpler and about a day cheaper**. Which API the account is provisioned for must be confirmed before Phase 8.

#### 11.B.3 OAuth 2.0 — the real cost of the current API

The Open API uses the **authorization-code** grant — a *user-delegated* flow with browser login and consent — for what is fundamentally machine-to-machine work.

Consequence: **the admin authorises once**, and the long-lived refresh token carries the integration from then on. No per-request authorisation; no customer ever touches it.

| | |
|---|---|
| Authorize | `GET https://api.easyparcel.com/oauth/login?client_id=…&redirect_uri=…&state=…` |
| Token | `POST https://api.easyparcel.com/oauth/token`, `Authorization: Basic base64(client_id:client_secret)` |
| Grants | `authorization_code`, then `refresh_token` |
| Lifetimes | access ≈ 10 hours; refresh ≈ 1 year |

Three load-bearing rules:

1. **The refresh token ROTATES.** Every refresh returns a *new* one and it must be persisted. Keeping the old one makes the next refresh fail and the integration dies silently until someone re-authorises by hand.
2. **Tokens cannot live in `.env`** (§31 is for static secrets). They are obtained at runtime and rotate; the app must not rewrite its own config — and after `config:cache`, `.env` is not read at all. They live in `integration_tokens`, **encrypted at rest** via the Eloquent `encrypted` cast keyed from `APP_KEY`. A plaintext bearer token in a nightly `mysqldump` is a credential leak waiting for a mislaid backup. ⚠ The app cipher must be settled **before** the first token is stored.
3. **The `state` nonce is mandatory.** Without it an attacker can feed the callback an authorization code for *their* EasyParcel account and the store would ship on their credit. Generated per attempt, held in the session, compared with `hash_equals()`.

**Refresh concurrency is handled, not accepted**: two requests finding the token expired could both refresh, and rotation would invalidate one result. `Cache::lock('easyparcel:refresh', 10)->block(5, …)`, **re-reading the token row inside the lock**, makes it a three-line fix with no Redis and no extra table.

#### 11.B.4 Stage 1 — rate calculation and selection (§13)

Customer enters postcode + state → server quotes → customer selects → `shipping_fee_minor`, courier name and `service_id` are stored on the order; admin views them on the order detail screen.

- Pickup origin comes from settings (OQ-02), as an ISO 3166-2 code.
- **`weight` is required by the API and the spec never mentions product weight** — hence `weight_g` on `product_variants` plus a settings-level default, so a quote can never be requested at zero weight (**OQ-01**).
- The quoted fee is **re-validated server-side at order creation** against the selected `service_id` (§17). The fee posted by the browser is never trusted.

#### 11.B.5 Stage 2 — shipment booking, AWB & tracking (REQ-013, IN SCOPE)

**Approved into scope by the client.** §13 permitted splitting this out; the client has chosen not to. This section is written to make the risk manageable, not to re-argue it.

**Why it is the riskiest part of the project**, stated once so the design below makes sense:
booking **spends real money** from the store's EasyParcel credit balance. Every other
failure in this system costs a page reload. A failure here can cost a paid courier
booking with no record of it, or a customer charged for shipping that was never booked.

##### 11.B.5.1 `NEEDS VERIFICATION` — the payloads (§3)

The Open API index names the booking endpoints, but this session **did not read and confirm
their request/response bodies** the way §11.B.1 did for quotations. Per §3, they are not
invented here:

| Item | Status |
|---|---|
| `POST …/shipment/submit` — endpoint exists | *named in the spec index* |
| `POST …/shipment/pay` — endpoint exists | *named in the spec index* |
| Tracking retrieval endpoint + polling vs webhook | **`NEEDS VERIFICATION`** |
| Exact `submit` request body (parcel dimensions? pickup date? sender contact fields?) | **`NEEDS VERIFICATION`** |
| Exact `submit`/`pay` response shape — where the **AWB number**, tracking number, tracking URL and label PDF URL appear | **`NEEDS VERIFICATION`** |
| Whether `submit` and `pay` are separate calls or can be combined | **`NEEDS VERIFICATION`** |
| Whether the API exposes the **credit balance**, so low balance can be warned on before booking | **`NEEDS VERIFICATION`** |
| Idempotency: does EasyParcel accept a client-supplied idempotency key on `submit`/`pay`? | **`NEEDS VERIFICATION`** — this materially changes §11.B.5.3 |

**Phase 8b cannot start until these are read from `github.com/easyparcel/OpenAPI` and recorded here.** The rest of this section is the *control design*, which holds regardless of field names.

##### 11.B.5.2 Booking is admin-triggered, not automatic

**Decision: the shipment is booked by an explicit admin action** ("Book shipment" on the order detail screen), **not** automatically inside the payment callback.

Rationale — this is the single most important safety decision in REQ-013:

- The payment callback path already carries the money-critical stock decrement. Adding a **real-money outbound spend** to it means a ToyyibPay retry storm could book and pay for multiple shipments.
- A human checkpoint catches the address that is obviously wrong, the order flagged `needs_review` (Planning §7.5 oversell), and the fraudulent order — *before* credit is spent.
- It keeps `PaymentController` free of a second external integration, which §20 and §22 both push toward.

Automatic booking on payment is a later option once the flow has run in production for a while. → **OQ-14** if the client wants it from day one.

##### 11.B.5.3 The money-left-the-wallet failure mode — and the guard

The dangerous sequence is: call `pay` → EasyParcel debits the credit → our DB write fails →
we have no record, and the admin books it again.

**Control: write the record first, in a pending state, and never delete it.**

1. **Before** any API call, insert a `shipments` row for the order in `pending_submit`, inside a transaction with a **`UNIQUE(order_id)`** key. That unique key is what makes double-booking structurally impossible — a second "Book shipment" click hits a duplicate-key error, not a second charge.
2. Guarded transition to `submitting` — `WHERE status = 'pending_submit'` — with the affected-row count asserted, the same *Atomic Race-Free Action Guard* used for payment (Planning §11.A.5). Only one request proceeds.
3. Call `submit`. Persist the returned reference **immediately**, before anything else, even if the rest of the response is unexpected.
4. Call `pay`. On **any** outcome — success, failure, timeout, unparseable body — write the raw response to `shipments.raw_response` and move the row to a definite state.
5. **A timeout on `pay` is not a failure — it is an unknown.** The row goes to `needs_reconciliation`, never to `failed`. Retrying a `pay` that may have succeeded is how a store pays twice.

##### 11.B.5.4 Shipment states

`App\Enums\ShipmentStatus`, a backed string enum:

`pending_submit` · `submitting` · `submitted` · `paid` · `booked` · `in_transit` · `delivered` · `failed` · **`needs_reconciliation`** · `cancelled`

`needs_reconciliation` is the one that earns its place: it is the state for *we do not know whether money left the wallet*. An admin resolves it against the EasyParcel dashboard. **It is never resolved automatically.**

##### 11.B.5.5 Reconciliation screen

Admin → Shipments, filtered to `needs_reconciliation` and `failed`, showing order number, attempted timestamp, stored reference and the raw response. Two actions: *Mark as booked* (paste the AWB from the EasyParcel dashboard) and *Mark as failed, safe to retry*.

Without this screen the failure mode in §11.B.5.3 is invisible until a customer complains. It is roughly half a day and it is not optional.

##### 11.B.5.6 AWB, label and tracking

- **AWB number** and tracking number stored on `shipments`; surfaced on the admin order detail and on the customer's order-status page.
- **Label PDF**: store the URL EasyParcel returns. **Do not proxy or re-host the PDF** unless the URL is short-lived — that would add file storage, auth and cleanup for no benefit. If the URL expires, fetch on demand at print time. → **OQ-15**.
- **Tracking updates**: whether this is a webhook or polling is `NEEDS VERIFICATION`. If polling, it is a **scheduled command** (`routes/console.php` + the VPS cron already available), running a few times a day over shipments in `booked` / `in_transit` only — not a queue worker, which §2.1 excludes.
- **`order_status` follows the shipment**: a successful booking moves the order to `shipped` (§14); `delivered` moves it to `completed`. This is the only place shipment state writes to order state, and it is a guarded update.

##### 11.B.5.7 Still OUT OF SCOPE

Drop-off point selection, insurance quotations, coupons, e-invoice fields, multi-parcel splitting, and courier-credit top-up from inside the app (top-up stays a manual action in the EasyParcel dashboard — putting a payment flow for *our own* credit inside the store is a second payment integration).

#### 11.B.6 Failure policy (§23)

Not connected, refresh failed, API error, timeout (5s connect / 10s total), body failing `json_validate()`, or a failed batch entry → fall back to a configurable **flat rate**, log it (§24), and show the customer a neutral message. Losing a sale to a courier platform's downtime is not acceptable at this budget. Admin Settings and the order detail screen both surface when a `flat` rate was used.

`Http::retry(1, 200, throw: false)` covers one transient retry; beyond that it falls through rather than making the customer wait.

#### 11.B.7 Remaining unknowns

**Quotations (Stage 1): none documentary.** The request and response shapes are verified (§11.B.1). `delivery_duration` is `null` in the specification's own example; the UI treats it as optional.

**Booking (Stage 2): documentary gaps remain and they are blocking.** The `submit` / `pay` / tracking payloads listed in §11.B.5.1 are `NEEDS VERIFICATION` and **must be read from the official specification before Phase 8b starts** (§3, **OQ-13**). This is the difference between the two stages: Stage 1 can be built today, Stage 2 cannot.

**Operational, both stages**: a live sandbox round trip has not been run — no developer-portal application is registered yet (**OQ-03**). Everything below the network boundary is tested against `Http::fake()` fixtures. For booking, the fixtures cannot be written until OQ-13 is answered, because inventing a response shape to fake against would encode the guess into the tests as well as the code.

---

## 12. Database Design (§7, §18, §25)

MySQL, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`. Expressed as **Laravel migrations**; seeders for admin, settings and demo catalogue. Foreign keys where they express a real invariant; indexes on every column the app actually filters or joins on.

### 12.1 Money rule

**All money is `INT UNSIGNED`, in sen, column suffix `_minor`** — pattern *Money — Integer Minor Units, Not DECIMAL*. PDO returns `DECIMAL` as a **string**, so the first `$price * $qty` silently becomes a float and rounding bugs appear only at specific quantities. Integers stay exact; Eloquent's `integer` cast keeps them typed `int` in PHP. ToyyibPay's `billAmount` is already in cents, so the payment path needs no conversion at all.

### 12.2 Tables

Per §18, each table documents purpose, key fields, PK, FKs, relationships, indexes and status fields.

**`users`** — admin authentication (REQ-009).
Laravel's default table, reused rather than a bespoke `admins` — §16 says use Laravel's standard authentication approach and not to install a large auth ecosystem. This gives the guard, hashing and `throttle` for free.
Fields: `id`, `name`, `email`, `password`, `is_active`, timestamps. PK `id`. No FKs. Indexes: `email` UNIQUE. Status: `is_active`.
Registration routes are never defined. The `password_reset_tokens` migration is dropped (single admin, no self-service reset).

**`categories`** — product grouping (REQ-001).
Fields: `id`, `name`, `slug`, `is_active`, timestamps. PK `id`. Relationship: `hasMany(Product)`. Indexes: `slug` UNIQUE. Status: `is_active`.

**`products`** — catalogue entity (REQ-001).
Fields: `id`, `category_id`, `name`, `slug`, `description`, `image_path`, `is_active`, timestamps. **No price column** — price lives on the variant.
PK `id`. FK `category_id` → `categories.id` (restrict on delete; categories are deactivated, not deleted).
Relationships: `belongsTo(Category)`, `hasMany(ProductVariant)`.
Indexes: `slug` UNIQUE, `(category_id, is_active)`. Status: `is_active`.

**`product_variants`** — the purchasable unit (REQ-002, REQ-008).
Fields: `id`, `product_id`, `sku`, `price_minor`, `stock_qty`, `weight_g`, `status`, `option1_name`, `option1_value`, `option2_name`, `option2_value`, timestamps.
PK `id`. FK `product_id` → `products.id` cascade.
Relationship: `belongsTo(Product)`.
Indexes: `sku` UNIQUE, `product_id`, **`UNIQUE(product_id, option1_value, option2_value)`** — the structural guarantee against duplicate combinations.
Status: `status` (`active` / `inactive`). Option columns `default('')`, **never nullable** (Planning §7.1).

**`orders`** — one per checkout (REQ-004, REQ-007).
Fields: `id`, `order_no`, customer (`customer_name`, `customer_email`, `customer_phone`), address (`address_line`, `city`, `state`, `postcode`, `country`), `subtotal_minor`, `shipping_fee_minor`, `grand_total_minor`, `courier_name`, `courier_service_id`, `shipping_rate_source` (`api` / `flat`), `order_status`, `payment_status`, timestamps.
PK `id`. Relationships: `hasMany(OrderItem)`, `hasOne(Payment)`.
Indexes: `order_no` UNIQUE, `(payment_status, order_status)`, `created_at`, `customer_email`.
Status: `order_status`, `payment_status` — separate, per §14.

**`order_items`** — immutable line snapshot (REQ-004).
Fields: `id`, `order_id`, `product_variant_id`, **`product_name`, `variation_label`, `sku`, `unit_price_minor`**, `qty`, `line_total_minor`, timestamps.
PK `id`. FKs `order_id` → `orders.id` cascade; `product_variant_id` → `product_variants.id` restrict (history must survive catalogue edits).
Relationship: `belongsTo(Order)`.
Indexes: `order_id`.
Snapshotted so later catalogue edits never rewrite history.

**`payments`** — gateway audit trail (REQ-005).
Fields: `id`, `order_id`, `provider`, `bill_code`, `provider_ref`, `amount_minor`, `status`, `raw_response` (JSON), `paid_at`, timestamps.
PK `id`. FK `order_id` → `orders.id` cascade.
Indexes: **`provider_ref` UNIQUE** — the duplicate-callback guard — and `order_id`.
Status: `status`. `raw_response` is stored for reconciliation, scrubbed of credentials first (§24).

**`settings`** — store configuration (REQ-011).
Fields: `id`, `key`, `value`, timestamps. PK `id`. Indexes: `key` UNIQUE. Written with `updateOrCreate()`.
**Non-secret only** — API credentials live in `.env` (§16, §31).

**`integration_tokens`** — EasyParcel OAuth tokens (REQ-006).
Fields: `id`, `provider`, `access_token`, `refresh_token`, `expires_at`, `connected_at`, timestamps. PK `id`. Indexes: `provider` UNIQUE.
Both token columns use the Eloquent **`encrypted`** cast. **The only table holding credentials**, and it exists solely because the refresh token rotates at runtime and therefore cannot live in `.env` (Planning §11.B.3). **Not created at all if the account turns out to be on the legacy Connect API** (OQ-03).

**`shipments`** — courier booking, AWB and tracking (REQ-013).
Fields: `id`, `order_id`, `provider`, `provider_shipment_ref`, `awb_no`, `tracking_no`, `tracking_url`, `label_url`, `courier_name`, `service_id`, `cost_minor`, `status`, `raw_response` (JSON), `booked_at`, `last_tracked_at`, timestamps.
PK `id`. FK `order_id` → `orders.id` cascade.
Relationship: `belongsTo(Order)`; `Order hasOne(Shipment)`.
Indexes: **`order_id` UNIQUE** — one shipment per order, and the structural guard against double-booking (Planning §11.B.5.3) — plus `status` and `awb_no`.
Status: `status`, cast to `App\Enums\ShipmentStatus` (Planning §11.B.5.4), including **`needs_reconciliation`**.
`cost_minor` records what EasyParcel actually charged, which may differ from the quoted `orders.shipping_fee_minor` — the gap between the two is the store's margin or loss on shipping and the admin needs to see it.

### 12.3 Ten application tables — and what is deliberately absent (§18)

Per §18's "DO NOT blindly create these tables":

- **`product_variation_values` / `options` / `option_values`** — collapsed into the variant row (Planning §7).
- **`carts` / `cart_items`** — the cart is in the session (Planning §8).
- **`shipping`** as a separate entity — `shipments` (above) covers it. Courier *selection* stays on `orders` (it is part of the order the customer agreed to); the *booking* lives on `shipments`. Those are two different facts and conflating them is what forces a nullable-column mess later.
- **`shipment_items`** — the single-parcel assumption (Planning §20.6) means a shipment covers the whole order. Multi-parcel splitting would need this table and is out of scope.

**Framework tables are held to one.** The Laravel 12 skeleton ships migrations for `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` and `password_reset_tokens`. With `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` and no reset flow, **all are deleted** and only Laravel's `migrations` bookkeeping table remains. This is what §25's "queue requirements only if actually used" implies in practice. Approving email (OQ-05) may bring some back — a known, priced consequence.

### 12.4 Deactivate, not delete (§16)

`is_active = 0` for categories and products. A hard delete would orphan `order_items` history.

**Laravel's `SoftDeletes` trait is deliberately not used on these models.** Pattern *MySQL — Soft Deletes Break Unique Indexes*: `deleted_at` alongside a UNIQUE `slug`/`sku` does not behave as expected, because MySQL treats NULLs as distinct, and making it work requires a generated-column sentinel. A plain `is_active` boolean says exactly what is meant with none of that machinery.

---

## 13. Laravel Architecture (§19, §20, §21, §22, §25)

Standard Laravel 12 structure (§19). No custom framework inside Laravel; no manual MVC re-creation.

```
/basic-ecom
  /app
    /Enums                OrderStatus, PaymentStatus, VariantStatus, ShipmentStatus
    /Http
      /Controllers        Home, Product, Cart, Checkout, Shipping, Payment, OrderStatus
        /Admin            Auth, Dashboard, Category, Product, Variation, Order, Shipment,
                          Setting, Integration
      /Middleware         EnsureAdminIsActive, AssignRequestId
      /Requests           CheckoutRequest, ProductRequest, VariationRequest, SettingRequest
    /Models               User, Category, Product, ProductVariant, Order, OrderItem,
                          Payment, Shipment, Setting, IntegrationToken
    /Services             CartService, ToyyibPayService, EasyParcelService
    /Providers            AppServiceProvider
  /bootstrap              app.php   ← routing, middleware, exceptions (Laravel 11+ slim skeleton)
  /config                 app.php, database.php, logging.php, session.php, services.php, shop.php
  /database
    /factories  /migrations  /seeders
  /public                 index.php, /assets (css, js, images), /uploads
  /resources/views        layouts/, components/, storefront/, admin/, errors/
  /routes                 web.php, console.php (tracking poll — only if polling, §11.B.5.6)
  /storage                /logs
  /tests                  Feature/, Unit/
  /docs                   documentation.md
  .env  .env.example  artisan  composer.json  Planning.md
```

Note: Laravel 11+ has **no `app/Http/Kernel.php` or `app/Console/Kernel.php`** — middleware, routing and exception handling are configured in `bootstrap/app.php`. Do not recreate them.

### 13.1 Routes (§21)

All web routes in `routes/web.php`. **Route model binding** for `{product:slug}`, `{category:slug}`, `{order}`, `{variant}` — clearer and safer than manual lookups.

| Group | Middleware |
|---|---|
| Storefront | `web` |
| Payment callback | `web`, **CSRF-excluded** (Planning §11.A.4) |
| Admin login | `web`, `guest`, `throttle:5,1` |
| Admin | `web`, `auth`, `EnsureAdminIsActive` |

### 13.2 Controllers (§20)

Receive the request, trigger validation, coordinate application logic, return a view/redirect. **No third-party API logic in a controller** (§20, §22) — `PaymentController` and `ShippingController` call their service and handle the result.

### 13.3 Models (§20)

Eloquent for Product, Category, ProductVariant, Order, OrderItem, Payment, Setting, IntegrationToken, User. Relationships defined explicitly (Planning §12.2).

- `$fillable` on every model. **Never `$guarded = []`** (§17 mass assignment).
- Casts: `integer` on every `_minor` column, backed enums on the status columns, `encrypted` on token columns, `datetime` on `paid_at` / `expires_at`.
- `Model::shouldBeStrict()` outside production — lazy-loading violations and missing attributes fail loudly in dev and tests rather than becoming N+1 queries in production.

### 13.4 Form Requests (§20)

Used where validation is non-trivial or reused: `CheckoutRequest`, `ProductRequest`, `VariationRequest`, `SettingRequest`. **Not** created for trivial validation merely to raise the file count — a single-field admin toggle validates inline.

### 13.5 Services (§20, §22)

Three, each justified:

| Service | Justification |
|---|---|
| `ToyyibPayService` | External integration (§12, §22). Handles API communication, normalises verified responses, throws meaningful exceptions, logs failures. |
| `EasyParcelService` | External integration (§13, §22). Quotations, **booking (`submit` + `pay`), AWB retrieval and tracking**, the flat-rate fallback, and the OAuth token lifecycle. Kept as **one** service rather than split into `EasyParcelRateService` / `EasyParcelBookingService` — it is one vendor, one credential lifecycle, one client. §22 forbids the split. |
| `CartService` | Session-cart logic shared by the cart and checkout controllers. Genuinely reused — not a service-per-model. |

**Not built** (§22): `UniversalPaymentProviderFactory`, `UniversalShippingProviderFactory`, `AbstractIntegrationManager`, `IntegrationOrchestrator`, a repository layer, or an interface over either service. There is exactly one payment provider and one courier provider.

### 13.6 Middleware (§21)

`auth` (Laravel), `guest`, `throttle` (Laravel), plus two thin custom ones: `EnsureAdminIsActive` (blocks a deactivated admin whose session is still live) and `AssignRequestId` (correlation id for logging, Planning §15).

### 13.7 Blade (§20)

Storefront, product pages, cart, checkout and admin. **Blade components only where reuse is meaningful** — layout, product card, price display, status badge, form error block. Not a component per element.

---

## 14. Security Plan (REQ-010, §17, §25)

Laravel's built-in mechanisms first (§17). No custom security framework.

| Control | Implementation |
|---|---|
| SQL injection | Eloquent / query builder throughout. Raw SQL avoided; where unavoidable, parameter binding — never string interpolation. |
| Password hashing | Laravel `Hash` (bcrypt). Never MD5/SHA1. |
| Session security | `http_only`, `same_site=lax`, `secure` in production, `encrypt`, idle lifetime; regenerate on login, invalidate + regenerate token on logout. |
| CSRF | Laravel CSRF middleware on the `web` group + `@csrf` in every form; built-in 419. **One exclusion**: the ToyyibPay callback — safe only because §11.A.5 re-queries. |
| Input validation | Laravel validation + Form Requests. Client-side validation is UX only, never a control. |
| Output escaping | Blade `{{ }}`. `{!! !!}` banned outside a reviewed allow-list. |
| Authentication | Laravel auth middleware on the entire admin route group. |
| Authorization | Admin group middleware + `EnsureAdminIsActive` — not merely hidden nav links. Single admin role; no RBAC. |
| Unauthorized admin access | Every admin route behind the group; direct URL access redirects to login. |
| **Mass assignment** | `$fillable` on every model; `$guarded = []` forbidden. Totals and statuses set in code, never from request input. |
| **Manipulated prices** | Prices re-read from the DB at render and at order creation. Posted prices ignored (Planning §8, §9.2). |
| **Manipulated product / variation IDs** | Every `variant_id` is re-resolved and checked to belong to an active product before use; ownership and active status validated server-side. |
| **Server-side stock validation** | On add-to-cart, on quantity update, at order creation, and atomically at payment settlement. |
| **Server-side order totals** | Subtotal, shipping fee and grand total computed on the server from DB values only (§9.3). |
| **Payment verification on the server** | Never trust the redirect or the callback body — re-query and match amount + reference (§11.A.5). |
| **Idempotent payment callbacks** | Guarded status transition + `UNIQUE(payments.provider_ref)`. Second callback is a no-op. |
| Unauthorized order access | The public status page requires **order number + matching email**; order IDs are not enumerable through it. |
| Brute force | `throttle:5,1` on admin login plus a rate limiter keyed by email+IP. |
| Credential handling | `.env` + `config/services.php` (§16, §31). Never hardcoded, never in the DB, **never passed to Blade or client-side JS**. Settings shows a *Configured / Not configured* badge only. |
| File uploads | Product images validated (`image`, mime allow-list, max size) and stored with a framework-generated name — never the client's filename. |
| Error disclosure | `APP_DEBUG=false` in production; safe customer-facing messages; detail to the log only (§23). |
| Transport | HTTPS in production — mandatory for the ToyyibPay callback. |
| API timeouts | 5s connect / 10s total on both services; failure handled (§11.B.6), never fatal. |
| OAuth token storage | Encrypted at rest via the `encrypted` cast, keyed from `APP_KEY`. Never rendered to any view. |
| OAuth callback | Per-attempt `state` nonce in the session, compared with `hash_equals()`. |
| Secret leakage in traces | `#[\SensitiveParameter]` (PHP 8.2+) on credential parameters, plus `dontFlash()` in `bootstrap/app.php`. |
| **Double-booking a shipment** | `UNIQUE(shipments.order_id)` + a guarded state transition (`WHERE status = 'pending_submit'`). A second click hits a duplicate-key error, not a second real-money charge (Planning §11.B.5.3). |
| **Booking authorization** | "Book shipment" is an admin-only action behind the admin middleware group. It spends real money, so it is never reachable from a storefront route, a callback, or a GET request — **POST + CSRF only**. |
| **Ambiguous booking outcome** | A timeout on `pay` moves the shipment to `needs_reconciliation`, never to `failed`. Automatic retry of a possibly-successful payment is forbidden — that is how a store pays twice (Planning §11.B.5.3). |
| **Tracking data exposure** | The customer order-status page shows AWB and tracking only after the order-number + email check that already gates it. Label URLs are admin-only — a label carries the customer's full address. |
| Dependency surface | §30 gate on every package; `composer.lock` committed; `composer audit` before release. |

**Never trusted** (§17): client-side prices · client-side stock · client-side order totals · browser redirects · hidden form fields · query parameters.

**Out of scope**: WAF, 2FA, RBAC, Sanctum/Passport (no API surface).

---

## 15. Error Handling & Logging (REQ-012, §23, §24)

**Handled gracefully** (§23): payment API failure · shipping API failure · invalid API response · network timeout · invalid customer input · out-of-stock product · invalid product variation · duplicate payment callback · invalid order · database failure. Configured in `bootstrap/app.php` → `withExceptions()`.

Customers see a safe, understandable message. Developers get enough log detail to diagnose the cause. Sensitive system errors are never shown to customers.

**Logging** (§24) — Laravel's standard mechanisms, `daily` channel to `storage/logs`, `LOG_LEVEL=info` in production, 14-day retention. A **correlation id per request** is attached by `AssignRequestId` via `Log::withContext()`.

Traceable events: order creation · payment request · payment callback · payment verification · payment status changes · shipping API request/result · OAuth refresh and rotation · flat-rate fallback used · critical errors.

**Never logged** (§24): API passwords · API secret keys · OAuth tokens · card details · sensitive authentication information. Also excluded: the full customer address in error context. Enforced by `#[\SensitiveParameter]` + `dontFlash()`. `payments.raw_response` is scrubbed of credentials before it is stored.

---

## 16. Testing Plan (§32, §25)

Laravel's testing infrastructure — **PHPUnit ^11.5**, feature tests with `RefreshDatabase`, factories, and `Http::fake()` for both integrations. Covers every area §32 names.

| Area (§32) | Cases |
|---|---|
| **Product** | Listing; detail; product status (inactive hidden); variation selection |
| **Variation** | Combination uniqueness; **empty-string vs NULL option guard**; per-variant price/stock/SKU/status |
| **Cart** | Add item; update quantity; remove item; **variation differentiation**; stock validation; subtotal in sen |
| **Checkout** | Customer validation; address validation; order calculation; shipping fee; grand total; **price re-read from DB, not from the session** |
| **Payment** | Payment creation (`createBill` payload); callback handling; verification; failed payment; **duplicate callback decrements stock exactly once**; **amount verification rejects a mismatched callback**; **forged callback leaves the order pending** |
| **Shipping** | Rate retrieval; invalid address; **API failure → flat-rate fallback**; selected shipping option persisted; decimal-string → sen conversion |
| **Orders** | Order creation; order status; payment status; **order item integrity** (snapshot survives a later price change) |
| **Inventory** | Stock decrement is atomic under concurrency; out-of-stock blocks purchase; oversell → `needs_review`, not silent success; admin stock update |
| **Admin** | Authentication; authorization; product management; order management; settings |
| **Shipment booking** (REQ-013) | Booking creates exactly one `shipments` row; **a second "Book shipment" click does not produce a second booking** (unique key + guarded transition); `submit` succeeds then `pay` times out → row lands in `needs_reconciliation`, **not** `failed` and **not** retried; API error → `failed` with the raw response stored; successful booking moves the order to `shipped`; AWB/tracking surface on the order-status page only after the email check; reconciliation screen lists exactly the rows needing attention |
| **Security** | CSRF; authentication; authorization; input validation; **mass assignment**; **price manipulation**; **stock manipulation**; **unauthorized order access**; **booking is POST + admin-only** |

**Mechanics:**
- SQLite in-memory for speed on most suites.
- **The Inventory, Payment and Orders suites are additionally run against real MySQL before release** — guarded-`UPDATE` semantics, `UNIQUE` collisions and `utf8mb4` behaviour are exactly what SQLite will not tell the truth about, and that is where an oversell bug would hide. This is a **release step**, not a CI/CD pipeline: §33 says not to introduce CI infrastructure unless requested.
- No live API calls in any test — `Http::fake()` on both services.
- `Model::shouldBeStrict()` on in the test environment, so an accidental lazy load fails the suite.

One manual end-to-end run against the ToyyibPay **sandbox** and the EasyParcel **sandbox** is required before go-live.

---

## 17. Deployment Plan (§33, §25)

**Target: VPS** (confirmed at intake). Standard PHP hosting is also supported by this plan, but the VPS removes the plan's largest operational risk.

### 17.1 Minimum production requirements

| | |
|---|---|
| PHP | **8.3** |
| Extensions | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`. Memory ≥ 128 MB |
| Database | **MySQL 8.0** |
| Composer | 2.x |
| Web server | Nginx or Apache, HTTPS (Let's Encrypt) |
| Framework | Laravel 12 |
| Node | **Not required** — see Planning §5.2 |
| Queue | **Not used** (`QUEUE_CONNECTION=sync`), so no worker and no Supervisor (§25) |

### 17.2 Web root and exposure (§33)

**Document root → `public/`.** Not exposed directly: `.env`, `storage/`, `vendor/`, `app/`, `config/`, `database/`, `resources/`, `routes/`.

### 17.3 Deployment commands

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then fill in real values
php artisan key:generate      # ONCE — see the warning below
php artisan migrate --force
php artisan db:seed           # admin + settings + demo catalogue
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- **`storage/` and `bootstrap/cache/` must be writable** by the web user (0775). The most common Laravel deploy failure.
- `php artisan storage:link` is **not required** — product images are written to `public/uploads` directly.
- After `config:cache`, **`env()` outside `config/` returns null**. Run `php artisan optimize:clear` before re-caching on every subsequent deploy.
- ⚠ **`APP_KEY` is generated once and never rotated.** Rotating it makes the encrypted `integration_tokens` rows undecryptable (recovery = one EasyParcel reconnect).

### 17.4 Go-live

- Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the public HTTPS host.
- Switch both integrations from sandbox to production credentials; register the live ToyyibPay return and callback URLs; complete the EasyParcel authorisation once from admin Settings.
- **Verify the callback URL is publicly reachable over HTTPS before the first real payment.**
- Force an admin password change on first login.
- Backups: nightly `mysqldump` + `public/uploads` + **`.env` stored separately** (it holds `APP_KEY`).
- `composer audit` before every release.
- Smoke test: one real low-value transaction end to end, reconciled against the ToyyibPay dashboard.
- **Client handoff** per `52-handoff-protocol.md`: credentials, deployment runbook, and signed acknowledgement of the residual risks in OQ-08/OQ-09/OQ-10.

### 17.5 `.env` (§31)

```
APP_NAME="Basic Custom E-Commerce"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://…

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

EASYPARCEL_CLIENT_ID=          # OAuth 2.0 — see Planning §11.B.2, NOT EASYPARCEL_API_KEY
EASYPARCEL_CLIENT_SECRET=
EASYPARCEL_SANDBOX=false
```

`.env.example` carries placeholders only. Production secrets are never committed.

### 17.6 Not introduced (§33)

No Docker, no Kubernetes, no CI/CD pipeline.

### 17.7 MySQL vs MariaDB

The client confirmed **MySQL 8.0**, so `DB_CONNECTION=mysql` is correct. Recorded because the pattern library documents that **MariaDB ≤10.5.2 under the `mysql` driver turns every `renameColumn()` migration into a hard syntax error**. `utf8mb4_unicode_ci` is still specified over MySQL 8's `utf8mb4_0900_ai_ci`, so a later move to MariaDB would not require a collation migration.

---

## 18. Development Phases (§27)

| Phase | Content | Status |
|---|---|---|
| **1** | **Planning.md** — this document. No application code. | ✅ **Approved 2026-08-26** |
| 2 | **Laravel Foundation** — install Laravel 12 (no starter kit), configure environment, database connection, base application structure | ✅ **Done** — Laravel 12.68.0, commit `43035bf`. Vite removed, cipher AES-256-GCM, file/file/sync drivers, boots clean |
| 3 | **Database** — migrations, Eloquent models, relationships, seeders | ✅ **Done** — commit `23bb05a`. 10 tables, 4 enums, 10 models, 8 factories, 3 seeders. 32 tests green; 24 guard tests re-verified on real MariaDB |
| 4 | **Core Laravel MVC** — routes, controllers, Blade layouts/views, validation, middleware | ✅ **Done** — commit `448979a`. 76 tests green on SQLite + MariaDB. Admin auth, order lookup, Money support, Bootstrap vendored |
| 5 | **Product** — categories, products, variations, stock (REQ-001/002/008) | ✅ **Done** — commit `4c31125`. Admin CRUD + storefront catalogue. 101 tests green on SQLite + MariaDB |
| 6 | **Cart & Checkout** — session cart, product + variation selection, customer details, shipping address, order creation (REQ-003/004) | ✅ **Done** — commit `735219d`. 124 tests green on SQLite + MariaDB. Live E2E order created |
| 7 | **Payment** — verified ToyyibPay integration (REQ-005) | ✅ **Built** — commit `5c42880`. 139 tests green on SQLite + MariaDB. **Fails closed: cannot settle a real payment until OQ-11 is answered** |
| 8a | **Shipping — rates** — verified EasyParcel quotations (REQ-006) | `Planned` — needs OQ-03 answered first |
| 8b | **Shipping — booking, AWB & tracking** (REQ-013) — `shipments` table, admin booking action, reconciliation screen, tracking | `Planned` — **blocked until the payloads in §11.B.5.1 are read and recorded**. Sandbox-only until then |
| 9 | **Admin** — dashboard, catalogue, orders, settings (REQ-007/009/011) | `Planned` |
| 10 | **Security & Testing** — full purchase flow tested (REQ-010/012) | `Planned` |
| 11 | **Deployment** — production instructions + client handoff | `Planned` |

---

## 19. Open Questions

| ID | Question | Impact |
|---|---|---|
| **OQ-01** | **Products have no weight in the spec, but EasyParcel rate checking requires `weight`.** Confirm `weight_g` per variant + a store-level default. | **Blocks REQ-006** |
| **OQ-02** | Store pickup origin — postcode + state. As ISO 3166-2 (`MY-10` Selangor) if on the Open API. | Wrong origin = wrong rates on every order |
| **OQ-03** | **Is the EasyParcel account on the current Open API (OAuth 2.0) or the legacy Connect API (flat key)?** §31's `EASYPARCEL_API_KEY` suggests the latter; verified docs describe the former. **Legacy = simpler and ~1 day cheaper** (no token table, no OAuth flow). Also: do the ToyyibPay accounts (sandbox + live, incl. `categoryCode`) and an EasyParcel developer-portal application exist? | **Blocks Phase 8 design and Phases 7–8 round trips** |
| **OQ-04** | Flat-rate fallback shipping fee when the rate API fails (Planning §11.B.6)? | Checkout resilience |
| **OQ-05** | **Email confirmation to the customer after payment** is not in the spec. Customers normally expect it. In scope, or OUT OF SCOPE? ~half a day, but it adds mail config. | Scope |
| **OQ-06** | Multiple product images, or one per product? | REQ-001 |
| **OQ-07** | Confirm MYR as the single currency. | Planning §12.1 |
| **OQ-08** | **Laravel 12 left bug-fix support 2026-08-13; security fixes end ~2027-02-24 (§0.1).** Who budgets the framework major upgrade, and when? | Long-term cost and security posture |
| **OQ-09** | **A VPS is a recurring monthly cost (~RM25–60) against a one-off RM1,000 build.** Who pays it, and is it provisioned? | Ongoing cost; blocks Phase 11 |
| **OQ-10** | Confirm the **asset-pipeline decision** in Planning §5.2 — remove Vite (recommended, no Node) or keep the stock skeleton per §19's folder list. | Build + deploy shape |
| **OQ-11** | Confirm a human has verified the ToyyibPay items in **Planning §11.A.6** against the official reference. | **Blocks settling any real payment** |
| **OQ-12** | **REQ-013: who funds and monitors the EasyParcel credit balance?** Booking debits a prepaid balance. If it runs dry, booking fails for every order until someone tops it up — and top-up is a manual dashboard action, deliberately not built into the app (Planning §11.B.5.7). Who watches it, and should the app warn at a threshold? | **Booking stops working when the balance empties.** Operational, recurring, and outside the RM1,000 build |
| **OQ-13** | **REQ-013: the booking payloads are unverified** (Planning §11.B.5.1) — `submit`/`pay` request and response shapes, where the AWB appears, tracking mechanism, and whether an idempotency key is supported. A human must read `github.com/easyparcel/OpenAPI` and record them. | **Blocks Phase 8b entirely** |
| **OQ-14** | **REQ-013: booking trigger.** Planned as an **admin action**, deliberately kept out of the payment callback (Planning §11.B.5.2). Confirm — or state that booking must happen automatically on payment, which is a materially riskier design and needs its own retry/idempotency work. | Design of the booking path |
| **OQ-15** | **REQ-013: label handling.** Store EasyParcel's label URL and open it on demand (planned), or download and re-host the PDF? Re-hosting adds storage, auth and cleanup. Depends on whether the URL expires. | Storage + Phase 8b effort |
| **OQ-16** | **REQ-013: pickup.** Does booking need a pickup date / address distinct from the store's `settings` origin, and does the courier require a scheduled pickup slot? | May add fields to the booking form |

---

## 20. Assumptions Recorded

1. Single admin user; no roles or permissions. Laravel's standard auth on the default `users` table; no registration routes.
2. Single currency, MYR; all money in sen as `INT UNSIGNED`, cast `integer` in Eloquent.
3. Guest checkout only; no customer accounts (§11).
4. Malaysia-only shipping.
5. Maximum two option axes per product (size + colour). Planning §7.2 lifts this if ever needed.
6. **One parcel per order** for both rate checking and booking — no multi-box splitting. This is what allows `shipments` to have `UNIQUE(order_id)` and no `shipment_items` table (Planning §12.3).
7. Order status is advanced manually by the admin after payment (§14).
8. ToyyibPay provides **no** callback signature — hence the mandatory server-side re-query.
9. Product images uploaded to `public/uploads`; no CDN, no image service, no `storage:link`.
10. **PHP 8.3 and Laravel 12 are client-mandated (§5).** PHP 8.3 is security-supported to 2027-12-31; nothing uses a feature above 8.3.
11. EasyParcel is authorised **once** by an admin if on the Open API; the rotating refresh token sustains it ~1 year. Re-authorisation is a manual admin action surfaced on Settings before it lapses.
11a. **Shipment booking is an admin action, not automatic on payment** (Planning §11.B.5.2, OQ-14).
11b. **The EasyParcel credit balance is funded and monitored outside the application** (OQ-12). Top-up is a manual EasyParcel-dashboard action; no second payment integration is built to fund it.
11c. **Courier credit is the store's own money.** Every successful booking is a real charge. `shipments.cost_minor` records what was actually charged so it can be compared against the `orders.shipping_fee_minor` the customer paid.
12. No Node.js in production (subject to OQ-10).
13. `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. No session, cache or job tables.
14. `APP_KEY` generated once and backed up with the database.
15. **VPS deploy, MySQL 8.0, client project** — confirmed at intake (§0).

---

## 21. Approval Gate (§35)

Per **§35**, work stops here. **Nothing has been created** — no Laravel installation, no packages, no migrations, no models, no Form Requests, no services, no controllers, no views, no API configuration, and no existing application file has been modified.

**The initial deliverable is `Planning.md` only.**

**To proceed, please:**

1. **Approve the variation design** (Planning §7.1) and the note on "normalized" in §7.
2. **Approve the cart and checkout designs** (Planning §8, §9) — in particular the session cart and server-side recalculation of every total.
3. **Approve the stock-decrement rule** and its oversell trade-off (Planning §11.A.5 + §16 Inventory).
4. **Confirm REQ-013 (shipment booking, AWB & tracking) is in scope** and acknowledge what it brings with it: **~+2 days** (revised estimate ~11 days), a 10th table, a reconciliation screen, and an **ongoing courier-credit balance the store must fund and watch**. Approve the safeguards in Planning §11.B.5 — in particular that booking is an **admin action**, that `UNIQUE(order_id)` is the anti-double-booking guard, and that an ambiguous `pay` outcome goes to `needs_reconciliation` and is **never auto-retried**.
5. **Answer OQ-01, OQ-02 and OQ-03** — OQ-03 in particular, because the answer changes the Phase 8 design and the table count.
6. **Answer OQ-10** — the asset-pipeline decision, which deviates from the folder list in §19 and should be a knowing choice.
7. **Confirm OQ-11** — a human has verified the ToyyibPay response fields. No real payment can settle until then, by design.
8. **Answer OQ-08 and OQ-09** — the framework upgrade obligation and the recurring VPS cost. Neither is covered by the RM1,000 build budget.
9. **Answer OQ-12 through OQ-16** — the REQ-013 questions. **OQ-13 blocks Phase 8b outright**: the booking payloads must be read from the official specification and recorded before any booking code is written (§3). OQ-12 is the one with a recurring cost attached.
