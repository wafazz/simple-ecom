# DEPLOYMENT.md — Basic Custom E-Commerce

> Target: **VPS**, Linux + Nginx, PHP 8.3, MySQL 8.0, HTTPS.
> Plan reference: `Planning.md` §17. Spec reference: `Prompt.txt` §33.

⚠ **Read §7 before taking real payments.** Two items are unresolved and one of
them prevents live payments settling at all. That is deliberate, not a defect.

---

## 1. Server requirements

| | |
|---|---|
| PHP | **8.3** (8.4 also works; the app targets 8.3) |
| Extensions | `ctype` `curl` `dom` `fileinfo` `filter` `hash` `mbstring` `openssl` `pcre` `pdo` `pdo_mysql` `session` `tokenizer` `xml` |
| Memory limit | ≥ 128 MB |
| Database | MySQL 8.0 (MariaDB works — see §6) |
| Composer | 2.x |
| Web server | Nginx or Apache with a document root on `public/` |
| HTTPS | **Required.** ToyyibPay will not call an insecure callback URL |
| Node.js | **Not required.** There is no build step — assets are static files |
| Queue worker | **Not required.** `QUEUE_CONNECTION=sync`; no Supervisor, no cron for queues |

```bash
sudo apt update
sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-{cli,mbstring,xml,curl,zip,mysql,bcmath} \
  composer certbot python3-certbot-nginx git unzip
```

---

## 2. Deploy

```bash
cd /var/www
git clone <repo> basic-ecom
cd basic-ecom

composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate          # ⚠ ONCE ONLY — see §5
```

Edit `.env` — at minimum:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com     # must be the public HTTPS host

DB_CONNECTION=mysql                 # 'mariadb' on a MariaDB host — see §6
DB_DATABASE=basic_ecom
DB_USERNAME=...
DB_PASSWORD=...
```

Create the database, then:

```bash
php artisan migrate --force
php artisan db:seed --class=SettingSeeder --force    # store defaults only
php artisan shop:create-admin                        # prompts, never echoes
```

> **Do not run the full `db:seed` in production.** `DemoCatalogSeeder` inserts
> demo products, and `AdminSeeder` will refuse to run without `ADMIN_EMAIL` and
> `ADMIN_PASSWORD` set. `shop:create-admin` is the supported path.

Permissions and caches:

```bash
sudo mkdir -p public/uploads/products
sudo chown -R www-data:www-data storage bootstrap/cache public/uploads
sudo chmod -R 775 storage bootstrap/cache public/uploads

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> After `config:cache`, `env()` outside `config/` returns **null**. The app never
> calls it elsewhere — keep it that way.

`php artisan storage:link` is **not** needed: product images are written directly
into `public/uploads`.

> ⚠ **Re-run the ownership block after every `git pull`.** Pulled files arrive
> owned by the deploy user, and `public/uploads` is the one that bites: the
> first image upload has to create `public/uploads/products/`, and if the web
> server cannot write there the admin gets a validation error saying so. Before
> that error existed this was a bare 500 page, because a failed `mkdir` raises
> `UnableToCreateDirectory`, which the disk's `'throw' => false` does **not**
> cover.

---

## 3. Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/basic-ecom/public;   # NOT the project root

    index index.php;
    charset utf-8;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 4M;           # product image uploads are capped at 2 MB
}
```

```bash
sudo certbot --nginx -d your-domain.com
```

Document root on `public/` is what keeps `.env`, `app/`, `config/`, `database/`,
`storage/`, `routes/`, `resources/` and `vendor/` off the web entirely.

---

## 4. Third-party configuration

### ToyyibPay (REQ-005)

```
TOYYIBPAY_SECRET_KEY=...
TOYYIBPAY_CATEGORY_CODE=...
TOYYIBPAY_SANDBOX=false
TOYYIBPAY_AMOUNT_FORMAT=decimal      # see §7
```

Register these in the ToyyibPay dashboard:

| | |
|---|---|
| Return URL | `https://your-domain.com/payment/toyyibpay/return` |
| Callback URL | `https://your-domain.com/payment/toyyibpay/callback` |

