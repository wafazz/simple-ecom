# Basic Custom E-Commerce

Single-vendor storefront for a small business — product variations, guest checkout,
ToyyibPay payment, and EasyParcel courier rates + shipment booking.

| | |
|---|---|
| Stack | Laravel 12 · PHP 8.3 · MySQL 8.0 · Blade · Bootstrap 5 |
| Spec | [`Prompt.txt`](Prompt.txt) — 36 sections, authoritative |
| Plan | [`Planning.md`](Planning.md) — approved 2026-08-26, `REQ-001`…`REQ-013` |
| Docs | [`docs/documentation.md`](docs/documentation.md) |
| Status | **Phase 2 complete** (Laravel foundation). Phase 3 (database) next. |

## Local setup

Requires PHP 8.3+ and Composer. **No Node** — assets are static files under `public/`
(see `Planning.md` §12.2).

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate
php artisan serve
```

> ⚠ **`APP_KEY` is generated once and never rotated.** Rotating it makes the encrypted
> `integration_tokens` rows undecryptable (`Planning.md` §11.B.3).

### Database driver

`DB_CONNECTION=mysql` for the MySQL 8.0 production target. **On a MariaDB host use
`DB_CONNECTION=mariadb`** — under the `mysql` driver, `renameColumn()` is a hard syntax
error on MariaDB ≤ 10.5.2. See `Planning.md` §17.7.

## Commands

```bash
php artisan test        # PHPUnit 11
./vendor/bin/pint       # style gate
composer audit          # dependency advisories — run before every release
```

## Conventions that are load-bearing

Full detail in `Planning.md`; these are the ones that cause silent damage if broken.

- **Money is integer sen**, column suffix `_minor`. No float touches the payment path.
- **Never trust a payment callback.** Re-query ToyyibPay server-side and match amount +
  external reference (§11.A.5).
- **Stock decrements via one guarded `UPDATE`**, affected-row count asserted. Never
  `SELECT` then `UPDATE`.
- **Never call `env()` outside `config/`** — after `config:cache` it returns null in
  production. Silent, and only in prod.
- **Unused variant option slots store `''`, never `NULL`** — MySQL treats NULLs as
  distinct in a unique index.
- **A shipment is booked at most once per order** (`UNIQUE(shipments.order_id)`), and an
  ambiguous `pay` outcome goes to `needs_reconciliation` — **never auto-retried**.

## Deployment

VPS, document root on `public/`. See `Planning.md` §17.
