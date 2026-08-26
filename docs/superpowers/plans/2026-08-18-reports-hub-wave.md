# Reports hub Wave polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the Reports hub into a Wave-style desk (snapshot + grouped cards) and make every report drill, share period chips, tell the truth about cash, compare P&L, pack SST-02 + MyInvois gaps, and show payroll statutory still owed.

**Architecture:** One PHP helper (`App\Support\ReportPeriod`) owns date presets and compare windows. Hub snapshot is computed in `ReportsHubController` from existing ledger / invoice / tax queries. Cash movement is rebuilt from `journal_items` on bank/cash accounts. P&L/BS reuse `general-ledger.report` click-through (`account_code` + period). SST-02 and payroll remittance extend existing report controllers rather than adding plan SKUs.

**Tech Stack:** Laravel 11, Inertia React, PHPUnit (`/opt/homebrew/bin/php artisan test`), Vite (`npm run build`), DomPDF for PDF exports.

## Global Constraints

- Homebrew PHP: `/opt/homebrew/bin/php artisan test --filter=ClassName` (Herd `php` fails on dump-loader).
- After any React change: `npm run build`. There is usually no Vite HMR.
- Frontend is compiled; tell the user to hard-refresh (`Cmd+Shift+R`).
- Do not add new plan permissions. Reuse: `reports.*`, `general-ledger.view`, `payroll.run`, `journal.create`, `reports.export.limited|full`.
- Do not add 50 reports, custom report builder, inventory, or US payroll wage reports.
- Do not git commit unless the user explicitly asks during execution (user rule overrides “frequent commits” in this skill). Skip every Commit step unless told to commit.
- Keep named routes that other screens already link (`cashflow-summary.index`, `reports.sales.index`). Change behaviour / redirect, not URLs.
- Click-through target is always `route('general-ledger.report', { account_code, date_from?, date_to?, from })`.
- Copy: owner-facing titles stay plain (“Cash movement”, “In balance”). Table headers Debit/Credit stay for accountants.
- PHP tests that only need helpers: `Tests\Unit\Support\…` extending `PHPUnit\Framework\TestCase` (no DB). Controller tests that need tenancy: skip HTTP feature tests if no tenant harness exists; cover math in unit tests instead.

## Locked product choices

| Topic | Choice |
| --- | --- |
| Cashflow | Rebuild from bank/cash journal items. Keep URL `/cashflow-summary`. Title **Cash movement**. Money in = sum of debits on `sub_type` bank/cash; money out = sum of credits. Transfers inflate both sides equally; net still cancels. |
| P&L compare | Default `compare=previous` (same length immediately before `date_from`). Chip switches to `last_year` (same calendar dates minus 1 year) or `none`. |
| Period chips (range reports) | `this_month`, `last_month`, `this_quarter`, `ytd`, plus `custom` when dates do not match a preset. |
| Period chips (as-of: TB, BS) | Same four labels, but they set a single date: this month → today; last month → last day of previous month; this quarter → today; YTD → today. |
| SST extra chip | `this_sst_period` = current Malaysia 2-month window (Jan–Feb, Mar–Apr, …). Only on Sales Tax. |
| Merge Sales | `/reports/sales` redirects to Income by Customer. Product table moves onto that page. Keep route name. |
| Aged AP | Hub card links existing `accounts-payable.index` (`reports.aged-reports`). |
| Payroll remittance | New page, permission `journal.create` + plan `payroll.run`. Codes from `PayrollService::PAYROLL_ACCOUNTS` payables (2200–2240). Net credit balance as of today. |
| Hub snapshot | This calendar month net profit; this SST period net tax; overdue AR count+amount; cash (bank+cash net debit as of today). Hide a tile if that plan permission is missing. |

## File map

