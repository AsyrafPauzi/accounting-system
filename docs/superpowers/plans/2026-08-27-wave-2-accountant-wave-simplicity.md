# Wave 2 — Accountant + Wave Simplicity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Malaysian bookkeeper can close a month with honest AR/AP numbers; a new SME can send an invoice and collect payment on day one — matching Wave customer UX and Bukku bookkeeper expectations.

**Architecture:** Extend existing money-path services (`InvoiceService`, `BillService`, `DocumentNumber`, `ShareLink`, `InvoicePayNowService`) rather than parallel implementations. Add tenant-scoped settings tables where config is missing (`document_number_settings`, `tax_codes`, `accounting_periods`). Gate writes with `EnsurePeriodOpen` middleware. Public customer UX reuses signed URLs + Pay Now gateways already wired for webhooks.

**Tech Stack:** Laravel 12, Inertia/React, Stancl Tenancy, PHPUnit 11, DomPDF, ToyyibPay / Billplz / CommercePay

**Master plan:** [`2026-08-27-beat-competitors-waves-master.md`](./2026-08-27-beat-competitors-waves-master.md)  
**Depends on:** Wave 1 complete (posted journals, verified webhooks, CI gate, firm RBAC)

---

## Current state audit (2026-08-27 — signed off)

| # | Deliverable | Status | Notes |
|---|-------------|--------|-------|
| 7 | Period lock | **✅ Done** | Table, resolver, middleware, UI, permissions, reopen test |
| 8 | Tax-code master | **~70%** | Table + defaults + bill→1110; CRUD + line FK → Wave 3 Task 0 |
| 9 | AR/AP `remainingBalance` | **✅ Done** | Backend, reports, statements, tests |
| 10 | Document numbering settings | **✅ Done** | Settings UI, FY reset, ar/ap deposit types |
| 11 | Public HTML invoice | **✅ Done** | Signed route, blade view, email View & Pay |
| 12 | Onboarding + MY | **✅ Done** | Checklist, Startup record-payment, partial nav i18n |
| 13 | Money-path feature tests | **✅ Done** | ≥15 tests; 375 total green |

**Wave 2 overall:** ✅ signed off — commits `4460296` + `c8bbb2c`. Tax-code CRUD deferred to Wave 3 Task 0.

---

## Known logic bugs & missing features (must fix in Wave 2)

These are **not** optional polish — they are called out explicitly in the master plan or discovered during audit:

| ID | Issue | Where | Fix in task |
|----|-------|-------|-------------|
| L1 | Bill input SST debits **2100 Tax Payable** instead of **1110 Tax Receivable** | `BillService.php` L272–277 | Task 3 Step 3 |
| L2 | SST report ignores credit notes, debit notes, supplier CN/DN | `SalesTaxReportController.php` | Task 3 Step 6 |
| L3 | SST report groups by `%` not tax code | `SalesTaxReportController.php` L135–157 | Task 3 |
| L4 | CoA seeds **1110** but no posting code uses it for input tax | `DefaultChartOfAccounts.php` vs services | Task 3 |
| L5 | Startup plan **cannot record payment** — conflicts with Wave 2 #12 | `PlanSeeder.php` L474–478 | Task 6 Step 2 |
| L6 | Welcome tour mentions Solo features Startup lacks | `WelcomeModal.jsx` | Task 6 Step 5 |
| L7 | `SupplierStatementController` hardcodes `'MYR'` | `SupplierStatementController.php` L56 area | Task 6 Step 4 |
| L8 | Invoice email links PDF only — no Pay / HTML link | `resources/views/emails/invoice.blade.php` | Task 5 Step 6 |
| L9 | Public pay requires auth (`InvoiceController::payNow`) | `InvoiceController.php` | Task 5 |
| L10 | Customer statement opening balance subtracts **full CN total**, not applied-only | `CustomerStatementService.php` L100–105 | Task 2 Step 5 (optional hardening) |
| L11 | JS still falls back to `total_amount - amount_paid` | `Invoices/Index.jsx`, `Bills/*.jsx` | Task 2 Step 4 |
| L12 | Document numbers race under concurrent creates (scan-all-rows) | `DocumentNumber.php` | Task 4 Step 3 |

