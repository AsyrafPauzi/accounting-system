# Wave 3 — Bukku Parity + Practice Moat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Honest claim: “better than Bukku for firms, better than Wave for Malaysian SMEs.” Ship bank reconciliation lite, SST-02 filing export, official receipt/voucher PDFs, receipt inbox, practice month-end close pack, MyInvois audit vault, Growth-tier e-invoice, and ops that scale past ~50 tenants.

**Architecture:** Extend existing money-path, OCR, practice console, and MyInvois stacks — no parallel implementations. Bank rec matches imported statement lines to posted `journal_items`. SST-02 reads the same `tax_codes` master as posting. Receipt inbox refactors `ProcessOcr` into persistent `ocr_jobs`. Practice close pack aggregates per-client signals already computed in `PracticeMetricsService`. Payload vault stores request/response JSON beside LHDN status on documents.

**Tech Stack:** Laravel 12, Inertia/React, Stancl Tenancy, PHPUnit 11, DomPDF, existing OCR providers (Gemini/Tesseract/Ilmu), ECS Fargate + SQS queue

**Master plan:** [`2026-08-27-beat-competitors-waves-master.md`](./2026-08-27-beat-competitors-waves-master.md)  
**Depends on:** Wave 1 complete (posted journals, webhooks, CI, firm RBAC) · Wave 2 complete (period lock, remainingBalance, doc numbering, public HTML invoice, onboarding)

---

## Current state audit (2026-08-27)

| # | Deliverable | Status | Notes |
|---|-------------|--------|-------|
| 14 | Bank rec v1 | **~8%** | `TransactionsController` feed only; no statement import or matching |
| 15 | SST-02 export | **~45%** | SST period pack exists; no SST-02 form; report groups by `%` not code |
| 16 | Official receipt + payment voucher PDFs | **~30%** | AR payment receipt only; no AP voucher; minimal branding |
| 17 | Receipt inbox / OCR jobs | **~50%** | `ProcessOcr` + bill-scoped upload; no inbox UI or persistent jobs |
| 18 | Practice close pack + firm staff invites | **~40%** | Practice dashboard + client metrics; no close widget; no firm-staff UI |
| 19 | MyInvois payload vault + Growth e-invoice | **~65% / 0% vault** | Full submit flow; no submission storage; Corporate-only gate |
| 20 | Ops scale | **~5%** | `tenants:migrate` on every ECS boot; sync tenant provision |

