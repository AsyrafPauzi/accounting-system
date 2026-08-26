# Billplz Subscription Renewal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Issue Billplz payment-link renewals for monthly/yearly SaaS subscriptions before period end, with a 7-day `past_due` grace, so revenue no longer dies at expiry.

**Architecture:** Keep ToyyibPay for first checkout. Platform Billplz credentials create renewal bills stored in `subscription_renewals`. Cron issues bills; webhook extends periods from the prior period end; expire command moves unpaid subs through `past_due` → `expired`.

**Tech Stack:** Laravel 11, Billplz API v3 bills, PHPUnit (`/opt/homebrew/bin/php artisan test`), Inertia React Plan page, Laravel Mail, scheduler in `routes/console.php`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-27-billplz-subscription-renewal-design.md`
- Homebrew PHP: `/opt/homebrew/bin/php artisan test --filter=ClassName` (Herd `php` fails on dump-loader).
- Do **not** git commit unless the user explicitly asks (user rule overrides “frequent commits” in this skill). Skip every Commit step unless told to commit.
- Do **not** replace ToyyibPay first checkout. Do **not** implement mid-cycle paid→paid upgrades.
- Do **not** use tenant Billplz keys for SaaS renewals — only platform env keys.
- Self-hosted: renewal commands no-op when `Deployment::isSelfHosted()`.
- Zero ToyyibPay rebill fallback in v1.
- After React changes: `npm run build`. Hard-refresh (`Cmd+Shift+R`).
- Unit helpers: `Tests\Unit\…` extending `PHPUnit\Framework\TestCase` when no DB. Feature tests that need DB: use existing SQLite / central test patterns if present; otherwise prefer pure unit coverage of math + state helpers.

## File map

- Create: `database/migrations/2026_08_27_000001_create_subscription_renewals_table.php`
- Create: `app/Models/SubscriptionRenewal.php`
- Create: `app/Support/SubscriptionPeriod.php`
- Create: `app/Services/SubscriptionRenewalService.php`
- Create: `app/Console/Commands/IssueSubscriptionRenewals.php`
- Create: `app/Mail/SubscriptionRenewalDue.php`
- Create: `resources/views/emails/subscription-renewal-due.blade.php`
- Create: `tests/Unit/Support/SubscriptionPeriodTest.php`
- Create: `tests/Unit/Support/SubscriptionGraceTest.php` (or fold into Expire command unit via extracted helper)
- Modify: `config/services.php` (platform `billplz` block, zeroed on self-hosted)
- Modify: `config/subscriptions.php` (`renewal_lead_days`, `grace_days`)
- Modify: `.env.example` (document keys)
- Modify: `app/Services/BillplzService.php` (`forPlatform`, `createBillDetailed`)
- Modify: `app/Models/Subscription.php` (`past_due` in active scope / `isActive`)
- Modify: `app/Console/Commands/ExpireSubscriptions.php`
- Modify: `app/Http/Controllers/SubscriptionController.php` (webhook + planSettings props)
- Modify: `routes/web.php` (webhook route)
- Modify: `bootstrap/app.php` (CSRF except)
- Modify: `routes/console.php` (schedule issue-renewals)
- Modify: `resources/js/Pages/Settings/Plan.jsx` (overdue banner)

---

### Task 1: Platform Billplz config + createBillDetailed

**Files:**
- Modify: `config/services.php`
- Modify: `config/subscriptions.php`
- Modify: `.env.example`
- Modify: `app/Services/BillplzService.php`
- Test: `tests/Unit/Sales/SalesPolishHelpersTest.php` (existing Billplz signature tests must still pass)

**Interfaces:**
- Consumes: env `BILLPLZ_*`
- Produces:
  - `config('services.billplz')` → `secret_key`, `collection_id`, `xsignature_key`, `sandbox` (all null on self-hosted)
  - `config('subscriptions.renewal_lead_days')` default `7`
  - `config('subscriptions.grace_days')` default `7`
  - `BillplzService::forPlatform(): ?self`
  - `BillplzService::createBillDetailed(array $data): ?array{id:string,url:string}` — keep existing `createBill()` returning URL only for invoice Pay Now

- [ ] **Step 1: Add config blocks**

In `config/services.php` after `toyyibpay`:

```php
'billplz' => env('APP_DEPLOYMENT_MODE', 'saas') === 'self_hosted'
    ? [
        'secret_key' => null,
        'collection_id' => null,
        'xsignature_key' => null,
        'sandbox' => true,
    ]
    : [
        'secret_key' => env('BILLPLZ_SECRET_KEY'),
        'collection_id' => env('BILLPLZ_COLLECTION_ID'),
        'xsignature_key' => env('BILLPLZ_XSIGNATURE_KEY'),
        'sandbox' => filter_var(env('BILLPLZ_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
    ],
```

In `config/subscriptions.php` add:

```php
'renewal_lead_days' => (int) env('SUBSCRIPTION_RENEWAL_LEAD_DAYS', 7),
'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),
```

Document keys in `.env.example` (commented).

- [ ] **Step 2: Implement `forPlatform` + `createBillDetailed`**

```php
public static function forPlatform(): ?self
{
    $secret = (string) (config('services.billplz.secret_key') ?? '');
    $collection = (string) (config('services.billplz.collection_id') ?? '');
    if ($secret === '' || $collection === '') {
        return null;
    }
    $sandbox = (bool) config('services.billplz.sandbox', true);
    $base = $sandbox
        ? 'https://www.billplz-sandbox.com/api'
        : 'https://www.billplz.com/api';
    $xsig = config('services.billplz.xsignature_key');

    return new self($secret, $collection, $base, filled($xsig) ? (string) $xsig : null);
}

/**
 * @param  array{description:string,email:string,name:string,amount:float,callback_url:string,redirect_url:string,reference:string}  $data
 * @return array{id:string,url:string}|null
 */
public function createBillDetailed(array $data): ?array
{
    // same POST as createBill; parse id + url from JSON
    // on success return ['id' => (string) $json['id'], 'url' => (string) $json['url']]
}
```

Refactor `createBill` to call `createBillDetailed` and return `['url']` so Pay Now stays unchanged.

- [ ] **Step 3: Run existing Billplz unit tests**

Run: `/opt/homebrew/bin/php artisan test --filter=SalesPolishHelpersTest`

Expected: PASS

- [ ] **Step 4: Commit** — skip unless asked.

---

### Task 2: `subscription_renewals` table + model

**Files:**
- Create: `database/migrations/2026_08_27_000001_create_subscription_renewals_table.php`
- Create: `app/Models/SubscriptionRenewal.php`
- Modify: `app/Models/Subscription.php` (hasMany renewals)

**Interfaces:**
- Produces: `SubscriptionRenewal` on central connection with fillable fields from the spec; `Subscription::renewals()`

- [ ] **Step 1: Migration**

```php
Schema::create('subscription_renewals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained('plans');
    $table->string('interval', 20); // monthly|yearly
    $table->decimal('amount', 12, 2);
    $table->string('status', 20)->default('pending'); // pending|paid|failed|cancelled
    $table->string('gateway', 40)->default('billplz');
    $table->string('gateway_bill_id', 80)->nullable()->unique();
    $table->string('payment_url', 500)->nullable();
    $table->date('period_start');
    $table->date('period_end');
    $table->date('due_at');
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->index(['subscription_id', 'status']);
});
```

MySQL lacks partial unique indexes easily — enforce “one pending per subscription” in `SubscriptionRenewalService` + test.

- [ ] **Step 2: Model**

```php
namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SubscriptionRenewal extends Model
{
    use CentralConnection, Auditable;

    protected $fillable = [
        'subscription_id', 'plan_id', 'interval', 'amount', 'status',
        'gateway', 'gateway_bill_id', 'payment_url',
        'period_start', 'period_end', 'due_at', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_at' => 'date',
        'paid_at' => 'datetime',
    ];

    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
}
```

- [ ] **Step 3: Commit** — skip unless asked.

---

### Task 3: SubscriptionPeriod helper (TDD)

**Files:**
- Create: `app/Support/SubscriptionPeriod.php`
- Test: `tests/Unit/Support/SubscriptionPeriodTest.php`

**Interfaces:**
- Produces:
  - `SubscriptionPeriod::nextWindow(string $interval, string $priorPeriodEnd): array{period_start:string,period_end:string}`
  - Monthly: start = day after prior end (or prior end as exclusive? Spec: extend from prior end — use `period_start = priorEnd`, `period_end = priorEnd + 1 month` **or** start = priorEnd+1 day. **Lock:** `period_start = Carbon::parse($priorEnd)->addDay()->toDateString()`, `period_end = Carbon::parse($priorEnd)->addMonthNoOverflow()->toDateString()` for monthly; yearly use `addYearNoOverflow()`.
  - Actually matching existing first-checkout (`now()` → `now()->addMonth()`): renewal should be continuous. Prefer: `period_start = $priorEnd` (same calendar day rolls), `period_end = Carbon::parse($priorEnd)->addMonthNoOverflow()->toDateString()`. Document that in the helper docblock.
  - **Locked in this plan:** `period_start = prior period end date`, `period_end = priorEnd->copy()->addMonthNoOverflow()` / `addYearNoOverflow()`.

```php
final class SubscriptionPeriod
{
    /** @return array{period_start:string,period_end:string} */
    public static function nextWindow(string $interval, string $priorPeriodEnd): array
    {
        $start = \Carbon\Carbon::parse($priorPeriodEnd)->startOfDay();
        $end = match ($interval) {
            'yearly' => $start->copy()->addYearNoOverflow(),
            default => $start->copy()->addMonthNoOverflow(),
        };

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    public static function graceDeadline(string $periodEnd, ?int $graceDays = null): string
    {
        $days = $graceDays ?? (int) config('subscriptions.grace_days', 7);

        return \Carbon\Carbon::parse($periodEnd)->addDays($days)->toDateString();
    }
}
```

- [ ] **Step 1: Failing tests**

```php
public function test_monthly_extends_from_prior_end(): void
{
    $w = SubscriptionPeriod::nextWindow('monthly', '2026-08-31');
    $this->assertSame('2026-08-31', $w['period_start']);
    $this->assertSame('2026-09-30', $w['period_end']);
}

public function test_yearly_extends_from_prior_end(): void
{
    $w = SubscriptionPeriod::nextWindow('yearly', '2026-08-18');
    $this->assertSame('2026-08-18', $w['period_start']);
    $this->assertSame('2027-08-18', $w['period_end']);
}

public function test_grace_deadline(): void
{
    $this->assertSame('2026-09-07', SubscriptionPeriod::graceDeadline('2026-08-31', 7));
}
```

Adjust September end if Carbon overflow differs — run test and fix assertion **or** implementation until consistent.

- [ ] **Step 2: Run — expect FAIL**

Run: `/opt/homebrew/bin/php artisan test --filter=SubscriptionPeriodTest`

- [ ] **Step 3: Implement helper — PASS**

- [ ] **Step 4: Commit** — skip unless asked.

---

### Task 4: `past_due` counts as subscribed + expire grace

**Files:**
- Modify: `app/Models/Subscription.php`
- Modify: `app/Console/Commands/ExpireSubscriptions.php`
- Create: `app/Support/SubscriptionGrace.php` (pure status transition helper) **or** put `shouldMarkPastDue` / `shouldExpire` on `SubscriptionPeriod`
- Test: `tests/Unit/Support/SubscriptionGraceTest.php`

**Interfaces:**
- `Subscription::isActive()` and `scopeActive` include `past_due`
- Expire command:
  1. `active` where `current_period_ends_at < today` → `past_due`
  2. `past_due` where `graceDeadline(period_end) < today` → `expired`
  3. Skip rows that have a `paid` renewal whose `period_end` is still in the future covering them (if payment landed same day)

```php
// Subscription.php
public function scopeActive(Builder $query): Builder
{
    return $query->whereIn('status', ['active', 'trialing', 'past_due'])
        ->where(function ($q) {
            $q->whereNull('current_period_ends_at')
              ->orWhereDate('current_period_ends_at', '>=', now()->toDateString())
              // past_due: period may be past but still within grace — include by status alone
              ->orWhere('status', 'past_due');
        });
}

public function isActive(): bool
{
    if (! in_array($this->status, ['active', 'trialing', 'past_due'], true)) {
        return false;
    }
    if ($this->status === 'past_due') {
        $ends = $this->current_period_ends_at?->toDateString();
        if (! $ends) {
            return true;
        }
        return SubscriptionPeriod::graceDeadline($ends) >= now()->toDateString();
    }
    // existing active/trialing date logic
}
```

- [ ] **Step 1: Unit-test grace transitions** (pure functions taking status, period end, today)

- [ ] **Step 2: Implement model + ExpireSubscriptions**

- [ ] **Step 3: Run tests**

Run: `/opt/homebrew/bin/php artisan test --filter=SubscriptionGraceTest`

- [ ] **Step 4: Commit** — skip unless asked.

---

### Task 5: SubscriptionRenewalService (issue + mark paid)

**Files:**
- Create: `app/Services/SubscriptionRenewalService.php`
- Test: `tests/Unit/Services/SubscriptionRenewalServiceTest.php` — mock Billplz **or** extract pure “build renewal payload” and test that; HTTP feature optional

**Interfaces:**
- Consumes: `BillplzService::forPlatform()`, `SubscriptionPeriod::nextWindow`, `Plan::priceForInterval`
- Produces:
  - `issueIfDue(Subscription $sub): ?SubscriptionRenewal` — returns existing pending or new pending; null if skipped
  - `markPaid(SubscriptionRenewal $renewal): void` — idempotent

Rules for `issueIfDue`:

1. If `Deployment::isSelfHosted()` → null  
2. If interval not monthly/yearly → null  
3. If plan price for interval is 0 → null  
4. If pending renewal exists → return it (no new bill)  
5. If period end is more than `renewal_lead_days` in the future → null  
6. `forPlatform()` null → Log::warning, return null  
7. Plan = pendingPlan ?? plan; interval = pending_interval ?? interval  
8. Window = nextWindow(interval, current_period_ends_at)  
9. Create Billplz bill; persist renewal; send `SubscriptionRenewalDue` mail to billing email  

`markPaid`:

1. If already paid → return  
2. Update renewal paid  
3. Update subscription: status active, plan_id from renewal, clear pending_*, period from renewal row, gateway billplz  

Billing email resolution: tenant’s admin user email if `tenant_id`; else firm owner email if `firm_id`; else skip mail with log.

- [ ] **Step 1: Implement service + mail class/view**

- [ ] **Step 2: Unit-test markPaid idempotency and nextWindow wiring with a fake in-memory path if DB unavailable — prefer testing `SubscriptionPeriod` + a small `RenewalEligibility` helper without HTTP**

Eligibility helper (optional extract):

```php
public static function isDue(?string $periodEnd, int $leadDays, string $today): bool
{
    if (! $periodEnd) return false;
    $deadline = Carbon::parse($periodEnd)->startOfDay();
    $todayC = Carbon::parse($today)->startOfDay();
    return $todayC->gte($deadline->copy()->subDays($leadDays));
}
```

- [ ] **Step 3: Commit** — skip unless asked.

---

### Task 6: `subscription:issue-renewals` command + schedule

**Files:**
- Create: `app/Console/Commands/IssueSubscriptionRenewals.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: `SubscriptionRenewalService::issueIfDue`
- Schedule: dailyAt `01:45`, `onOneServer()`, before apply-pending

```php
protected $signature = 'subscription:issue-renewals';

public function handle(SubscriptionRenewalService $service): int
{
    if (Deployment::isSelfHosted()) {
        $this->info('Skipped (self-hosted).');
        return self::SUCCESS;
    }
    $q = Subscription::query()
        ->whereIn('status', ['active', 'past_due'])
        ->whereIn('interval', ['monthly', 'yearly'])
        ->whereNotNull('current_period_ends_at');

    $issued = 0;
    foreach ($q->cursor() as $sub) {
        if ($service->issueIfDue($sub)) {
            $issued++;
        }
    }
    $this->info("Issued/ensured {$issued} renewal(s).");
    return self::SUCCESS;
}
```

Note: `issueIfDue` returning existing pending still increments — either count only newly created or log separately. Prefer return type `?SubscriptionRenewal` and count only when `wasRecentlyCreated`.

- [ ] **Step 1: Command + schedule**

- [ ] **Step 2: Manual dry-run locally** (optional): `php artisan subscription:issue-renewals`

- [ ] **Step 3: Commit** — skip unless asked.

---

### Task 7: Billplz renewal webhook

**Files:**
- Modify: `app/Http/Controllers/SubscriptionController.php` — add `webhookBillplz`
- Modify: `routes/web.php` — next to other subscription webhooks
- Modify: `bootstrap/app.php` — CSRF except `/subscription/webhook/billplz`
- Test: unit on signature already exists; add unit/feature for markPaid via controller if feasible

**Interfaces:**
- Route name: `subscription.webhook.billplz`
- Callback URL when creating bill: `route('subscription.webhook.billplz')`
- Redirect URL: `route('subscription.callback')` or `subscription.success`

```php
public function webhookBillplz(Request $request)
{
    $billplz = BillplzService::forPlatform();
    if (! $billplz || ! $billplz->callbackIsPaid($request->all())) {
        return response('ignored', 200);
    }
    $billId = (string) $request->input('id');
    $renewal = SubscriptionRenewal::where('gateway_bill_id', $billId)->first();
    if (! $renewal) {
        return response('not found', 200);
    }
    app(SubscriptionRenewalService::class)->markPaid($renewal);
    return response('OK');
}
```

- [ ] **Step 1: Wire route + CSRF + controller**

- [ ] **Step 2: Ensure issue service uses this callback URL**

- [ ] **Step 3: Commit** — skip unless asked.

---

### Task 8: Plan page overdue banner

**Files:**
- Modify: `app/Http/Controllers/SubscriptionController.php` `planSettings` (or equivalent) to pass:
  - `renewal: null | { status, payment_url, amount, due_at, period_end, grace_ends_at }`
  - `subscription.status` already present — ensure `past_due` surfaces
- Modify: `resources/js/Pages/Settings/Plan.jsx`
- Run: `npm run build`

**UI copy:**
- If `past_due` or pending renewal with due soon: banner “Your renewal payment is due. Pay by {grace_ends_at} to keep {plan}.” Button: “Pay now” → `payment_url` (external).

- [ ] **Step 1: Pass props from controller**

- [ ] **Step 2: Banner in Plan.jsx**

- [ ] **Step 3: `npm run build`**

- [ ] **Step 4: Commit** — skip unless asked.

---

## Self-review

**Spec coverage**

| Spec item | Task |
| --- | --- |
| Platform Billplz env + forPlatform | 1 |
| subscription_renewals table | 2 |
| Period extend from prior end | 3 |
| past_due + 7-day grace expire | 4 |
| Issue bill + email | 5–6 |
| Webhook mark paid + pending plan | 5, 7 |
| Plan banner | 8 |
| Self-hosted no-op | 5–6 |
| No ToyyibPay replace / no paid upgrade | Global constraints |

**Placeholder scan:** none intentional.

**Type consistency:** `createBillDetailed` → `{id,url}`; renewal `gateway_bill_id`; `SubscriptionPeriod::nextWindow` / `graceDeadline`.

## Out of scope (do not implement)

- Paid→paid mid-cycle upgrade  
- Card auto-charge  
- ToyyibPay rebill fallback  
- Deploy CI test job  