- Create: `app/Support/ReportPeriod.php`
- Create: `tests/Unit/Support/ReportPeriodTest.php`
- Create: `resources/js/Components/ReportPeriodChips.jsx`
- Create: `app/Http/Controllers/PayrollRemittanceController.php`
- Create: `resources/js/Pages/Reports/PayrollRemittance.jsx`
- Create: `tests/Unit/Support/CashMovementTest.php` (extract calculator) **or** put cash math on `ReportPeriod` / `App\Support\CashMovement`
- Create: `app/Support/CashMovement.php`
- Create: `resources/views/pdf/sales-tax.blade.php`
- Modify: `app/Http/Controllers/ReportsHubController.php`
- Modify: `resources/js/Pages/Reports/Hub.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx` (activeRoutes)
- Modify: `app/Http/Controllers/ProfitAndLossController.php`
- Modify: `resources/js/Pages/Reports/ProfitAndLoss.jsx`
- Modify: `resources/views/pdf/profit-and-loss.blade.php` (optional compare columns)
- Modify: `app/Http/Controllers/BalanceSheetController.php`
- Modify: `resources/js/Pages/Reports/BalanceSheet.jsx`
- Modify: `app/Http/Controllers/CashflowSummaryController.php`
- Modify: `resources/js/Pages/CashflowSummary/Index.jsx`
- Modify: `app/Http/Controllers/IncomeByCustomerController.php`
- Modify: `resources/js/Pages/Reports/IncomeByCustomer.jsx`
- Modify: `routes/web.php` (sales redirect, payroll remittance, sales-tax export)
- Modify: `app/Http/Controllers/SalesTaxReportController.php`
- Modify: `resources/js/Pages/Reports/SalesTax.jsx`
- Modify: range report pages to mount `ReportPeriodChips` (P&L, sales tax, income by customer, purchases by vendor, customer credits, cash movement, aged is as-of-today — skip chips)
- Modify: `resources/js/Pages/Reports/TrialBalance.jsx` (as-of chips)
- Delete usage of: `resources/js/Pages/Reports/Sales.jsx` (leave file or delete after redirect; prefer delete if unused)

---

### Task 1: ReportPeriod helper

**Files:**
- Create: `app/Support/ReportPeriod.php`
- Test: `tests/Unit/Support/ReportPeriodTest.php`