**Verify the callback URL is publicly reachable over HTTPS before the first real
payment.** It cannot reach `localhost`.

### EasyParcel (REQ-006)

```
EASYPARCEL_CLIENT_ID=...
EASYPARCEL_CLIENT_SECRET=...
EASYPARCEL_SANDBOX=false
EASYPARCEL_WEIGHT_UNIT=kg            # see §7
```

Register `https://your-domain.com/admin/integrations/easyparcel/callback` as the
redirect URI in the EasyParcel developer portal, then connect once from
**Admin → Integrations**. The connection renews itself from then on.

Set the pickup origin in **Admin → Settings** — postcode and state. A wrong
origin means a wrong rate on *every* order.

---

## 5. Things that are irreversible

**`APP_KEY` is generated once and never rotated.** It encrypts the EasyParcel
tokens in `integration_tokens`. Rotating it makes those rows undecryptable —
recovery is disconnecting and reconnecting EasyParcel, so it is survivable, but
avoid it. Back `.env` up **separately from the database**; a backup containing
both is a backup that leaks its own key.

**The app cipher is `AES-256-GCM`** and must not change after the first token is
stored, for the same reason.

---

## 6. MySQL vs MariaDB

The app is written for **MySQL 8.0** and `DB_CONNECTION=mysql`.

If the host runs **MariaDB**, set `DB_CONNECTION=mariadb`. Under the `mysql`
driver, `renameColumn()` is a hard syntax error on MariaDB ≤ 10.5.2 — a future
migration would fail on deploy rather than in testing. The collation is already
`utf8mb4_unicode_ci`, which exists on both, so no schema change is needed either
way.

The suite is run against both engines before release.

---

## 7. ⚠ Unresolved before go-live

| | |
|---|---|
| **OQ-11 — ToyyibPay response fields** | The `getBillTransactions` response field names are **unverified**; the official reference returns 403 to automated fetch. `ToyyibPayService` **fails closed**: any response it cannot positively recognise leaves the order **pending** rather than settling it. **Live payments will not settle until a human confirms the field names** against the official reference and, if needed, adjusts the candidate keys and `TOYYIBPAY_AMOUNT_FORMAT`. This is deliberate — marking an unpaid order paid is worse than failing to settle a paid one. |
| **OQ-13 — EasyParcel booking payloads** | Shipment booking, AWB and tracking (REQ-013) are **not built**. The `shipment/submit` and `shipment/pay` request/response shapes were never verified, and inventing them was refused. Rates work; booking is a separate piece of work. |

**Diagnosing "payments are not settling":** check `storage/logs` for
`Payment left UNVERIFIED` with a `reason`. That reason names exactly what the
response did not contain. It is OQ-11, not a bug.

---

## 8. Post-deploy checklist

- [ ] `https://your-domain.com` loads over HTTPS
- [ ] `/up` returns 200
- [ ] Admin login works; the forced password change appears and completes
- [ ] Catalogue: create a category, product and variation; upload an image
- [ ] Storefront: product appears with correct price and stock
- [ ] Checkout: courier rates return (or the flat rate appears if not connected)
- [ ] Order is created with correct totals
- [ ] ToyyibPay redirect reaches the gateway
- [ ] Callback URL reachable over HTTPS from outside
- [ ] `storage/logs` is being written
- [ ] `.env` is **not** reachable at `https://your-domain.com/.env` (must 404)
- [ ] Nightly backup configured: `mysqldump` + `public/uploads` + `.env` stored separately
- [ ] `composer audit` clean

**Go-live smoke test:** one real low-value transaction, end to end, reconciled
against the ToyyibPay dashboard.

---

## 9. Routine operations

```bash
# Deploy an update
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Before every release
composer audit
php artisan test

# Reset the admin password
php artisan shop:create-admin --email=admin@your-domain.com
```

**Backups.** Nightly `mysqldump`, `public/uploads`, and `.env` — the last stored
somewhere the database dump is not. A store without a backup is a store with a
deadline.

**Logs.** `storage/logs`, daily files, 14-day retention. Every request carries a
correlation id, echoed in the `X-Request-Id` response header — quote it when
chasing a specific order through the logs.