**Wave 2 carryover (blocks Wave 3 #15, #17, #18, #19):**

| Item | Status | Notes |
|------|--------|-------|
| Tax-code CRUD + `tax_code_id` on line items | **~40%** | Table + defaults seeded; no line FK migration; no settings UI |
| Period lock on all write routes | **~70%** | Service layer + payment middleware; void/post/CN routes partial |
| Full i18n (Invoices/Bills index) | **~30%** | Nav partial; list pages still English |

**Wave 3 overall:** ~35% complete by deliverable count; strongest foundations on #17 OCR and #19 MyInvois submit.

---

## Known logic bugs & missing features (must fix in Wave 3)

| ID | Issue | Where | Fix in task |
|----|-------|-------|-------------|
| L13 | SST report groups by `tax_rate` % not `tax_codes.code` | `SalesTaxReportController.php` L141–165 | Task 0 → Task 2 |
| L14 | No `tax_code_id` on invoice/bill/CN line items | line item migrations missing | Task 0 |
| L15 | Bill confirm-from-OCR ignores tax code mapping | `BillController.php`, `ReceiptParser.php` | Task 5 |
| L16 | Payment receipt is plain PDF, not numbered Official Receipt | `payment-receipt.blade.php` | Task 3 |
| L17 | No AP payment voucher PDF for `BillPayment` | `BillController.php` | Task 3 |
| L18 | MyInvois submit discards request/response JSON on failure | `MyInvoisService.php` submitRaw | Task 7 |
| L19 | `myinvois.submit` Corporate-only; Growth marketing silent | `PlanSeeder.php` L528–537 | Task 7 |
| L20 | ECS boot runs `tenants:migrate` for all tenants | `docker/entrypoint.sh` L29–30 | Task 1 |
| L21 | Tenant provision blocks HTTP until DB+migrate done | `RegisteredUserController.php`, `AddClientController.php` | Task 1 |
| L22 | Practice dashboard has no per-client SST / unbilled / period status | `PracticeMetricsService.php` | Task 6 |
| L23 | `practice.staff.manage` permission has no UI | `RolesAndPermissionsSeeder.php` | Task 6 |
| L24 | Bank feed shows journals but no statement balance reconciliation | `TransactionsController.php` | Task 4 |

---

## Global constraints

- Tenant migrations under `database/migrations/tenant/`; run `php artisan tenants:migrate` after schema changes.
- Wave 1 golden rule still applies until Wave 3 bank rec is trustworthy: do not ship inventory, POS, or fixed assets.
- **Task 0 (Wave 2 tax-code finish) blocks Task 2 (SST-02)** — do not start SST-02 export until line FK + CRUD land.
- **Task 1 (ops scale) should land early** — reduces deploy risk before feature velocity.
- Bank rec v1 is **suggest-match only** — no auto-post without user confirm (Copilot confirm-gate pattern).
- SST-02 export is a **filing helper** — label clearly “figures for your return, verify before submit to MyTax”.
- MyInvois Growth move is **packaging only** — do not weaken UBL validation or readiness checks.
- Tests: `/opt/homebrew/bin/php artisan test`; tenant tests use `CreatesTestTenants` trait.
- Target **≥25 money-path / compliance feature tests** after Wave 3 (master plan metric).

---

## Dependency graph (within Wave 3)

```
Task 0 (Wave 2 tax finish) ──► Task 2 (#15 SST-02)
        │
        ▼
Task 1 (#20 ops scale) ──► independent; do early
        │
        ├──► Task 3 (#16 PDFs) ──► uses doc numbering from Wave 2
        │
        ├──► Task 5 (#17 inbox) ──► depends Task 0 for tax on confirm
        │
        ├──► Task 4 (#14 bank rec) ──► largest; needs posted journals + period lock
        │
        ├──► Task 6 (#18 close pack) ──► depends Task 0 + Task 2 signals
        │
        └──► Task 7 (#19 vault + Growth) ──► depends Task 0 tax alignment
                │
                ▼
           Task 8 (sign-off)
```

**Recommended execution order:** Task 0 → Task 1 → Task 2 → Task 3 → Task 5 → Task 4 → Task 6 → Task 7 → Task 8

---

## Task 0: Finish Wave 2 tax-code master (prerequisite)

**Goal:** Close Wave 2 #8 gaps so Wave 3 SST-02, OCR confirm, and MyInvois UBL all read the same tax master.

**Files:**
- Create: `database/migrations/tenant/2026_08_29_000001_add_tax_code_id_to_line_items.php`
- Create: `app/Http/Controllers/TaxCodeController.php`
- Create: `resources/js/Pages/Settings/TaxCodes/Index.jsx`
- Modify: `app/Services/InvoiceService.php`, `BillService.php`, `CreditNoteService.php`, `DebitNoteService.php`, `SupplierCreditNoteService.php`, `SupplierDebitNoteService.php` — resolve GL from tax code
- Modify: `app/Http/Controllers/SalesTaxReportController.php` — group by `tax_codes.code` (L13)
- Modify: invoice/bill/CN create forms — tax code dropdown
- Modify: `routes/web.php`, `resources/js/Pages/Settings/Company.jsx` — link to Tax codes
- Create: `tests/Feature/Tax/TaxCodePostingTest.php`
- Create: `tests/Feature/Tax/SalesTaxReportCnDnTest.php`

**Schema addition:**
```php
// invoice_items, bill_items, credit_note_items, debit_note_items, supplier_*_items
$table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
```

**Backfill:** Map existing `tax_rate` → nearest seeded code (8% → SR-8, 10% → ST-10, 0% → ES).

- [ ] **Step 1: Write failing test — invoice line with SR-8 posts Cr 2100 at 8%**

- [ ] **Step 2: Migration + backfill command or seeder hook**

- [ ] **Step 3: Tax code CRUD (index, create, update, deactivate — no delete if used)**

- [ ] **Step 4: Wire forms + services to use `tax_code_id`**

- [ ] **Step 5: Refactor SalesTaxReport to group by code; extend CN/DN tests**

- [ ] **Step 6: Run tax feature tests + commit**

```bash
git commit -m "feat: tax-code CRUD and line-item FK for SST posting"
```

**Done when:** Bill posts Dr 1110 via code; SST pack grouped by SR-8/ST-10/ES/ZRL; CN/DN tests green.

**Blocks:** Task 2, Task 5, Task 6 SST gaps, Task 7 UBL alignment.

---

## Task 1: Ops scale — queue tenant provision, remove boot migrate (#20)

**Goal:** ECS web containers start in seconds; tenant DB creation runs on a queue; deploy migrates tenants via one-off task, not every boot.

**Files:**
- Modify: `docker/entrypoint.sh` — central `migrate --force` only; remove `tenants:migrate` (or gate behind `RUN_TENANT_MIGRATE=1`)
- Modify: `app/Providers/TenancyServiceProvider.php` — `shouldBeQueued(true)` on `TenantCreated` pipeline
- Create: `app/Jobs/ProvisionTenantJob.php` — wrap CreateDatabase + MigrateDatabase + optional defaults seed
- Create: `database/migrations/2026_08_29_000001_add_provision_status_to_tenants_table.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php` — async provision + poll redirect
- Modify: `app/Http/Controllers/Practice/AddClientController.php` — same
- Create: `resources/js/Pages/Auth/Provisioning.jsx` — “Setting up your books…” spinner
- Modify: `DEPLOYMENT.md` — ECS one-off migrate task definition
- Modify: `.github/workflows/deploy.yml` — optional migrate step before traffic shift
- Create: `tests/Feature/Tenancy/AsyncProvisionTest.php`

**Schema (`tenants`):**
```php
$table->enum('provision_status', ['pending', 'provisioning', 'ready', 'failed'])->default('pending');
$table->text('provision_error')->nullable();
$table->timestamp('provisioned_at')->nullable();
```

- [ ] **Step 1: Write failing test — tenant create returns before DB ready, status pending**

- [ ] **Step 2: Add provision_status column + model casts**

- [ ] **Step 3: Implement ProvisionTenantJob; queue TenantCreated pipeline**

- [ ] **Step 4: Registration + AddClient poll until ready or failed**

- [ ] **Step 5: Remove tenants:migrate from entrypoint; document one-off task**

- [ ] **Step 6: Run tests + staging smoke (boot time < 30s with 100+ tenants)**

- [ ] **Step 7: Commit**

```bash
git commit -m "fix: queue tenant provision and remove boot-time tenants:migrate"
```

**Done when:** New signup sees provisioning page then dashboard; ECS boot does not iterate all tenants; failed provision surfaces error + retry.

---

## Task 2: SST-02 export from tax codes (#15)

**Goal:** Download SST-02 / SST-02A helper file (CSV + PDF summary) mapped to LHDN return lines from the same data as the SST period pack.

**Files:**
- Create: `app/Services/Sst02ExportService.php`
- Modify: `app/Http/Controllers/SalesTaxReportController.php` — `exportSst02()` action
- Create: `resources/views/pdf/sst-02-summary.blade.php`
- Modify: `resources/js/Pages/Reports/SalesTax.jsx` — “Download SST-02 helper” button + disclaimer
- Modify: `routes/web.php` — `reports.sales-tax.export-sst02`
- Create: `tests/Feature/Tax/Sst02ExportTest.php`

**Export shape (CSV columns — map to MyTax upload fields):**
```php
[
    'tax_code',           // SR-8, ST-10, ES, ZRL
    'taxable_sales',      // output base
    'output_tax',         // 2100 movement
    'taxable_purchases',  // input base
    'input_tax',          // 1110 movement
    'net_tax',            // output - input
    'cn_adjustment',      // credit notes
    'dn_adjustment',      // debit notes
]
```

- [ ] **Step 1: Write failing test — posted SR-8 invoice + ST-10 bill → SST-02 CSV has two rows**

- [ ] **Step 2: Implement Sst02ExportService reading tax_codes (depends Task 0)**

- [ ] **Step 3: Add controller action + authorize `reports.sales-tax`**

- [ ] **Step 4: PDF summary page mirroring CSV totals**

- [ ] **Step 5: UI button + disclaimer copy**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: SST-02 helper export from tax codes"
```

**Done when:** Growth user downloads SST-02 CSV for a period; totals match SST pack; test green.

---

## Task 3: Official receipt + payment voucher PDFs (#16)

**Goal:** AutoCount-grade collections paperwork — numbered Official Receipt (AR) and Payment Voucher (AP) with company letterhead.

**Files:**
- Modify: `resources/views/pdf/payment-receipt.blade.php` — rebrand as Official Receipt; reuse `sales-document.blade.php` styling
- Create: `resources/views/pdf/payment-voucher.blade.php`
- Modify: `app/Http/Controllers/InvoiceController.php` — assign receipt number via `DocumentNumber::next('official_receipt')`
- Modify: `app/Http/Controllers/BillController.php` — `paymentVoucher()` method
- Modify: `app/Models/DocumentNumberSetting.php` — add `official_receipt`, `payment_voucher` types
- Modify: `app/Services/InvoiceService.php`, `BillService.php` — optional auto-generate receipt number on recordPayment
- Modify: `resources/js/Pages/Invoices/Show.jsx`, `Bills/Show.jsx` — voucher/receipt links
- Modify: `resources/views/emails/invoice.blade.php` — attach or link receipt after Pay Now (optional)
- Create: `tests/Feature/Sales/OfficialReceiptPdfTest.php`
- Create: `tests/Feature/Purchases/PaymentVoucherPdfTest.php`

- [ ] **Step 1: Write failing test — record payment → receipt PDF 200 with OR number**

- [ ] **Step 2: Add doc number types + migration seed defaults**

- [ ] **Step 3: Enhance receipt template (company header, SST reg, payment method, amount in words)**

- [ ] **Step 4: Implement bill payment voucher endpoint + template**

- [ ] **Step 5: Wire UI links on invoice/bill show pages**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: official receipt and payment voucher PDFs with numbering"
```

**Done when:** AR payment shows numbered Official Receipt; AP payment shows Payment Voucher; PDFs include company branding.

---

## Task 4: Bank reconciliation v1 — CSV upload + suggest-match (#14)

**Goal:** Wave-style bank feed upgrade — import statement, suggest matches to posted journals/payments, user confirms.

**Files:**
- Create: `database/migrations/tenant/2026_08_29_000002_create_bank_statements_tables.php`
- Create: `app/Models/BankStatement.php`, `BankStatementLine.php`
- Create: `app/Services/BankStatementImportService.php` — CSV parser (Maybank, CIMB, generic columns)
- Create: `app/Services/BankReconciliationService.php` — suggest-match by amount ±date window ±reference
- Create: `app/Http/Controllers/BankReconciliationController.php`
- Create: `resources/js/Pages/BankRec/Index.jsx`, `Import.jsx`, `Match.jsx`
- Modify: `routes/web.php` — `bank-rec.*` routes gated `bank-rec.view` / `bank-rec.match`
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`, `PlanSeeder.php` — Growth+ `bank-rec.view`
- Reuse: `app/Services/Ocr/PdfPreprocessor.php` for PDF statement text extract (optional v1.1)
- Create: `tests/Feature/BankRec/BankStatementImportTest.php`
- Create: `tests/Feature/BankRec/SuggestMatchTest.php`

**Schema:**
```php
// bank_statements
$table->foreignId('account_id')->constrained('accounts'); // bank/cash GL account
$table->date('period_start');
$table->date('period_end');
$table->decimal('opening_balance', 15, 2);
$table->decimal('closing_balance', 15, 2);
$table->string('source'); // csv, pdf, manual
$table->string('file_path')->nullable();
$table->enum('status', ['open', 'reconciled'])->default('open');

// bank_statement_lines
$table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
$table->date('transaction_date');
$table->string('description')->nullable();
$table->string('reference')->nullable();
$table->decimal('amount', 15, 2); // signed: + in, - out
$table->foreignId('matched_journal_item_id')->nullable()->constrained('journal_items')->nullOnDelete();
$table->enum('match_status', ['unmatched', 'suggested', 'matched', 'excluded'])->default('unmatched');
$table->decimal('match_confidence', 3, 2)->nullable();
```

**Suggest-match algorithm (v1):**
1. Same absolute amount within ±3 days
2. Boost score if reference contains invoice/bill number
3. Only suggest posted `journal_items` on the selected bank account
4. User confirm sets `matched_journal_item_id`, status `matched`

- [ ] **Step 1: Write failing test — import 3-line CSV → 3 statement lines created**

- [ ] **Step 2: Migrations + models**

- [ ] **Step 3: CSV import (generic + one MY bank template)**

- [ ] **Step 4: Suggest-match service + tests**

- [ ] **Step 5: Reconciliation UI — list unmatched, accept/reject suggestions**

- [ ] **Step 6: Period lock on match confirm if line date in closed period**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat: bank reconciliation v1 with CSV import and suggest-match"
```

**Done when:** User imports CSV, sees suggestions, confirms match; reconciled balance equals statement closing; closed period blocks match on old dates.

---

## Task 5: Receipt inbox — OCR jobs as a list (#17)

**Goal:** Bukku Digital Shoebox lite — upload receipts without opening a bill first; review OCR; confirm → create bill.

**Files:**
- Create: `database/migrations/tenant/2026_08_29_000003_create_ocr_jobs_table.php`
- Create: `app/Models/OcrJob.php`
- Modify: `app/Jobs/ProcessOcr.php` — always create/update `OcrJob` row
- Create: `app/Http/Controllers/ReceiptInboxController.php`
- Create: `resources/js/Pages/Receipts/Inbox.jsx`, `Review.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx` — Purchases → Receipt inbox nav
- Modify: `app/Support/OnboardingChecklist.php` — optional step “Upload first receipt”
- Reuse: `app/Services/OCRService.php`, `ReceiptParser.php`, `BillService.php`
- Create: `tests/Feature/Ocr/ReceiptInboxTest.php`

**Schema (`ocr_jobs`):**
```php
$table->id();
$table->string('file_path');
$table->string('original_filename')->nullable();
$table->enum('status', ['pending', 'processing', 'ready', 'failed', 'confirmed', 'discarded'])->default('pending');
$table->json('parsed_data')->nullable();
$table->text('error_message')->nullable();
$table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('created_by')->nullable();
$table->timestamps();
```

- [ ] **Step 1: Write failing test — upload image → OcrJob pending → ProcessOcr → ready**

- [ ] **Step 2: Migration + model + refactor ProcessOcr**

- [ ] **Step 3: Inbox list page (status filters, retry failed)**

- [ ] **Step 4: Review page — edit parsed fields, pick tax code (Task 0), confirm → bill draft**

- [ ] **Step 5: Plan gate `ocr.use` (Solo+) unchanged**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: receipt inbox with persistent OCR jobs"
```

**Done when:** User uploads receipt from inbox; OCR runs; confirm creates bill with tax code; failed jobs retryable.

---

## Task 6: Practice close pack + firm staff invites (#18)

**Goal:** Firm moat — per-client month-end checklist on practice dashboard; invite firm staff (not just client tenants).

**Files:**
- Modify: `app/Services/Practice/PracticeMetricsService.php`:
  - `closePackForClient(Tenant $client): array` — unbilled drafts, overdue AR, SST gaps, open period, payroll remittance due
- Modify: `resources/js/Pages/Practice/Dashboard.jsx` — expandable close-pack row per client
- Create: `app/Http/Controllers/Practice/PracticeStaffController.php`
- Create: `resources/js/Pages/Practice/Team.jsx`
- Modify: `routes/web.php` — `practice.team.*` gated `practice.staff.manage`
- Modify: `app/Models/Firm.php` — enforce staff seat cap from plan
- Reuse: `TenantUserController` invite pattern (email → accept → attach to firm)
- Create: `tests/Feature/Practice/ClosePackTest.php`
- Create: `tests/Feature/Practice/FirmStaffInviteTest.php`

**Close pack signals per client:**
| Signal | Source |
|--------|--------|
| Unbilled | Draft/posted-not-sent invoices count |
| Overdue AR | `PracticeMetricsService` aging > 0 past due |
| SST gaps | `MyInvoisGap` + unsubmitted posted invoices in period |
| Period status | `accounting_periods` current month open/closed |
| Payroll remittance | `PayrollRemittanceController` next due date |

- [ ] **Step 1: Write failing test — client with draft invoice → close pack shows unbilled=1**

- [ ] **Step 2: Implement closePackForClient() aggregating signals**

- [ ] **Step 3: Dashboard widget — traffic-light per signal**

- [ ] **Step 4: Firm staff invite CRUD (owner + practice.staff.manage)**

- [ ] **Step 5: Seat cap enforcement test**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: practice close pack widget and firm staff invites"
```

**Done when:** Practice owner sees per-client close checklist; can invite firm staff; viewer cannot manage staff.

---

## Task 7: MyInvois payload vault + e-invoice on Growth (#19)

**Goal:** Audit trail for LHDN submissions; move `myinvois.submit` to Growth plan (Bukku packaging win).

**Files:**
- Create: `database/migrations/tenant/2026_08_29_000004_create_myinvois_submissions_table.php`
- Create: `app/Models/MyInvoisSubmission.php`
- Modify: `app/Services/MyInvoisService.php` — persist request/response in `submitRaw()` success and failure paths
- Create: `app/Http/Controllers/MyInvoisSubmissionController.php` — list/show for audit (admin/accountant)
- Create: `resources/js/Pages/MyInvois/Submissions.jsx`
- Modify: `database/seeders/PlanSeeder.php` — add `myinvois.submit` to Growth; update marketing bullets
- Modify: `tests/Feature/Licensing/PlanPermissionAlignmentTest.php`
- Create: `tests/Feature/Sales/MyInvoisSubmissionVaultTest.php`

**Schema (`myinvois_submissions`):**
```php
$table->id();
$table->string('document_type'); // invoice, credit_note, bill, consolidated
$table->unsignedBigInteger('document_id');
$table->json('request_json');
$table->json('response_json')->nullable();
$table->unsignedSmallInteger('http_status')->nullable();
$table->string('lhdn_uuid')->nullable();
$table->string('status'); // submitted, accepted, rejected, error
$table->timestamp('submitted_at');
$table->timestamps();
$table->index(['document_type', 'document_id']);
```

- [ ] **Step 1: Write failing test — submit invoice → submission row with request_json**

- [ ] **Step 2: Migration + model**

- [ ] **Step 3: Persist in MyInvoisService (success + HTTP error + validation fail)**

- [ ] **Step 4: Submissions audit UI (read-only, filter by date/status)**

- [ ] **Step 5: Move Growth plan permission; update alignment test + PlanFirm/Growth marketing**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: MyInvois payload vault and Growth-tier e-invoice"
```

**Done when:** Every submit attempt stored; Growth user can submit; Corporate unchanged; failed submission debuggable from vault UI.

---

## Task 8: Wave 3 integration sign-off

**Files:**
- Modify: `docs/superpowers/plans/2026-08-27-beat-competitors-waves-master.md` — Wave 3 status
- Optional: `ROADMAP.md` — mark Wave 3 items

- [ ] **Step 1: Run full test suite**

```bash
/opt/homebrew/bin/php artisan test
```

- [ ] **Step 2: Manual smoke checklist**

1. Register new tenant → provisioning page → dashboard (async provision)
2. Import bank CSV → suggest-match → confirm → reconciled
3. Upload receipt to inbox → OCR → confirm bill with SR-8 tax code
4. Download SST-02 CSV → totals match SST pack
5. Record invoice payment → Official Receipt with number; bill payment → voucher PDF
6. Practice dashboard → client close pack shows SST gap + overdue AR
7. Invite firm staff → staff logs in with practice.staff.manage
8. Submit MyInvois → submission vault shows request/response JSON
9. Growth plan user can access MyInvois submit

- [ ] **Step 3: ECS deploy smoke — boot without tenants:migrate; one-off migrate task**

- [ ] **Step 4: Update master plan Wave 3 status**

---

## Wave 3 exit checklist

- [ ] **#14** `bank_statements` + lines; CSV import; suggest-match UI; period lock on match
- [ ] **#15** SST-02 helper export (CSV + PDF) from tax codes; disclaimer present
- [ ] **#16** Official Receipt (AR) + Payment Voucher (AP) PDFs with document numbering
- [ ] **#17** Receipt inbox with persistent `ocr_jobs`; confirm → bill with tax code
- [ ] **#18** Practice close pack widget; firm staff invite UI + seat cap
- [ ] **#19** `myinvois_submissions` vault; `myinvois.submit` on Growth plan
- [ ] **#20** No `tenants:migrate` on ECS boot; queued tenant provision with status UI
- [ ] **Task 0** Tax-code CRUD + line FK complete (Wave 2 #8 closed)
- [ ] Logic bugs L13–L24 addressed or explicitly deferred with ticket
- [ ] ≥25 feature tests on money/compliance paths green
- [ ] `php artisan tenants:migrate` run via deploy task, not web boot

**Target score after Wave 3:** overall **≥ 7.8** (per master plan)

---

## Execution handoff

Plan complete. Saved to:

- Master: [`2026-08-27-beat-competitors-waves-master.md`](./2026-08-27-beat-competitors-waves-master.md)
- Wave 1: [`2026-08-27-wave-1-trust-foundation.md`](./2026-08-27-wave-1-trust-foundation.md) ✅ signed off
- Wave 2: [`2026-08-27-wave-2-accountant-wave-simplicity.md`](./2026-08-27-wave-2-accountant-wave-simplicity.md) ✅ signed off
- **Wave 3: [`2026-08-27-wave-3-bukku-parity-practice-moat.md`](./2026-08-27-wave-3-bukku-parity-practice-moat.md)** ← this file

**Next:** Task 0 (finish tax codes) → Task 1 (ops scale) → Task 2 (SST-02) → Task 3 (PDFs) → Task 5 (inbox) → Task 4 (bank rec) → Task 6 (close pack) → Task 7 (MyInvois vault) → Task 8 sign-off.

**Do not start Wave 4** until Wave 3 exit checklist is signed off.