**Interfaces:**
- Consumes: Carbon
- Produces:
  - `ReportPeriod::range(?string $preset, ?string $from, ?string $to, ?Carbon $now = null): array{preset:string,date_from:string,date_to:string}`
  - `ReportPeriod::asOf(?string $preset, ?string $date, ?Carbon $now = null): array{preset:string,as_of:string}`
  - `ReportPeriod::sstPeriod(?Carbon $now = null): array{date_from:string,date_to:string}`
  - `ReportPeriod::previousOfSameLength(string $from, string $to): array{date_from:string,date_to:string}`
  - `ReportPeriod::lastYearSameDates(string $from, string $to): array{date_from:string,date_to:string}`
  - `ReportPeriod::detectPreset(string $from, string $to, ?Carbon $now = null): string` returns one of `this_month|last_month|this_quarter|ytd|this_sst_period|custom`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ReportPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    private function freeze(): Carbon
    {
        return Carbon::parse('2026-08-18');
    }

    public function test_this_month_runs_from_first_to_today(): void
    {
        $r = ReportPeriod::range('this_month', null, null, $this->freeze());
        $this->assertSame('2026-08-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
        $this->assertSame('this_month', $r['preset']);
    }

    public function test_last_month_is_full_july_when_today_is_august(): void
    {
        $r = ReportPeriod::range('last_month', null, null, $this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-07-31', $r['date_to']);
    }

    public function test_this_quarter_is_jul_to_today(): void
    {
        $r = ReportPeriod::range('this_quarter', null, null, $this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
    }

    public function test_ytd_is_jan_1_to_today(): void
    {
        $r = ReportPeriod::range('ytd', null, null, $this->freeze());
        $this->assertSame('2026-01-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
    }

    public function test_custom_dates_win_when_preset_missing(): void
    {
        $r = ReportPeriod::range(null, '2026-03-01', '2026-03-31', $this->freeze());
        $this->assertSame('custom', $r['preset']);
        $this->assertSame('2026-03-01', $r['date_from']);
        $this->assertSame('2026-03-31', $r['date_to']);
    }

    public function test_sst_period_in_august_is_jul_aug(): void
    {
        $r = ReportPeriod::sstPeriod($this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-08-31', $r['date_to']);
    }

    public function test_previous_of_same_length(): void
    {
        $r = ReportPeriod::previousOfSameLength('2026-08-01', '2026-08-18');
        $this->assertSame('2026-07-14', $r['date_from']);
        $this->assertSame('2026-07-31', $r['date_to']);
    }

    public function test_last_year_same_dates(): void
    {
        $r = ReportPeriod::lastYearSameDates('2026-08-01', '2026-08-18');
        $this->assertSame('2025-08-01', $r['date_from']);
        $this->assertSame('2025-08-18', $r['date_to']);
    }

    public function test_as_of_last_month_is_july_31(): void
    {
        $r = ReportPeriod::asOf('last_month', null, $this->freeze());
        $this->assertSame('2026-07-31', $r['as_of']);
    }
}
```

`previousOfSameLength` inclusive day count: from 1 Aug to 18 Aug = 18 days. Previous window ends the day before 1 Aug (31 Jul) and is also 18 days → 14 Jul–31 Jul.

SST: month 1–2, 3–4, 5–6, 7–8, 9–10, 11–12. August is in Jul–Aug. `date_to` for SST period is the **last day of the pair** (31 Aug), even if today is 18 Aug — SST-02 is a taxable period, not “to today”. Range chips for other reports still use today as `date_to` for open periods.

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php artisan test --filter=ReportPeriodTest`

Expected: FAIL (class not found)

- [ ] **Step 3: Write `app/Support/ReportPeriod.php`**

```php
<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ReportPeriod
{
    public const PRESETS = ['this_month', 'last_month', 'this_quarter', 'ytd', 'this_sst_period'];

    /**
     * @return array{preset:string,date_from:string,date_to:string}
     */
    public static function range(?string $preset, ?string $from, ?string $to, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();

        if (in_array($preset, self::PRESETS, true)) {
            if ($preset === 'this_sst_period') {
                $sst = self::sstPeriod($now);
                return ['preset' => $preset, 'date_from' => $sst['date_from'], 'date_to' => $sst['date_to']];
            }
            [$start, $end] = self::presetRange($preset, $now);
            return ['preset' => $preset, 'date_from' => $start->toDateString(), 'date_to' => $end->toDateString()];
        }

        $dateFrom = $from ?: $now->copy()->startOfMonth()->toDateString();
        $dateTo = $to ?: $now->toDateString();
        $detected = self::detectPreset($dateFrom, $dateTo, $now);

        return ['preset' => $detected, 'date_from' => $dateFrom, 'date_to' => $dateTo];
    }

    /**
     * @return array{preset:string,as_of:string}
     */
    public static function asOf(?string $preset, ?string $date, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        if ($preset === 'last_month') {
            return ['preset' => $preset, 'as_of' => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        }
        if (in_array($preset, ['this_month', 'this_quarter', 'ytd'], true)) {
            return ['preset' => $preset, 'as_of' => $now->toDateString()];
        }
        $asOf = $date ?: $now->toDateString();

        return ['preset' => $preset ?: 'custom', 'as_of' => $asOf];
    }

    /**
     * Malaysia SST 2-calendar-month window containing $now. date_to is period end (not today).
     *
     * @return array{date_from:string,date_to:string}
     */
    public static function sstPeriod(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        $month = (int) $now->month; // 1-12
        $startMonth = $month % 2 === 0 ? $month - 1 : $month;
        $start = $now->copy()->month($startMonth)->startOfMonth();
        $end = $start->copy()->addMonth()->endOfMonth();

        return ['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()];
    }

    /**
     * Inclusive previous window of the same number of days, ending the day before $from.
     *
     * @return array{date_from:string,date_to:string}
     */
    public static function previousOfSameLength(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        return ['date_from' => $prevStart->toDateString(), 'date_to' => $prevEnd->toDateString()];
    }

    /**
     * @return array{date_from:string,date_to:string}
     */
    public static function lastYearSameDates(string $from, string $to): array
    {
        return [
            'date_from' => Carbon::parse($from)->subYear()->toDateString(),
            'date_to' => Carbon::parse($to)->subYear()->toDateString(),
        ];
    }

    public static function detectPreset(string $from, string $to, ?CarbonInterface $now = null): string
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        foreach (['this_month', 'last_month', 'this_quarter', 'ytd'] as $preset) {
            [$s, $e] = self::presetRange($preset, $now);
            if ($from === $s->toDateString() && $to === $e->toDateString()) {
                return $preset;
            }
        }
        $sst = self::sstPeriod($now);
        if ($from === $sst['date_from'] && $to === $sst['date_to']) {
            return 'this_sst_period';
        }

        return 'custom';
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private static function presetRange(string $preset, CarbonInterface $now): array
    {
        $today = Carbon::parse($now)->startOfDay();

        return match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today],
            'ytd' => [$today->copy()->startOfYear(), $today],
            default => [$today->copy()->startOfMonth(), $today],
        };
    }
}
```

Fix `previousOfSameLength` expected dates if Carbon `diffInDays` is exclusive — run the test and adjust the assertion **or** the implementation until 1–18 Aug → 14–31 Jul.

- [ ] **Step 4: Run tests**

Run: `/opt/homebrew/bin/php artisan test --filter=ReportPeriodTest`

Expected: PASS

- [ ] **Step 5: Commit** — skip unless user asked.

---

### Task 2: Shared period chips component

**Files:**
- Create: `resources/js/Components/ReportPeriodChips.jsx`

**Interfaces:**
- Consumes: `ReportPeriod` presets (string names only; dates come from the server `filters`)
- Produces: React component used by later report pages

```jsx
import React from 'react';
import { router } from '@inertiajs/react';

const RANGE_CHIPS = [
    { id: 'this_month', label: 'This month' },
    { id: 'last_month', label: 'Last month' },
    { id: 'this_quarter', label: 'This quarter' },
    { id: 'ytd', label: 'Year to date' },
];

export default function ReportPeriodChips({
    action,
    preset = 'custom',
    fromKey = 'date_from',
    toKey = 'date_to',
    dateFrom = '',
    dateTo = '',
    extraChips = [],
    extraParams = {},
    mode = 'range', // 'range' | 'as_of'
    asOfKey = 'as_of_date',
    asOf = '',
}) {
    const chips = [...RANGE_CHIPS, ...extraChips];

    const visit = (next) => {
        router.get(action, { ...extraParams, ...next }, { preserveScroll: true, preserveState: false });
    };

    const applyPreset = (id) => {
        if (mode === 'as_of') {
            visit({ preset: id, [asOfKey]: undefined });
            return;
        }
        visit({ preset: id, [fromKey]: undefined, [toKey]: undefined });
    };

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-wrap gap-1.5">
                {chips.map((chip) => (
                    <button
                        key={chip.id}
                        type="button"
                        onClick={() => applyPreset(chip.id)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                            preset === chip.id
                                ? 'bg-terracotta text-white border-terracotta'
                                : 'bg-surface text-ink border-border-warm hover:bg-cream'
                        }`}
                    >
                        {chip.label}
                    </button>
                ))}
            </div>
            {mode === 'as_of' ? (
                <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                    As of
                    <input
                        type="date"
                        value={asOf}
                        onChange={(e) => visit({ preset: 'custom', [asOfKey]: e.target.value })}
                        className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink"
                    />
                </label>
            ) : (
                <>
                    <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                        From
                        <input type="date" value={dateFrom} onChange={(e) => visit({ preset: 'custom', [fromKey]: e.target.value, [toKey]: dateTo })} className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink" />
                    </label>
                    <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                        To
                        <input type="date" value={dateTo} onChange={(e) => visit({ preset: 'custom', [fromKey]: dateFrom, [toKey]: e.target.value })} className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink" />
                    </label>
                </>
            )}
        </div>
    );
}
```

Controllers that accept chips must read `preset` and call `ReportPeriod::range` / `asOf` **before** running queries. Pass `filters.preset` back to the page.

Wire chips onto P&L, BS, TB, Sales Tax, Income by Customer, Purchases by Vendor, Customer Credits, Cash movement as those pages are touched in later tasks — do not boil the ocean in this task. This task only adds the component file.

- [ ] **Step 1: Add the component file**
- [ ] **Step 2: No PHP test.** Visual check happens when first report is wired (Task 4/5).
- [ ] **Step 3: Commit** — skip unless asked.

---

### Task 3: Hub snapshot, groups, TB + GL + Aged AP, merge Sales

**Files:**
- Modify: `app/Http/Controllers/ReportsHubController.php`
- Modify: `resources/js/Pages/Reports/Hub.jsx`
- Modify: `app/Http/Controllers/IncomeByCustomerController.php`
- Modify: `resources/js/Pages/Reports/IncomeByCustomer.jsx`
- Modify: `routes/web.php` (`reports.sales.index` → redirect)
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx` `activeRoutes`
- Delete: `resources/js/Pages/Reports/Sales.jsx` after redirect (optional; dead code)

