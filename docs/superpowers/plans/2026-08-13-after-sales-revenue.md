# After Sales (Revenue) — Next Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish proving Sales on dummy data, then build Purchases (AP) to the same Malaysian-books bar.

**Architecture:** Sales is the revenue half (AR). Purchases is the expense half (AP). Mirror the same document jobs — bill Show, supplier credits, knock-off, recurring bills — without copying Sales UI blindly. Live LHDN / ToyyibPay stay a separate credentials track.

**Tech Stack:** Laravel 12, Stancl tenancy, Inertia React, Spatie permissions, tenant migrations under `database/migrations/tenant/`.

## Global Constraints

- Sales (Revenue) only stays frozen unless a click-through bug is found.
- New tenant schema goes in a **new** migration file. Do not edit `2026_08_13_000001` or `000002` if tenants may already have run them.
- PHPUnit: `php -d auto_prepend_file= vendor/bin/phpunit …` (Herd dump-loader).
- Invoice `{id}` routes stay `whereNumber('id')`; static paths before `{id}`.
- Do not commit unless asked.
- Do not add Peppol, Shopee, SST tax-code master, self-billed e-invoice, or WhatsApp Business API in this phase.

---

## Local dummy (already seeded)

Login at the local app URL (`APP_URL` is `http://localhost`; Herd may use a `.test` host).

| Who | Email | Password |
|---|---|---|
| **Sales demo (use this)** | `testdemo@bukucloud.com` | `Password123!` |
| Practice firm-owner | `testaccounter@bukucloud.com` | `Password123!` |
| Extra SME | `admin@fasttrade.my` | `password` |

On the sales demo tenant (`bukucloud-demo_938`): 5 customers, 12 invoices including **INV-OVERDUE-001**, 2 credit notes including leftover **CN-OPEN-0001**, 1 debit note, 1 SO + 1 DO, 1 unapplied deposit `DEMO-RECEIPT-001`, 8 products, 5 estimates, 3 recurring templates.

---

### Task 1: Click-through Sales QA on dummy data

**Files:** none (manual). File bugs against existing Sales controllers/pages only.

- [ ] Log in as `testdemo@bukucloud.com`
- [ ] Open **INV-OVERDUE-001** → Late fee → confirm a draft interest invoice
- [ ] **Customer Deposits → New receipt** for Tropika → allocate across INV-OVERDUE-001 and INV-OPEN-001
- [ ] Open **CN-OPEN-0001** → refund leftover to bank 1200
- [ ] Open **SO-DEMO-0001** → deliver remaining qty → convert leftover to invoice
- [ ] Recurring templates → confirm auto-post is on for the first template
- [ ] Customer statement for Tropika → charges, knock-off, CN, Pay Now row
- [ ] Practice login `testaccounter@bukucloud.com` → **AR aging**
- [ ] Record any broken page as a Sales bugfix task before starting Purchases

---

### Task 2: Purchases (AP) document parity — schema

**Files:**
- Create: `database/migrations/tenant/2026_08_14_000001_purchases_ap_parity.php`
- Mirror Sales: `bill_items.product_id` / `account_code`, bill payments table, supplier credits, debit notes from supplier, purchase orders if AutoCount-shaped, AP deposits (prepayments)

**Interfaces:**
- Consumes: existing `bills`, `bill_items`, `BillService`
- Produces: tenant tables ready for Task 3 services

- [ ] **Step 1:** List current `bills` columns vs Sales `invoices` after `000001`/`000002`
- [ ] **Step 2:** Write migration (new file only) for missing AP columns/tables
- [ ] **Step 3:** `php -d auto_prepend_file= artisan tenants:migrate`
- [ ] **Step 4:** Seed 2200-equivalent **supplier prepayments** COA code if missing (do not reuse 2200)

---

### Task 3: Purchases services + Show pages

**Files:**
- Modify: `app/Services/BillService.php`
- Create: `app/Services/SupplierCreditService.php` (AP reverse of `CreditNoteService`)
- Modify: `app/Http/Controllers/BillController.php`
- Create: `resources/js/Pages/Bills/Show.jsx` (match Invoices/Show jobs: duplicate, PDF, post, knock-off, void)

**Interfaces:**
- Bill payment: Dr AP (2100-style payable), Cr Bank — never double-count with supplier credits
- Supplier credit issue: reverse expense + SST, credit AP; apply leftover; cash refund Dr Bank Cr AP

- [ ] **Step 1:** Unit tests for bill remaining balance (payments + supplier credits)
- [ ] **Step 2:** `php -d auto_prepend_file= vendor/bin/phpunit tests/Unit/Purchases/`
- [ ] **Step 3:** Implement Bill Show + record payment + duplicate
- [ ] **Step 4:** Supplier credit issue/apply/void/refund
- [ ] **Step 5:** One receipt → many bills (AP knock-off), leftover as supplier prepayment

---

### Task 4: Live MyInvois + Pay Now (credentials track)

**Files:**
- Modify: `config/myinvois.php`, `app/Services/MyInvoisService.php`, `app/Services/InvoicePayNowService.php`
- Settings already store client id/secret and ToyyibPay keys

- [ ] **Step 1:** Sandbox MyInvois with a real IRBM test TIN (not dummy C25876543210)
- [ ] **Step 2:** Set `MYINVOIS_MODE=live` only on the sandbox tenant
- [ ] **Step 3:** Submit INV-OPEN-001 (or a fresh invoice) and confirm UUID + QR
- [ ] **Step 4:** Save ToyyibPay category + secret → Pay Now on invoice and statement
- [ ] **Step 5:** Keep dry-run as default for local dummy so seed data never hits LHDN

---

### Task 5: Do not start until Tasks 1–3 are green

- Inventory warehouses / stock valuation
- Payroll
- Peppol / Shopee
- Extra PDF layout designer
- WhatsApp Business API

---

## Suggested order

1. Task 1 (same day — you now have dummy data)
2. Task 2–3 (next build: Purchases AP)
3. Task 4 whenever Asyraf has IRBM + ToyyibPay sandbox keys