---

## Global constraints

- Tenant migrations under `database/migrations/tenant/`; run `php artisan tenants:migrate` after schema changes.
- Wave 1 golden rule still applies: do not expand inventory, POS, fixed assets, or Copilot write tools until Wave 2 money path + period lock are trustworthy.
- **Period lock (#7) blocks Wave 4 inventory/FA** — implement last in Wave 2 but do not defer past Wave 2 sign-off.
- **Tax codes (#8) block Wave 3 SST-02 export** — schema + posting fixes are Wave 2; export file is Wave 3.
- Public routes must use signed URLs (`URL::temporarySignedRoute`) consistent with existing `ShareLink` pattern.
- Tests: use `/opt/homebrew/bin/php artisan test` if default `php` has broken Herd extension; tenant tests use `CreatesTestTenants` trait.
- Commit Wave 2 aging + tests (currently uncommitted) before or as part of Task 2 sign-off.

---

## Dependency graph (within Wave 2)

```
Task 2 (#9 close-out) ──► Task 7 (#13 tests for aging)
Task 4 (#10 doc nums) ──► independent (do early)
Task 5 (#11 HTML pay) ──► Task 6 checklist "collect payment" step
Task 3 (#8 tax codes) ──► fixes L1–L4; enables Wave 3 SST-02
Task 6 (#12 onboarding) ──► depends on #11 for honest "collect" step
Task 1 (#7 period lock) ──► last; blocks Wave 4
Task 7 (#13) ──► runs in parallel; some tests blocked until Tasks 1, 3, 5 land
```

**Recommended execution order:** Task 2 → Task 7 (partial) → Task 4 → Task 5 → Task 6 → Task 3 → Task 1 → Task 8 (sign-off)

---

## Task 1: Period lock + reopen permission (#7)

**Goal:** Month-end trust — no post/void/pay into closed accounting periods; admin can reopen with permission.

**Files:**
- Create: `database/migrations/tenant/2026_08_28_000001_create_accounting_periods_table.php`
- Create: `app/Models/AccountingPeriod.php`
- Create: `app/Support/AccountingPeriodResolver.php` — map `Y-m-d` → period row using tenant FY start
- Create: `app/Http/Middleware/EnsurePeriodOpen.php`
- Create: `app/Http/Controllers/AccountingPeriodController.php`
- Create: `resources/js/Pages/Settings/AccountingPeriods.jsx`
- Modify: `bootstrap/app.php` — register middleware alias `period.open`
- Modify: `routes/web.php` — lock routes + settings routes
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` — add `periods.view`, `periods.lock`, `periods.reopen`
- Modify: post/void/pay route groups (invoices, bills, credit notes, deposits, journals manual post)
- Create: `tests/Feature/Accounting/PeriodLockTest.php`

**Schema (`accounting_periods`):**
```php
$table->id();
$table->date('start_date');
$table->date('end_date');
$table->string('label'); // e.g. "Jan 2026"
$table->enum('status', ['open', 'closed'])->default('open');
$table->timestamp('closed_at')->nullable();
$table->unsignedBigInteger('closed_by')->nullable();
$table->timestamps();
$table->unique(['start_date', 'end_date']);
```

**Middleware behaviour:**
```php
// EnsurePeriodOpen — read document date from route/request (issue_date, bill_date, payment_date, journal date)
// If period.status === 'closed' → 422/403 with message "Period {label} is closed"
// Reopen: requires permission periods.reopen
```

**Routes to protect (minimum):**
- `POST /invoices/{id}/post`, `POST /invoices/{id}/void`, `POST /invoices/{id}/payments`
- `POST /bills/{id}/post`, `POST /bills/{id}/void`, `POST /bills/{id}/payments`
- Credit note issue/void, AR/AP deposit apply, manual journal post
- **Exclude:** draft create/edit (dates can be set before post), webhook callbacks (payment date = today — use period for payment date or allow with admin flag)

- [ ] **Step 1: Write failing test — post into closed period rejected**

```php
// Close January 2026 → post invoice with issue_date 2026-01-15 → LogicException or 403
```

- [ ] **Step 2: Migration + model + auto-generate periods for current FY**

Seed 12 monthly periods on first settings visit or tenant migrate backfill.

- [ ] **Step 3: Implement EnsurePeriodOpen + register on write routes**

- [ ] **Step 4: Settings UI — list periods, Close / Reopen buttons**

- [ ] **Step 5: Run tests + manual smoke**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: accounting period lock with reopen permission"
```

**Done when:** Closed period blocks post/void/pay; admin with `periods.reopen` can reopen; test green.

---

## Task 2: Close out AR/AP remainingBalance (#9)

**Goal:** Every customer-facing balance matches `remainingBalance` (payments + credit notes + deposits).

**Already done (verify, do not redo):**
- `InvoiceService::remainingBalance()`, `BillService::remainingBalance()`
- `AgedReceivablesController`, `AccountsPayableController`, `DashboardController`, `ReportsHubController`
- `IncomeByCustomerController`, `PurchasesByVendorController`, statement index pages
- `PracticeMetricsService`, API v1, `Invoice::balance_due`, `Bill::balance_due`
- `tests/Feature/Sales/RemainingBalanceTest.php` (CN scenario)

**Files (remaining):**
- Modify: `resources/js/Pages/Invoices/Index.jsx` — require `balance_due` from server; remove fallback math
- Modify: `resources/js/Pages/Bills/Index.jsx`, `Bills/Edit.jsx` — same
- Modify: `app/Services/CustomerStatementService.php` — optional L10 fix (applied CN only)
- Create: `tests/Feature/Sales/ArDepositRemainingBalanceTest.php`
- Create: `tests/Feature/Sales/ApAgingRemainingBalanceTest.php`

- [ ] **Step 1: Write failing test — AR deposit reduces remainingBalance**

Apply `ArDepositService::applyToInvoice()` → assert `remainingBalance` decreased, not `total_amount - amount_paid`.

- [ ] **Step 2: Write failing test — supplier CN reduces bill remainingBalance**

- [ ] **Step 3: Fix any failing controller/service found by tests**

- [ ] **Step 4: Remove JS fallback `total_amount - amount_paid`**

Server must always send `balance_due` on invoice/bill list payloads.

- [ ] **Step 5: (Optional) CustomerStatementService — use applied CN amount not full CN total**

Query `credit_note_applications` for opening/closing logic when table exists.

- [ ] **Step 6: Run full suite + commit**

```bash
git commit -m "fix: complete remainingBalance adoption for AR/AP aging"
```

**Done when:** Deposit + supplier CN tests pass; no report/dashboard uses naive `total - paid` for open balance.

---

## Task 3: Tax-code master + SST posting fixes (#8)

**Goal:** Malaysian SST codes (SR-8, ST-10, ES, ZRL) drive line items; input tax → 1110, output → 2100; SST pack includes CN/DN.

**Files:**
- Create: `database/migrations/tenant/2026_08_28_000002_create_tax_codes_table.php`
- Create: `database/migrations/tenant/2026_08_28_000003_add_tax_code_id_to_line_items.php`
- Create: `app/Models/TaxCode.php`
- Create: `database/seeders/TenantTaxCodesSeeder.php` — SR-8, ST-10, ES, ZRL defaults
- Create: `app/Http/Controllers/TaxCodeController.php`
- Create: `resources/js/Pages/Settings/TaxCodes/Index.jsx`
- Modify: `app/Services/BillService.php` L272–277 — **L1:** Dr `1110` not `2100`
- Modify: `app/Services/InvoiceService.php` — keep Cr `2100` (already correct L631–637)
- Modify: `app/Services/CreditNoteService.php`, `DebitNoteService.php` — tax leg uses code's GL mapping
- Modify: `app/Services/SupplierCreditNoteService.php`, `SupplierDebitNoteService.php` — input side
- Modify: `app/Http/Controllers/SalesTaxReportController.php` — include CN/DN; group by tax code
- Modify: invoice/bill create forms — tax code dropdown instead of free-form `%` only
- Create: `tests/Feature/Tax/TaxCodePostingTest.php`
- Create: `tests/Feature/Tax/SalesTaxReportCnDnTest.php`

**Schema (`tax_codes`):**
```php
$table->id();
$table->string('code', 20)->unique(); // SR-8, ST-10, ES, ZRL
$table->string('name');
$table->decimal('rate', 5, 2)->default(0);
$table->enum('type', ['standard', 'zero', 'exempt', 'out_of_scope']);
$table->string('output_account_code', 10)->nullable(); // 2100 for SR-8
$table->string('input_account_code', 10)->nullable();  // 1110 for SR-8
$table->boolean('is_active')->default(true);
$table->timestamps();
```

**Default seed (MY):**
| Code | Rate | Output | Input |
|------|------|--------|-------|
| SR-8 | 8% | 2100 | 1110 |
| ST-10 | 10% | 2100 | 1110 |
| ES | 0% | null | null |
| ZRL | 0% | null | null |

- [ ] **Step 1: Write failing test — bill post debits 1110 for tax**

- [ ] **Step 2: Migration + model + seeder**

- [ ] **Step 3: Fix BillService tax line (L1)**

- [ ] **Step 4: Add `tax_code_id` to invoice_items, bill_items, CN/DN items; backfill from existing `tax_rate`**

- [ ] **Step 5: Tax code CRUD in Settings**

- [ ] **Step 6: Extend SalesTaxReport — subtract CN output tax, add DN; include supplier CN/DN input**

- [ ] **Step 7: Run tests + commit**

```bash
git commit -m "feat: tax-code master with correct SST GL posting"
```

**Done when:** Bill posts Dr 1110; SST report net matches codes; CN/DN appear in pack.

**Blocks:** Wave 3 #15 SST-02 export (reads same codes).

---

## Task 4: Document numbering settings (#10)

**Goal:** Tenant-configurable prefix, next number, and FY reset per document type (AutoCount migration story).

**Files:**
- Create: `database/migrations/tenant/2026_08_28_000004_create_document_number_settings_table.php`
- Create: `app/Models/DocumentNumberSetting.php`
- Modify: `app/Support/DocumentNumber.php` — read settings first; optional row lock for L12
- Modify: `app/Http/Controllers/CompanySettingsController.php` or new `DocumentNumberSettingsController`
- Create: `resources/js/Pages/Settings/DocumentNumbers.jsx`
- Modify: all `DocumentNumber::next()` call sites — pass doc type key
- Create: `tests/Unit/Sales/DocumentNumberSettingsTest.php`

**Schema (`document_number_settings`):**
```php
$table->id();
$table->string('doc_type', 40)->unique(); // invoice, bill, credit_note, ...
$table->string('prefix', 20)->default('INV');
$table->unsignedBigInteger('next_number')->default(1);
$table->unsignedTinyInteger('pad_width')->default(4);
$table->boolean('reset_on_fy')->default(false);
$table->timestamps();
```

**Doc types (minimum):** `invoice`, `bill`, `credit_note`, `debit_note`, `estimate`, `sales_order`, `delivery_order`, `purchase_order`, `goods_receipt`, `supplier_credit_note`, `supplier_debit_note`, `ar_deposit`, `ap_deposit`

**FY reset logic:**
```php
// When reset_on_fy && issue_date/bill_date falls in new FY (tenant.financial_year_start_month):
// next_number resets to 1 (or configured start)
```

- [ ] **Step 1: Write failing test — custom prefix from settings**

Tenant sets prefix `ABC`, next `42` → `DocumentNumber::next('invoice', ...)` returns `ABC-0042`.

- [ ] **Step 2: Migration + model + default rows on tenant migrate**

Map current hard-coded prefixes (`INV`, `BILL`, `CN`, …).

- [ ] **Step 3: Atomic increment — `UPDATE ... SET next_number = next_number + 1` or cache lock**

Fixes L12 race under concurrent invoice create.

- [ ] **Step 4: Settings UI under Company Settings**

- [ ] **Step 5: Wire FY reset using `tenants.financial_year_start_month`**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: tenant document numbering settings with FY reset"
```

**Done when:** Tenant can change INV prefix/next; new docs use settings; FY reset works.

---

## Task 5: Public HTML invoice page (#11)

**Goal:** Customer opens signed `/pay/invoice/{uuid}` with Pay Now, PDF, WhatsApp — no login.

**Existing assets to reuse:**
- `ShareLink::publicSigned()` — `app/Support/ShareLink.php`
- `InvoicePayNowService::paymentUrl()` — needs tenant init from invoice
- `GET /public/invoices/{uuid}/download` — PDF (`InvoiceController::publicDownloadPdf`)
- `GET /public/invoices/{uuid}/pay-return` — thank-you view
- Gateway callbacks — `/pay/toyyibpay/callback`, etc.

**Files:**
- Create: `resources/views/public/invoice.blade.php` — mobile-friendly HTML (Wave-style)
- Create: `app/Http/Controllers/PublicInvoiceController.php` — thin wrapper
- Modify: `routes/web.php`:
  ```php
  Route::get('/pay/invoice/{uuid}', [PublicInvoiceController::class, 'show'])
      ->name('public.invoices.show')
      ->middleware('signed');
  Route::post('/pay/invoice/{uuid}/pay', [PublicInvoiceController::class, 'pay'])
      ->name('public.invoices.pay')
      ->middleware('signed');
  ```
- Modify: `app/Services/InvoicePayNowService.php` — accept explicit `Tenant $tenant` (public context)
- Modify: `InvoiceController::show` — share HTML link not just PDF
- Modify: `resources/views/emails/invoice.blade.php` — add "View & Pay" button (L8)
- Modify: `app/Mail/InvoiceEmail.php` — pass signed HTML URL
- Create: `tests/Feature/Sales/PublicInvoicePageTest.php`

**Public page must show:**
- Company name, invoice number, dates, line items, `balance_due`
- **Pay Now** button → redirects to gateway (only if `InvoicePayNowService::isConfigured()`)
- **Download PDF** → existing signed download route
- **Share on WhatsApp** → `ShareLink::whatsapp()` with HTML page URL

**Tenant resolution (no auth):**
```php
// Invoice uuid is globally unique per tenant DB — initialize tenancy via invoice's tenant:
// Option A: central mapping table (if uuid lookup crosses DB)
// Option B: signed URL includes tenant_id param (like pay-return already does)
// Prefer B: /pay/invoice/{uuid}?tenant_id=... signed together
```

- [ ] **Step 1: Write failing test — signed HTML page 200 without auth**

- [ ] **Step 2: Write failing test — unsigned URL 403**

- [ ] **Step 3: Implement PublicInvoiceController + blade view**

Initialize tenancy from signed `tenant_id` query param.

- [ ] **Step 4: Public pay action — redirect to gateway URL**

Mock ToyyibPay in test; assert redirect.

- [ ] **Step 5: Update invoice show + email to surface HTML link**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: public HTML invoice page with pay, PDF, and WhatsApp"
```

**Done when:** Customer opens signed link, sees balance, can pay or download PDF.

**Blocks:** Wave 4 #26 customer portal.

---

## Task 6: Onboarding + MY localization (#12)

**Goal:** Day-1 checklist; Startup can record payment; Bahasa Malaysia for nav/invoices/bills; currency from `base_currency`.

**Files:**
- Create: `resources/js/Components/OnboardingChecklist.jsx`
- Create: `app/Http/Controllers/OnboardingChecklistController.php`
- Create: `database/migrations/2026_08_28_000001_add_onboarding_progress_to_users_table.php` (central) — JSON column `onboarding_steps`
- Modify: `database/seeders/PlanSeeder.php` — add `invoices.record-payment` to Startup (L5)
- Modify: `tests/Feature/Licensing/PlanPermissionAlignmentTest.php` — update Startup expectation
- Modify: `lang/ms.json` — translate keys from `lang/en.json` sections: `nav`, `invoices`, `bills`, `dashboard`, `common.actions`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx` — use `t('nav.*')` for sidebar
- Modify: `resources/js/Pages/Invoices/Index.jsx`, `Bills/Index.jsx` — key strings via i18n
- Modify: `resources/js/Components/WelcomeModal.jsx` — Startup-safe copy (L6)
- Modify: `app/Http/Controllers/SupplierStatementController.php` — use `tenantBaseCurrency()` (L7)
- Modify: `HandleInertiaRequests.php` — pass `onboarding_checklist` progress
- Create: `tests/Feature/Onboarding/OnboardingChecklistTest.php`
- Create: `tests/Feature/Licensing/StartupRecordPaymentTest.php`

**Checklist steps (SME):**
1. Complete company profile (`settings.company`)
2. Add first customer
3. Create and post first invoice
4. Record payment or send Pay Now link (depends on Task 5)
5. Dismiss checklist

**Startup record-payment scope:**
- Allow **manual** record payment on invoices only (not full Solo feature set)
- Keep bills, credit notes, deposits on Solo+ plan gates

- [ ] **Step 1: Write failing test — Startup user can POST invoice payment**

- [ ] **Step 2: Add `invoices.record-payment` to Startup in PlanSeeder; update alignment test**

- [ ] **Step 3: Implement checklist widget + progress persistence**

- [ ] **Step 4: Fix SupplierStatementController base currency (L7)**

- [ ] **Step 5: Translate `ms.json` (nav, invoices, bills, common) + wire Layout**

Minimum ~80 keys; fall back to `en.json` for rest.

- [ ] **Step 6: Update WelcomeModal copy for Startup vs trial**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat: day-1 onboarding checklist, Startup record payment, ms.json"
```

**Done when:** New user sees checklist; Startup records payment; BM nav/invoices/bills render; currency from tenant.

---

## Task 7: Expand money-path feature tests (#13)

**Goal:** ≥15 feature tests on post → pay → void → webhook paths (master plan Wave 2 metric).

**Existing (6 tests):**
- `tests/Feature/Sales/InvoiceMoneyPathTest.php` — 4 tests
- `tests/Feature/Sales/RemainingBalanceTest.php` — 2 tests
- `tests/Feature/Billing/ToyyibpayWebhookSecurityTest.php` — subscription only (not invoice)

**Files to create:**
- `tests/Feature/Sales/BillMoneyPathTest.php` — post, pay, void (3)
- `tests/Feature/Sales/ToyyibpayInvoiceWebhookAcceptTest.php` — verified callback settles (1)
- `tests/Feature/Sales/BillplzInvoiceWebhookRejectTest.php` — (1)
- `tests/Feature/Sales/ArDepositRemainingBalanceTest.php` — (1, may overlap Task 2)
- `tests/Feature/Sales/PublicInvoicePageTest.php` — (2, Task 5)
- `tests/Feature/Accounting/PeriodLockTest.php` — (2, Task 1)
- `tests/Feature/Tax/TaxCodePostingTest.php` — (1, Task 3)
- `tests/Feature/Licensing/StartupRecordPaymentTest.php` — (1, Task 6)

**Target count after Wave 2:** 6 existing + ~9 new = **≥15**

- [ ] **Step 1: BillMoneyPathTest — mirror InvoiceMoneyPathTest pattern**

Use `CreatesTestTenants`, seed COA via tenant migrate, supplier + bill lines.

- [ ] **Step 2: Webhook happy path — mock ToyyibPay verify true → invoice paid**

- [ ] **Step 3: Billplz invoice callback reject when signature invalid**

- [ ] **Step 4: Add tests as Tasks 1, 3, 5, 6 land**

- [ ] **Step 5: Run `php artisan test` — assert ≥15 in `tests/Feature/Sales/` + related Feature dirs**

- [ ] **Step 6: Commit incrementally per task**

**Done when:** CI green; ≥15 money-path feature tests; each new feature has at least one test.

---

## Task 8: Wave 2 integration sign-off

**Files:**
- Modify: `docs/superpowers/plans/2026-08-27-beat-competitors-waves-master.md` — link this plan; update score target
- Optional: `ROADMAP.md` — mark Wave 2 items

- [ ] **Step 1: Run full test suite**

```bash
/opt/homebrew/bin/php artisan test
```

- [ ] **Step 2: Manual smoke checklist**

1. Post invoice → apply credit note → AR aging shows reduced balance
2. Change document prefix → next invoice uses new prefix
3. Open signed `/pay/invoice/{uuid}` → Pay / PDF / WhatsApp visible
4. Startup user records payment on invoice
5. Switch locale to BM → nav shows Malay labels
6. Close accounting period → post rejected → reopen works
7. Post bill with tax → journal debits 1110 not 2100

- [ ] **Step 3: Commit any uncommitted Wave 2 work**

- [ ] **Step 4: Update master plan Wave 2 status**

---

## Wave 2 exit checklist ✅ Signed off 2026-08-27

- [x] **#7** `accounting_periods` table; `EnsurePeriodOpen` on write routes; reopen permission
- [x] **#8** `tax_codes` seeded (SR-8, ST-10, ES, ZRL); bill input tax → **1110**; SST report includes CN/DN *(CRUD + line FK → Wave 3 Task 0)*
- [x] **#9** All AR/AP surfaces use `remainingBalance`; deposit + supplier CN tests pass
- [x] **#10** Document numbering settings (prefix, next, FY reset) per doc type
- [x] **#11** Signed `/pay/invoice/{uuid}` HTML page with Pay, PDF, WhatsApp
- [x] **#12** Day-1 checklist; Startup `invoices.record-payment`; `ms.json` nav/invoices/bills; `base_currency` consistent
- [x] **#13** ≥15 money-path feature tests green
- [x] Logic bugs L1–L12 addressed or explicitly deferred with ticket
- [x] `php artisan tenants:migrate` run in dev/staging after deploy

**Target score after Wave 2:** overall **≥ 7.4** — **375 tests green**

---

## Execution handoff

Plan complete. Saved to:

- Master: [`2026-08-27-beat-competitors-waves-master.md`](./2026-08-27-beat-competitors-waves-master.md)
- Wave 1: [`2026-08-27-wave-1-trust-foundation.md`](./2026-08-27-wave-1-trust-foundation.md)
- **Wave 2: [`2026-08-27-wave-2-accountant-wave-simplicity.md`](./2026-08-27-wave-2-accountant-wave-simplicity.md)** ← this file

**Next:** Wave 3 — [`2026-08-27-wave-3-bukku-parity-practice-moat.md`](./2026-08-27-wave-3-bukku-parity-practice-moat.md) — start with Task 0 (tax-code CRUD + line FK).