**Interfaces:**
- Consumes: `ReportPeriod::range('this_month')`, `ReportPeriod::sstPeriod()`, P&L math (copy the income−expense sum), AR overdue from same formula as `AgedReceivablesController`, cash from bank/cash nets
- Produces: Hub props `snapshot` and grouped `sections`

Snapshot fields:

```php
[
  'month_label' => 'August 2026',
  'net_profit' => ?float,          // null if no reports.profit-loss
  'tax_owing' => ?float,           // output−input this SST period; null if no reports.sales-tax
  'overdue_ar_amount' => ?float,   // null if no reports.aged-reports
  'overdue_ar_count' => ?int,
  'cash' => ?float,                // bank+cash debit−credit as of today; null if no journal.view
]
```

Hub card groups (filter each card by `planPermissions` like today):

1. **Financial** — P&L, Balance Sheet, Trial Balance (`trial-balance.index`, `general-ledger.view`), General ledger (`general-ledger.index`, `general-ledger.view`)
2. **Money** — Cash movement, Income by customer, Purchases by vendor, Customer credits
3. **Collect** — Aged receivables (`aged-receivables.index`), Aged payables (`accounts-payable.index`)
4. **Tax & payroll** — Sales tax, Payroll remittance (add the card in Task 9; leave a commented placeholder **out** — add the card only when the route exists)

