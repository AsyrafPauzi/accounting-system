# Wave 1 — Trust Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix ledger honesty, payment webhook security, production scheduling, CI gates, and firm RBAC so BukuCloud can pass an accountant’s first month-end and stop leaking SaaS revenue.

**Architecture:** Introduce a single `JournalWriter` for all system postings (status, date, type). Add `PostedJournalScope` query helper used by all financial reports. Verify ToyyibPay server-side before activating subscriptions. Add scheduler program to ECS supervisor. Split GitHub Actions into test + deploy jobs. Replace `Gate::before` blanket grant with `FirmClient.permission_level` → permission set mapping (reuse existing Spatie role permission lists).

**Tech Stack:** Laravel 12, PHPUnit 11, Stancl Tenancy, GitHub Actions, ECS Fargate, Supervisor

## Global Constraints

- Tenant migrations live under `database/migrations/tenant/`; run `php artisan tenants:migrate` after schema changes.
- Do not change plan pricing or feature gates in Wave 1.
- ToyyibPay first checkout stays; Billplz renewal webhook already exists — only harden verification.
- Self-hosted docker-compose already has a scheduler service; ECS root `Dockerfile` uses `docker/supervisor.conf`.
- Tests use SQLite in-memory per `phpunit.xml`; tenant tests need a tenancy bootstrap harness.

---

## Task 1: JournalWriter + posted scope

**Files:**
- Create: `app/Support/JournalWriter.php`
- Create: `app/Support/PostedJournalScope.php`
- Create: `tests/Unit/Support/JournalWriterTest.php`
- Modify: `app/Services/InvoiceService.php` (post, payment, void reversals)
- Modify: `app/Services/BillService.php`
- Modify: `app/Services/CreditNoteService.php`
- Modify: `app/Services/DebitNoteService.php`
- Modify: `app/Services/ArDepositService.php`
- Modify: `app/Services/ApDepositService.php`
- Modify: `app/Services/SupplierCreditNoteService.php`
- Modify: `app/Services/SupplierDebitNoteService.php`

**Interfaces:**
- Consumes: existing `journal_entries` / `journal_items` schema
- Produces:
  - `JournalWriter::postSystem(array $header, array $lines): int` — returns journal id
  - `JournalWriter::postReversal(int $journalId, string $description, ?string $date = null): int`
  - `PostedJournalScope::apply(Builder|QueryBuilder $query, string $journalAlias = 'journal_entries'): void`

**Header shape:**
```php
[
    'date' => 'Y-m-d',
    'description' => string,
    'reference_type' => ?string,
    'reference_id' => ?int,
    'type' => 'system', // always for auto-posts
    'status' => 'posted', // always for auto-posts
]
```

**Lines shape:**
```php
[['account_code' => '1100', 'debit' => 100.0, 'credit' => 0.0, 'description' => ?string], ...]
```

- [ ] **Step 1: Write failing unit test for balanced post**

```php
// tests/Unit/Support/JournalWriterTest.php
public function test_post_system_creates_posted_journal_with_balanced_lines(): void
{
    // bootstrap minimal tenant accounts 1100, 4000 if harness exists
    // or use RefreshDatabase on tenant connection in Feature test variant
    $id = JournalWriter::postSystem(
        ['date' => '2026-01-15', 'description' => 'Test', 'reference_type' => null, 'reference_id' => null],
        [
            ['account_code' => '1100', 'debit' => 100, 'credit' => 0],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 100],
        ]
    );
    $this->assertDatabaseHas('journal_entries', ['id' => $id, 'status' => 'posted', 'date' => '2026-01-15']);
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test tests/Unit/Support/JournalWriterTest.php -v`

- [ ] **Step 3: Implement JournalWriter**

```php
// app/Support/JournalWriter.php — validate sum(debit)==sum(credit), insert header+lines, throw LogicException if unbalanced
```

- [ ] **Step 4: Implement PostedJournalScope**

```php
// app/Support/PostedJournalScope.php
public static function apply($query, string $alias = 'journal_entries'): void
{
    $query->where("{$alias}.status", 'posted');
}
```

- [ ] **Step 5: Refactor InvoiceService::post()**

Change:
- `'date' => now()` → `'date' => $invoice->issue_date->toDateString()` (or Carbon parse)
- Replace `DB::table('journal_entries')->insertGetId([...])` with `JournalWriter::postSystem(...)`

Same for `recordPayment`, `reversePayment`, `void` reversal paths in InvoiceService.

- [ ] **Step 6: Refactor BillService, CreditNoteService, DebitNoteService, deposit services**

Every `insertGetId` on `journal_entries` must go through JournalWriter with explicit date and `status=posted`.

- [ ] **Step 7: Run tests + smoke**

Run: `php artisan test tests/Unit/Support/JournalWriterTest.php`
Manual: post invoice in dev; confirm `journal_entries.status = posted`.

- [ ] **Step 8: Commit**

