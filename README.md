# Accounter (Malaysia Edition)

Multi-tenant accounting SaaS built with **Laravel 12**, **Inertia.js**, and **React**. Each company gets its own database (Stancl Tenancy). Use this guide to run the app locally and test core flows.

## Requirements

| Tool | Version / notes |
|------|-----------------|
| PHP | **8.2+** (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` recommended) |
| Composer | 2.x |
| Node.js | **18+** (LTS) |
| Database | **MySQL 8+** or **MariaDB** (recommended; matches typical production and tenant DB creation). SQLite can work for the central DB only if your Tenancy/database config supports it—**MySQL is the path least likely to surprise you**. |

## 1. Clone and install dependencies

```bash
git clone <repository-url> accounting-saas
cd accounting-saas
composer install
npm install
```

## 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

- **`APP_URL`** — Must match how you open the app (e.g. `http://127.0.0.1:8000`). Tenancy central domains include `localhost` and `127.0.0.1` (see `config/tenancy.php`).
- **Database (central)** — Example for MySQL:

  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=accounter_central
  DB_USERNAME=your_user
  DB_PASSWORD=your_password
  ```

  Create the empty central database first:

  ```sql
  CREATE DATABASE accounter_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

- **Session / cache / queue** — `.env.example` uses `database` drivers. After the first migration, those tables exist on the **central** connection.

- **Mail** — Default `MAIL_MAILER=log` is fine for local testing (no real email).

## 3. Central database migrations

Central DB holds users, tenants, plans, subscriptions, sessions, jobs, etc.

```bash
php artisan migrate
```

The plans migration seeds an initial **Pro** plan if none exists. Subscriptions use **mock checkout** in local/dev (no Stripe required).

## 4. MySQL user permissions (important for new sign-ups)

When a user **registers**, the app creates a **new tenant database** and runs **tenant migrations** automatically. The MySQL user in `.env` needs permission to create databases, for example:

```sql
GRANT ALL PRIVILEGES ON accounter_central.* TO 'your_user'@'localhost';
GRANT CREATE ON *.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

(Adjust host/user to match your setup; some teams use a dedicated dev user with `CREATE` on `*` or on a naming pattern.)

## 5. Frontend dev server

Vite serves React/Inertia assets in development:

```bash
npm run dev
```

For a one-off production-style build:

```bash
npm run build
```

## 6. Application server

In a **second** terminal (keep `npm run dev` running for hot reload):

```bash
php artisan serve
```

Open **`http://127.0.0.1:8000`** (or the URL `artisan serve` prints).

### One-command dev stack (optional)

From `composer.json`:

```bash
composer dev
```

This runs the PHP server, queue worker, logs, and Vite together. Requires `npm install` already done.

## 7. Queue worker (optional for local)

If you test **invoice email** or other queued jobs with `QUEUE_CONNECTION=database`:

```bash
php artisan queue:work
```

With `MAIL_MAILER=log`, jobs still run; check `storage/logs/laravel.log`.

---

## Testing the system (recommended flow)

### Do not rely on the default DB seeder for manual UI testing

`DatabaseSeeder` creates a user **without** a `tenant_id`. The app expects logged-in users to belong to a tenant. **Use registration instead.**

### Step A — Register a company

1. Go to **`/register`**.
2. Create an account (name, email, password).

This creates a **Tenant** and a dedicated **tenant database**, then runs **tenant migrations** on it.

### Step B — Explore free-tier features

Without paying, you can still use routes allowed by `EnsureSubscribed`, including:

- Dashboard  
- Invoices, customers, credit notes  
- Profile, company settings  
- Subscription page  

### Step C — Unlock “paid” modules (mock subscription)

1. Open **`/subscription`**.  
2. Choose a plan and complete checkout (**mock** gateway—no card).  

Then you can reach suppliers, bills, accounts payable, chart of accounts, general ledger, reports, etc.

### Step D — Chart of accounts (tenant)

For GL-linked features and reports, your tenant needs accounts (e.g. `1100`, `4000`, `2100`). After you have subscription access:

1. Go to **Chart of Accounts**.  
2. Use **Seed default** (or equivalent) to create the starter chart.  

This keeps journal postings and financial reports aligned with account codes used by invoices and bills.

### Step E — Smoke-test accounting flows

Suggested order:

1. **Customers** → create a customer.  
2. **Invoices** → create a draft → **post** (check journal / GL if enabled).  
3. **Record payment** (bank account from chart).  
4. **Credit note** (against an invoice) if you test returns.  
5. With subscription: **Suppliers** → **Bills** → post → pay.  
6. **Reports** (P&L, balance sheet, etc.) for the same period.

---

## Applying new tenant migrations later

If you pull code that adds files under `database/migrations/tenant/`, run for **existing** tenant databases:

```bash
php artisan tenants:migrate
```

Migrate **one** tenant only (use the tenant id from `php artisan tenants:list`, e.g. `asyraf-pauzi_546`):

```bash
php artisan tenants:migrate --tenants=asyraf-pauzi_546
```

### “Nothing to migrate” — which command did you run?

| Command | Database | Meaning |
|---------|----------|---------|
| **`php artisan migrate`** | **Central only** (users, tenants, plans, sessions) | If this says *Nothing to migrate*, the central DB is already up to date. It does **not** touch company databases. |
| **`php artisan tenants:migrate`** | **Each tenant** (invoices, customers, GL, …) | This applies everything under `database/migrations/tenant/`. Run this after pulling code that adds/changes **tenant** migrations. |

So: errors like *Unknown column `customers.deleted_at`* are almost always fixed by **`php artisan tenants:migrate`**, not by `php artisan migrate`.

If **`tenants:migrate`** also reports nothing but the column is still missing, the `migrations` table may be out of sync with the real schema. Run the repair command (adds `deleted_at` only where it is missing):

```bash
php artisan tenants:repair-soft-deletes
# or one tenant:
php artisan tenants:repair-soft-deletes --tenants=your-tenant-id
```

You can still verify in MySQL: `SHOW COLUMNS FROM customers LIKE 'deleted_at';` on the tenant database (often named `tenant{tenant_id}`).

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| **“Nothing to migrate” but tenant app errors (missing columns)** | You probably only ran **`php artisan migrate`**. Run **`php artisan tenants:migrate`**. Use **`php artisan tenants:list`** to confirm tenants exist. |
| Registration fails on database | MySQL user can **CREATE DATABASE**; connection credentials; server running. |
| `SQLSTATE[42000] ... only_full_group_by` | Use a recent codebase; some aggregate queries require MySQL-compatible SQL. Prefer MySQL 8 defaults or avoid disabling `ONLY_FULL_GROUP_BY` without verifying queries. |
| Blank / no styles | Run **`npm run dev`** (or **`npm run build`**) so Vite builds assets. |
| “Please upgrade your plan” on some pages | Complete **mock subscription** on `/subscription`, or stay on free-tier routes (invoices, customers, credit notes, dashboard, etc.). |
| Tenant DB missing tables | Ensure registration completed without errors; run `php artisan tenants:migrate` if you added tenant migrations after sign-up. |
| `Table 'tenant{…}.cache' doesn't exist` (Spatie permission cache, etc.) | Infrastructure tables (`cache`, `sessions`, `jobs`) are on the **central** DB. Config defaults `DB_CACHE_CONNECTION`, `SESSION_CONNECTION`, and `DB_QUEUE_CONNECTION` to `DB_CONNECTION` so they do not follow the tenant default. Run `php artisan config:clear` after pulling. Optionally set those env vars explicitly to your central connection name (e.g. `mysql`). |
| **403** on routes with `permission:…` even when you have the **admin** role | The **admin** role may exist but have **no permissions linked** (role was created before seeding), or **permission rows** are missing. Run **`php artisan app:sync-roles-permissions`** (central DB), then **`php artisan optimize:clear`**, then **log out and log in** again. Still blocked? In Tinker: `\App\Models\Permission::where('name','invoices.view')->first()` must not be null; `$user->getAllPermissions()->pluck('name')` should list `invoices.view`. |

---

## Scripts reference

| Command | Purpose |
|---------|---------|
| `composer install` | PHP dependencies |
| `composer setup` | Install, `.env`, key, migrate, npm install, build (automation-friendly) |
| `composer dev` | Serve + queue + logs + Vite |
| `npm run dev` | Vite dev server |
| `npm run build` | Production asset build |
| `php artisan migrate` | Central migrations only |
| `php artisan tenants:migrate` | All tenant DBs (`database/migrations/tenant`) |
| `php artisan tenants:list` | Show tenant ids (for `--tenants=...`) |
| `php artisan app:sync-roles-permissions` | Re-seed Spatie roles/permissions (central) + clear permission cache |
| `php artisan queue:work` | Process queued jobs |
| `php artisan test` | PHPUnit tests |

---

## Security note for production

Before going live: set `APP_DEBUG=false`, use strong `APP_KEY`, configure real mail and (if used) a proper payment gateway instead of mock checkout, review tenancy domains, and run backups for **each** tenant database as well as the central DB.