Merge Sales: move the product query from `SalesReportController` into `IncomeByCustomerController` as `products` prop. Change `routes/web.php`:

```php
Route::get('/reports/sales', fn () => redirect()->route('reports.income-by-customer.index'))
    ->name('reports.sales.index');
```

Keep the same middleware group (`reports.sales`). Income by Customer already uses `reports.sales` — good.

Income by Customer page: keep customer table, add “Sales by product” table below (copy markup from current `Sales.jsx`).

Hub layout:
- Header unchanged in spirit: “Reports” / snapshot subtitle
- 4-stat strip (hide nulls; if only 2 permissions, grid still works)
- Then grouped headings + compact rows (not giant colour blocks): title, one-line description, chevron. Match GL/COA density.

- [ ] **Step 1: Unit-test snapshot cash math if extracted.** Prefer extracting `App\Support\CashMovement::balanceAsOf(?string $asOf): float` in Task 6 and calling it from the hub. For this task, inline a small query in the hub controller; Task 6 will replace it.
- [ ] **Step 2: Implement hub controller + Hub.jsx**
- [ ] **Step 3: Redirect sales + product table on Income by Customer**
- [ ] **Step 4: `npm run build`**
- [ ] **Step 5: Manual** — open `/reports`, confirm groups, TB/GL/AP links, `/reports/sales` lands on Income by Customer with a product table.
- [ ] **Step 6: Commit** — skip unless asked.

---

### Task 4: Click-through on P&L and Balance Sheet

**Files:**
- Modify: `resources/js/Pages/Reports/ProfitAndLoss.jsx`
- Modify: `resources/js/Pages/Reports/BalanceSheet.jsx`

**Interfaces:**
- Consumes: existing `acc.code`; GL report already supports `account_code`, `date_from`, `date_to`
- Produces: links only (no backend change required)

P&L link:

```js
const ledgerUrl = (code) => route('general-ledger.report', {
    account_code: code,
    date_from: filters.date_from,
    date_to: filters.date_to,
    from: 'pl',
});
```

Wrap account name (and code) in `<Link href={ledgerUrl(acc.code)}>`. Skip zero-amount lines that are already hidden.

Balance Sheet:

```js
const ledgerUrl = (code) => route('general-ledger.report', {
    account_code: code,
    date_to: filters.as_at_date,
    from: 'bs',
});
```

If `AccountTable` is a local helper in `BalanceSheet.jsx`, pass `ledgerUrl` into it.

GL report back-link: `GeneralLedger/Report.jsx` already special-cases `from=tb`. Add `from=pl` → `profit-and-loss.index` (preserve dates if present on the query string) and `from=bs` → `balance-sheet.index`.

- [ ] **Step 1: Patch Report.jsx back links** (`from === 'pl' | 'bs'`)
- [ ] **Step 2: Link P&L and BS rows**
- [ ] **Step 3: `npm run build`**
- [ ] **Step 4: Manual** — P&L line → ledger for that account and period; back returns to P&L.
- [ ] **Step 5: Commit** — skip unless asked.