```bash
git add app/Support/JournalWriter.php app/Support/PostedJournalScope.php app/Services/*.php tests/
git commit -m "fix: post system journals as posted via JournalWriter"
```

---

## Task 2: Financial reports filter posted only

**Files:**
- Modify: `app/Http/Controllers/ProfitAndLossController.php`
- Modify: `app/Http/Controllers/BalanceSheetController.php`
- Modify: `app/Http/Controllers/TrialBalanceController.php`
- Modify: `app/Http/Controllers/GeneralLedgerController.php`
- Modify: `app/Support/AccountLedger.php`
- Modify: `app/Http/Controllers/DashboardController.php` (GL-based KPIs if any)
- Create: `tests/Unit/Support/PostedJournalScopeTest.php` or extend Feature test

- [ ] **Step 1: Write failing test — draft journal excluded from P&L**

Create tenant Feature test: post invoice (posted journal) + create manual draft journal on expense account → P&L shows only posted amount.

- [ ] **Step 2: Apply PostedJournalScope to all report queries**

In each controller query joining `journal_entries`, add:
```php
PostedJournalScope::apply($query, 'journal_entries');
```

Also update `AccountLedger::openingBalance` and balance queries.

- [ ] **Step 3: Run report-related tests**

Run: `php artisan test tests/Unit/Support/AccountLedgerTest.php`

- [ ] **Step 4: Commit**

```bash
git commit -m "fix: financial reports include posted journals only"
```

---

## Task 3: Balance sheet current-year earnings

**Files:**
- Modify: `app/Http/Controllers/BalanceSheetController.php`
- Create: `app/Support/CurrentYearEarnings.php` (optional small helper)
- Test: extend balance sheet Feature/Unit test

- [ ] **Step 1: Write failing test**

After posting one sale (Dr AR, Cr Revenue 1000): `total_assets - total_liabilities_and_equity < 0.01` must pass when earnings line included.

- [ ] **Step 2: Compute YTD P&L net for fiscal year containing `as_at_date`**

```php
// Sum income - expense from posted journals, date <= as_at_date, within FY start from company settings
$currentYearEarnings = ...;
$equityAccounts[] = ['code' => '—', 'name' => 'Current year earnings', 'amount' => $currentYearEarnings];
$totalEquity += $currentYearEarnings;
```

- [ ] **Step 3: Expose `current_year_earnings` in Inertia props for BalanceSheet.jsx**

- [ ] **Step 4: Run test + commit**

```bash
git commit -m "fix: balance sheet includes current year earnings"
```

---

## Task 4: ToyyibPay verification + Billplz fail-closed

**Files:**
- Modify: `app/Services/ToyyibpayService.php` — add `getBillTransactions(string $billCode): ?array`
- Modify: `app/Http/Controllers/SubscriptionController.php` — `webhook`, `webhookExtraUser`, `webhookCopilotCredits`
- Modify: `app/Http/Controllers/InvoiceController.php` — `toyyibpayCallback` if present
- Modify: `app/Services/BillplzService.php` — `callbackIsPaid` require x-signature when key configured; **reject when key missing in production**
- Modify: `tests/Feature/Practice/PracticeBillingUpgradeTest.php` — add negative test
- Create: `tests/Feature/Billing/ToyyibpayWebhookSecurityTest.php`

**ToyyibPay verify flow:**
```php
// After status_id == 1:
$txns = $toyyibpay->getBillTransactions($billCode);
// Require at least one txn with status paid AND external ref matches expected order_id
// If verify fails: Log::warning + return 403 (not 200 OK)
```

Use ToyyibPay API: `getBillTransactions` endpoint (document in service PHPDoc).

- [ ] **Step 1: Write failing test — unsigned/guessed webhook rejected**

```php
$this->postJson('/subscription/webhook', ['status_id' => 1, 'order_id' => $sub->id, 'billcode' => 'fake'])
    ->assertStatus(403);
$this->assertSame('pending', $sub->fresh()->status);
```

- [ ] **Step 2: Implement getBillTransactions + verify helper**

- [ ] **Step 3: Wire all ToyyibPay webhook handlers**

- [ ] **Step 4: Billplz — fail closed**

```php
// BillplzService::callbackIsPaid
if (! $this->xSignatureKey) {
    return false; // was: fall through to paid flag
}
```

- [ ] **Step 5: Run billing tests**

Run: `php artisan test tests/Feature/Practice/PracticeBillingUpgradeTest.php tests/Feature/Billing/`

- [ ] **Step 6: Commit**

```bash
git commit -m "fix: verify ToyyibPay webhooks and fail-closed Billplz HMAC"
```

---

## Task 5: ECS production scheduler

**Files:**
- Modify: `docker/supervisor.conf` — add `[program:laravel-scheduler]`
- Modify: `DEPLOYMENT.md` — document scheduler program
- Optional: `docker/entrypoint.sh` comment only (no behavior change)

**Supervisor addition:**
```ini
[program:laravel-scheduler]
command=/bin/sh -c "while true; do php /var/www/html/artisan schedule:run --no-interaction; sleep 60; done"
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/www/html/storage/logs/scheduler.log
```

