# Sales polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close Sales thin gaps — edit CN/DN/SO/DO/AR deposits, email SO/DO/DN/deposits, cancel SO, full uninvoiced DO return, batch SO — per `docs/superpowers/specs/2026-08-17-sales-polish-design.md`.

**Architecture:** Service-layer guards + update/cancel/return methods; controllers/routes mirror invoice email and batch patterns; Inertia Edit pages reuse Create forms; Mailables + Jobs like CreditNoteEmail. No inventory. No new migrations if `cancelled` DO status suffices.

**Tech Stack:** Laravel 12, Inertia React, DomPDF, queued Mail, Spatie permissions, tenant DB.

## Global Constraints

- Edit only under lock table in the spec (MyInvois lock, void, delivered/invoiced, applied deposits).
- CN/DN line edits only when unapplied/unrefunded (CN) and not void / not MyInvois-submitted.
- Delivery return: full DO only, uninvoiced only.
- Email uses `EnsureEmailVerifiedForOutbound` + `invoices.email` (or doc create where no email perm).
- Do not commit unless user asks (repo has large unrelated dirty tree).

---

### Task 1: Service guards — SO cancel, DO return, document lock helpers

**Files:**
- Modify: `app/Services/SalesOrderService.php`
- Modify: `app/Services/DeliveryOrderService.php`
- Modify: `app/Services/CreditNoteService.php`
- Modify: `app/Services/DebitNoteService.php`
- Modify: `app/Services/ArDepositService.php`
- Test: `tests/Unit/Sales/SalesOrderCancelReturnTest.php`

**Produces:** `SalesOrderService::update`, `::cancel`, `::assertEditable`; `DeliveryOrderService::update`, `::returnFull`; `CreditNoteService::update`, `::assertEditable`; `DebitNoteService::update`; `ArDepositService::update`.

- [ ] Implement methods + unit tests for cancel SO (blocked when delivered) and return DO (blocked when invoiced).
- [ ] Run: `php artisan test --filter=SalesOrderCancelReturn`

---

### Task 2: Routes + controllers — edit/update/email/cancel/return/batch

**Files:**
- Modify: `routes/web.php` (sales CN/DN/SO/DO/ar-deposits blocks)
- Modify: controllers listed in Task 1 domains
- Create: Mail/Job/views for DN, SO, DO, ArDeposit emails (mirror CreditNote)

---

### Task 3: Inertia UI — Edit pages + Show actions + Batch SO + Index Batch link

**Files:**
- Create: `resources/js/Pages/{CreditNotes,DebitNotes,SalesOrders,DeliveryOrders,ArDeposits}/Edit.jsx`
- Create: `resources/js/Pages/SalesOrders/Batch.jsx`
- Modify: Show.jsx + Index.jsx for each

---

### Task 4: Feature smoke — email middleware + batch store

**Files:**
- Create or extend: `tests/Feature/Sales/SalesPolishRoutesTest.php`

---

## Manual checklist

- [ ] Edit CN notes when unapplied; blocked after MyInvois submit
- [ ] Email DN/SO/DO/deposit with customer email
- [ ] Cancel empty SO; cannot cancel after DO
- [ ] Return delivered uninvoiced DO; SO qty restored
- [ ] Batch SO creates multiple confirmed orders