---

### Task 5: Wire period chips on every range / as-of report

**Files:**
- Modify each report `index` to: `$resolved = ReportPeriod::range($request->input('preset'), $request->input('date_from') ?? $request->input('start_date'), …)` then query with resolved dates. Return `filters.preset`.
- Normalize query param names going forward:
  - Range reports already using `date_from`/`date_to`: P&L, cash movement — keep.
  - Range reports using `start_date`/`end_date`: Sales tax, Income by Customer, Purchases by Vendor, Customer Credits — **keep the existing names** in the form (do not break bookmarks). Chips component `fromKey="start_date" toKey="end_date"`.
  - As-of: TB `as_of_date`, BS `as_at_date` — pass through `asOfKey`.
- Mount `<ReportPeriodChips>` in the filter bar of: P&L, BS, TB, Sales Tax (plus extra chip `{ id: 'this_sst_period', label: 'This SST period' }`), Income by Customer, Purchases by Vendor, Customer Credits, Cash movement.

Sales Tax controller:

```php
$resolved = ReportPeriod::range(
    $request->input('preset'),
    $request->input('start_date'),
    $request->input('end_date')
);
$start = $resolved['date_from'];
$end = $resolved['date_to'];
```

Default when no query: `this_month` for P&L (already startOfMonth–today). Sales tax currently defaults to `startOfQuarter` — **change default to `this_sst_period`** so the tax pack matches Customs periods. Income by Customer currently YTD — change default to `this_month` for consistency with chips (YTD is one click).

- [ ] **Step 1: Update each controller to use `ReportPeriod::range` / `asOf`**
- [ ] **Step 2: Drop duplicate From/To forms; use chips + date inputs**
- [ ] **Step 3: `npm run build`**
- [ ] **Step 4: Manual** — This month / Last month / This quarter / YTD change the table; custom dates mark no chip as selected (`preset=custom`).
- [ ] **Step 5: Commit** — skip unless asked.

---

### Task 6: Rebuild Cash movement from bank/cash journals

**Files:**
- Create: `app/Support/CashMovement.php`
- Test: `tests/Unit/Support/CashMovementTest.php` (pure functions for in/out/net from mock lines; DB queries stay in the class methods — test the reducer)
- Modify: `app/Http/Controllers/CashflowSummaryController.php`
- Modify: `resources/js/Pages/CashflowSummary/Index.jsx`

**Interfaces:**
- Consumes: `journal_items` joined to `journal_entries` and `accounts` where `accounts.sub_type in ('bank','cash')`
- Produces:

```php
final class CashMovement
{
    /**
     * @param  list<array{month:string,debit:float,credit:float}>  $lines
     * @return list<array{month:string,month_label:string,money_in:float,money_out:float,net:float}>
     */
    public static function chartByMonth(array $lines): array;

    public static function totals(array $chart): array; // money_in, money_out, net
}
```

Query in the controller (posted journals only if `journal_entries.status` is used; if drafts exist, `where('journal_entries.status', 'posted')` — match GL). Do **not** use Invoice/Bill totals.

Copy on the page:
- Title: **Cash movement**
- Subtitle: **Money in and out of bank and cash. Transfers raise both sides; net is unchanged.**
- KPI labels: Money in / Money out / Net
- Chart series: money in, money out (reuse existing chart component; rename keys `sales`→`money_in`, `expenses`→`money_out` in the React file)

Keep route `cashflow-summary.index`. Hub card label **Cash movement**.

- [ ] **Step 1: Write reducer tests** (two months of fake debit/credit → chart + net)
- [ ] **Step 2: Run — expect FAIL**
- [ ] **Step 3: Implement `CashMovement` + swap controller**
- [ ] **Step 4: Update React labels; `npm run build`**
- [ ] **Step 5: Manual** — a bank withdrawal increases Money out, not “expenses”
- [ ] **Step 6: Commit** — skip unless asked.

Hub snapshot `cash` tile should call the same net-as-of-today query (extract `CashMovement::netAsOf(string $asOf): float` if easy).

---

### Task 7: P&L compare column

