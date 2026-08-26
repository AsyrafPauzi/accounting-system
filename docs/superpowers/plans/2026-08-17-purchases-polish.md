# Purchases polish + Bukku bill types Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Bukku-simple bill types (credit / cash / claim) plus Sales-style PO/GR polish, batch bills, and AP document trail.

**Architecture:** `bills.purchase_kind` plus `BillService::create` posts+pays cash in one transaction. PO cancel and GR return mirror SO/DO guards. Email/PDF reuse `pdf.sales-document` and `emails.sales-document`. Trail is read-only like `SalesDocumentTrail`.

**Tech Stack:** Laravel 12, Inertia React, DomPDF, queued Mail, tenant migrations.

## Global Constraints

- Follow spec `docs/superpowers/specs/2026-08-17-purchases-polish-design.md`.
- No inventory, inbox, hire purchase, batch payments, or plan price changes.
- Skip SCN/SDN/AP-deposit edit in this wave (not in success criteria).
- New tenant migration only; do not edit older migrations.
- PHPUnit: `php -d auto_prepend_file= vendor/bin/phpunit …`
- Do not commit unless asked.

---

### Task 1: Schema + BillService cash/claim

**Files:**
- Create: `database/migrations/tenant/2026_08_17_000030_purchases_polish.php`
- Modify: `app/Models/Bill.php`, `app/Services/BillService.php`, `app/Http/Requests/StoreBillRequest.php`, `app/Http/Controllers/BillController.php`, `app/Services/Copilot/CopilotTools.php`

- [ ] Add `purchase_kind` string default `credit` on bills.
- [ ] `create()` stores kind; claim prefixes notes; cash posts + full payment.
- [ ] Validation: kind in credit|cash|claim; supplier required for cash/claim; bank required for cash.
- [ ] Copilot claim + draft bill set `purchase_kind`.

---

### Task 2: PO cancel/update + GR return/update

**Files:**
- Modify: `app/Services/PurchaseOrderService.php`, `app/Services/GoodsReceiptService.php`
- Test: `tests/Unit/Purchases/PurchaseOrderCancelReturnTest.php`

- [ ] `assertEditable` / `update` / `cancel` on PO.
- [ ] `assertEditable` / `update` / `returnFull` on GR.
- [ ] `refreshStatus` restores `confirmed` after return; never overwrites `cancelled`.

---

### Task 3: Routes, email, batch, trail

**Files:**
- Create: Mail/Jobs for PO and GR; `app/Services/PurchasesDocumentTrail.php`
- Modify: controllers, `routes/web.php`, `resources/js/Components/DocumentTrail.jsx`

---

### Task 4: Inertia UI

**Files:**
- Modify: Bills Create/Index/Show, PO Show/Create, GR Show
- Create: `PurchaseOrders/Edit.jsx`, `Bills/Batch.jsx`

---

### Task 5: Tests

**Files:**
- `tests/Unit/Purchases/PurchaseOrderCancelReturnTest.php`
- `tests/Feature/Purchases/PurchasesPolishRoutesTest.php`

---

## Manual checklist

- [ ] Credit bill still saves as draft
- [ ] Cash purchase ends paid with payment row
- [ ] Claim bill unpaid, labelled claimant
- [ ] Cancel empty PO; return unbilled GR
- [ ] Batch bills; email PO/GR
- [ ] Trail on PO / GR / Bill Show