- [ ] **Step 1: Add scheduler program to supervisor.conf**

- [ ] **Step 2: Verify schedule list locally**

Run: `php artisan schedule:list`

Confirm: `subscription:issue-renewals`, `invoices:send-reminders`, `invoices:generate-recurring`, `subscription:expire`.

- [ ] **Step 3: Update DEPLOYMENT.md §6 Troubleshooting — mention scheduler.log**

- [ ] **Step 4: Commit**

```bash
git commit -m "ops: run Laravel scheduler in ECS supervisor"
```

---

## Task 6: CI test + build gate

**Files:**
- Create: `.github/workflows/test.yml` OR modify `.github/workflows/deploy.yml`
- Modify: `.github/workflows/deploy.yml` — `needs: test` job

**Recommended: single workflow, two jobs**

```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', extensions: pdo_sqlite, ... }
      - run: composer install
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci && npm run build

  deploy:
    needs: test
    # existing deploy steps...
```

- [ ] **Step 1: Add test job**

- [ ] **Step 2: Make deploy depend on test**

- [ ] **Step 3: Run locally**

Run: `composer test && npm run build`

- [ ] **Step 4: Commit**

```bash
git commit -m "ci: require tests and frontend build before ECS deploy"
```

---

## Task 7: Firm RBAC by permission_level

**Files:**
- Create: `app/Support/FirmActingPermissions.php`
- Modify: `app/Providers/AppServiceProvider.php` — replace `return true` with level check
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` — `projectedPermissions()` uses same helper
- Create: `tests/Feature/Practice/FirmViewerWriteBlockedTest.php`

**Permission mapping (reuse seeder lists):**

| FirmClient.permission_level | Effective tenant permissions |
|---|---|
| `viewer` | Same as Spatie `viewer` role |
| `editor` | Same as Spatie `accountant` role |
| `admin` | Same as Spatie `admin` role (minus `admin.*`) |

```php
// app/Support/FirmActingPermissions.php
public static function allowedForLevel(string $level): array
public static function userMay($user, string $ability): ?bool // null = defer to Spatie
```

**Gate::before change:**
```php
$level = FirmClient::where('firm_id', $user->firm_id)
    ->where('tenant_id', tenant('id'))
    ->where('status', 'active')
    ->value('permission_level');
if (! in_array($ability, self::allowedForLevel($level ?? 'viewer'), true)) {
    return false;
}
return true; // only for allowed abilities
```

- [ ] **Step 1: Write failing test**

Firm user with `permission_level=viewer` acting on client POSTs invoice create → 403.

- [ ] **Step 2: Implement FirmActingPermissions**

Extract permission name arrays from `RolesAndPermissionsSeeder` into constants or read from Role models at boot (cache once).

- [ ] **Step 3: Update Gate::before and projectedPermissions**

- [ ] **Step 4: Run practice tests**

Run: `php artisan test tests/Feature/Practice/`

- [ ] **Step 5: Commit**

```bash
git commit -m "fix: honor firm viewer/editor/admin when acting on client tenant"
```

---

## Task 8: Wave 1 integration smoke + docs

**Files:**
- Modify: `ROADMAP.md` — note Wave 1 ledger fixes in progress/done
- Optional: `README.md` — CI badge note

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`

- [ ] **Step 2: Manual smoke checklist**

1. Register tenant → seed COA → create customer → create invoice → post → P&L shows revenue
2. Balance sheet balanced flag true
3. Firm viewer cannot create invoice when acting
4. `php artisan schedule:run` runs without error locally

- [ ] **Step 3: Update master plan status**

Mark Wave 1 items complete in `2026-08-27-beat-competitors-waves-master.md`.

- [ ] **Step 4: Commit**

```bash
git commit -m "docs: mark Wave 1 trust foundation complete"
```

---

## Wave 1 exit checklist ✅ Signed off 2026-08-27

- [x] All system journals created with `status=posted`
- [x] P&L, BS, TB, GL, AccountLedger filter posted
- [x] Balance sheet balances after posted sale
- [x] Invoice post uses `issue_date`
- [x] ToyyibPay webhooks verified server-side
- [x] Billplz rejects unsigned callbacks when x-signature key set; rejects all in prod when key missing
- [x] ECS supervisor runs scheduler every 60s
- [x] GitHub Actions test job blocks deploy
- [x] Firm viewer gets 403 on write routes
- [x] At least 3 new security/GL tests green

**Target score after Wave 1:** overall **≥ 6.6** — **375 tests green** (commit `cf7d6bb`)

---

## Execution handoff

Plan complete. Saved to:

- Master: `docs/superpowers/plans/2026-08-27-beat-competitors-waves-master.md`
- Wave 1: `docs/superpowers/plans/2026-08-27-wave-1-trust-foundation.md`

**Next:** Implement Task 1 (JournalWriter) first — everything else depends on it.