**Files:**
- Modify: `app/Http/Controllers/ProfitAndLossController.php` (`buildPlData` + `index` + CSV/PDF)
- Modify: `resources/js/Pages/Reports/ProfitAndLoss.jsx`
- Modify: `resources/views/pdf/profit-and-loss.blade.php`

**Interfaces:**
- Consumes: `compare=previous|last_year|none` (default `previous`)
- Produces: each line `{ code, name, amount, compare_amount, variance }` plus totals `{ total_revenue, compare_revenue, … }` and `compare_label`, `compare_from`, `compare_to`

Merge algorithm:

1. `$current = buildPlData($from, $to)`
2. If compare is `none`, skip.
3. `$window = $compare === 'last_year' ? ReportPeriod::lastYearSameDates($from, $to) : ReportPeriod::previousOfSameLength($from, $to)`
4. `$prior = buildPlData($window['date_from'], $window['date_to'])`
5. Union codes by section. Missing side → `compare_amount = 0`. `variance = amount - compare_amount`.

UI:
- Chips next to period: **vs previous** | **vs last year** | **Off**
- Table columns: Account | This period | Compare | Variance
- Variance in forest if ≥ 0 for income / ≤ 0 for expense (better), terracotta otherwise — keep it simple: forest if variance > 0, terracotta if < 0, muted if 0
- Totals row repeats the three columns
- CSV: add `compare_amount`, `variance` columns
- PDF: extra columns if compare is on

Pass `compare` through `ReportPeriodChips` `extraParams={{ compare }}` so period changes keep the compare mode.

- [ ] **Step 1: Unit test a small merge helper** `ReportPeriod` does not merge P&L — add `App\Support\ReportCompare::mergeLines(array $current, array $prior): array` in `app/Support/ReportCompare.php`

```php
public static function mergeLines(array $current, array $prior): array
{
    $priorByCode = [];
    foreach ($prior as $row) {
        $priorByCode[$row['code']] = $row;
    }
    $seen = [];
    $out = [];
    foreach ($current as $row) {
        $cmp = (float) ($priorByCode[$row['code']]['amount'] ?? 0);
        $out[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'amount' => (float) $row['amount'],
            'compare_amount' => $cmp,
            'variance' => round($row['amount'] - $cmp, 2),
        ];
        $seen[$row['code']] = true;
    }
    foreach ($prior as $row) {
        if (isset($seen[$row['code']])) {
            continue;
        }
        $out[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'amount' => 0.0,
            'compare_amount' => (float) $row['amount'],
            'variance' => round(0 - $row['amount'], 2),
        ];
    }
    return $out;
}
```

Test: current 4000=100, prior 4000=80 and 4100=10 → two rows, variances +20 and -10.

- [ ] **Step 2: FAIL then implement**
- [ ] **Step 3: Hook controller + UI + CSV/PDF**
- [ ] **Step 4: `npm run build`**
- [ ] **Step 5: Commit** — skip unless asked.

---

### Task 8: SST-02 style pack + CSV/PDF

**Files:**
- Modify: `app/Http/Controllers/SalesTaxReportController.php`
- Modify: `resources/js/Pages/Reports/SalesTax.jsx`
- Create: `resources/views/pdf/sales-tax.blade.php`
- Modify: `routes/web.php` (export routes next to P&L exports)

**Interfaces:**
- Consumes: invoices/bills already loaded; `invoice_items.tax_rate`; `invoices.lhdn_status`, `lhdn_uuid`
- Produces extra props:

```php
'pack' => [
    'period_from' => $start,
    'period_to' => $end,
    'output_tax' => ...,
    'input_tax' => ...,
    'net_tax' => ...,
    'exempt_sales' => ...,   // sum invoice_items.amount where tax_rate == 0
    'taxable_sales' => ...,  // existing
],
'by_rate' => [...], // already exists; include 0% as Exempt
'myinvois_gaps' => [
    ['id', 'invoice_number', 'issue_date', 'customer', 'total', 'lhdn_status', 'reason'],
],
'gap_counts' => ['missing' => n, 'pending' => n, 'rejected' => n],
```

Gap rule (posted invoices in period, not draft/void):

- `lhdn_uuid` empty **or** `lhdn_status` in `pending`, `rejected`, `invalid` → gap
- `reason`: `Not submitted` if no uuid; else the status
- Limit 200 rows; show count if truncated
- Link number to `invoices.show`

This is **not** a filed SST-02 XML. Page copy: **SST period pack — figures for your return, not a filed form.**

CSV columns: section, rate, taxable, tax, document, status. PDF: company header like P&L, pack totals, by-rate table, gap list.

Export routes (same gates as P&L):

```php
Route::middleware('permission:reports.export.limited|reports.export.full')->group(function () {
    Route::get('/reports/sales-tax/export/csv', [SalesTaxReportController::class, 'exportCsv'])->name('reports.sales-tax.export.csv');
});
Route::middleware('permission:reports.export.full')->group(function () {
    Route::get('/reports/sales-tax/export/pdf', [SalesTaxReportController::class, 'exportPdf'])->name('reports.sales-tax.export.pdf');
});
```

Also require `reports.sales-tax` on those routes (wrap inside the existing sales-tax middleware group).

Unit test gap classifier:

```php
public static function myinvoisGapReason(?string $uuid, ?string $status): ?string
```

`null` uuid + pending → `Not submitted`; status `valid` + uuid → `null` (not a gap); `rejected` → `rejected`.

- [ ] **Step 1: Test `myinvoisGapReason`**
- [ ] **Step 2: Implement pack + gaps + exports**
- [ ] **Step 3: UI — pack strip, by-rate including exempt, gaps table, CSV/PDF buttons (lock PDF if no `reports.export.full`, same as P&L)**
- [ ] **Step 4: `npm run build`**
- [ ] **Step 5: Commit** — skip unless asked.

---

### Task 9: Payroll remittance due

**Files:**
- Create: `app/Http/Controllers/PayrollRemittanceController.php`
- Create: `resources/js/Pages/Reports/PayrollRemittance.jsx`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Reports/Hub.jsx` (Tax & payroll card)
- Modify: `AuthenticatedLayout.jsx` `activeRoutes`

**Interfaces:**
- Consumes: `PayrollService::PAYROLL_ACCOUNTS` keys `epf_payable`, `socso_payable`, `eis_payable`, `pcb_payable`, `hrd_payable`
- Produces: one row per code: `{ code, name, credit_balance, ledger_url }` where `credit_balance = max(0, round(credits − debits, 2))` as of `as_of` (default today). Hide zero rows but show a total.

Permission: same group as payroll run:

```php
Route::middleware(['permission:journal.create', 'plan.permission:payroll.run'])->group(function () {
    Route::get('/reports/payroll-remittance', [PayrollRemittanceController::class, 'index'])
        ->name('reports.payroll-remittance');
});
```

Page: as-of chips (Task 2 `mode="as_of"`). Click code → ledger `date_to=as_of`. Copy: **Statutory still in payables — EPF, SOCSO, EIS, PCB, HRD. This is the unpaid balance, not this month’s run.**

If accounts were never created, `ensureAccounts()` on first visit (same as payroll form) so codes exist.

Hub card: only if `planPermissions['payroll.run']` and user has `journal.create` (the hub already filters on plan permission; also check `auth.permissions`).

- [ ] **Step 1: Unit test net credit: debit 100 credit 2400 → 2300 payable**
- [ ] **Step 2: Controller + page + hub card + route**
- [ ] **Step 3: `npm run build`**
- [ ] **Step 4: Manual** — after a payroll post, remittance shows EPF/SOCSO/etc.; paying those journals down reduces the report
- [ ] **Step 5: Commit** — skip unless asked.

---

## Self-review

**Spec coverage**

| User ask | Task |
| --- | --- |
| Hub snapshot, groups, TB + GL + Aged AP, merge Sales | 3 |
| P&L / BS click-through | 4 |
| Shared period chips | 1, 2, 5 |
| Fix cashflow (rebuild bank/cash) | 6 |
| P&L compare | 7 |
| SST-02 pack + CSV/PDF | 8 |
| Payroll remittance due | 9 |

**Placeholder scan:** none left; SST is explicitly “figures for your return, not a filed form”.

**Type consistency:** `ReportPeriod::range` returns `date_from`/`date_to`; chips use `fromKey`/`toKey` for legacy `start_date`. Compare query is `compare=previous|last_year|none`. Cash KPIs are `money_in` / `money_out` / `net`.

## Out of scope

- Filing SST-02 to Customs
- Cash vs accrual toggle on P&L
- Budget vs actual
- New plan SKUs
- Changing AP/AR aging math
- Wave US payroll wage report
