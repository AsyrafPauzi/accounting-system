# Beat Bukku / Wave / AutoCount — Waves 1–4 Master Plan

**Date:** 2026-08-27  
**Status:** Approved for execution  
**Source:** [Whole-system audit canvas](/Users/intanlyeanna/.cursor/projects/Users-intanlyeanna-Documents-02-Work-Projects-Kerja-Kerja-Asyraf-bukucloud-system/canvases/whole-system-audit.canvas.tsx)

## North star

Make BukuCloud a **trusted book of record** for Malaysian SMEs and accounting firms — then pull ahead with **practice desk + Copilot + self-hosted**, where Bukku and AutoCount cannot follow in the cloud.

**Score today:** 5.6 / 10 overall  
**Score targets:** Day 14 → 6.6 · Day 45 → 7.4 · Day 90 → 7.8 · Day 180 → 8.5

## Golden rule (all waves)

Do **not** ship inventory, POS, fixed assets, or more Copilot tools until:

1. Ledger posts cleanly (`status=posted`, reports filter posted, BS balances)
2. Payment webhooks cannot be faked
3. Production cron is proven (renewals, reminders, recurring)

---

## Wave 1 — Trust foundation (days 1–14) ✅ Signed off 2026-08-27

**Goal:** Stop losing the first accountant demo and stop leaking SaaS revenue.  
**Detailed plan:** [`2026-08-27-wave-1-trust-foundation.md`](./2026-08-27-wave-1-trust-foundation.md)  
**Commits:** `cf7d6bb` (core) · **375 tests green**

| # | Deliverable | Done when |
|---|---|---|
| 1 | Posted journals only; invoice journal date = `issue_date` | Backdated invoice: TB and cash agree |
| 2 | Balance sheet includes current-year earnings | A = L + E after one posted sale |
| 3 | Verify ToyyibPay; Billplz HMAC fail-closed | Unsigned webhook does not activate |
| 4 | ECS scheduler runs `schedule:run` | CloudWatch shows renewal/reminder logs |
| 5 | CI: tests + build gate before deploy | Red build cannot reach ECS |
| 6 | Firm RBAC: viewer / editor / admin | Firm viewer cannot POST `/invoices` |

**Beats:** ledger trust (Bukku/AutoCount), billing security, ops reliability.

---

## Wave 2 — Accountant + Wave simplicity (days 15–45) ✅ Signed off 2026-08-27

**Goal:** A Malaysian bookkeeper can close a month; a new SME can send and collect on day one.

**Detailed plan:** [`2026-08-27-wave-2-accountant-wave-simplicity.md`](./2026-08-27-wave-2-accountant-wave-simplicity.md)  
**Commits:** `4460296` (core) · `c8bbb2c` (completion) · **375 tests green**

**Carryover into Wave 3 Task 0:** tax-code CRUD + line FK; full i18n on invoice/bill lists; period lock on all void/post routes.

| # | Deliverable | Beats |
|---|---|---|
| 7 | **Period lock** + reopen permission | AutoCount / Bukku month-end trust |
| 8 | **Tax-code master** (SR-8, ST-10, ES, ZRL); input SST → 1110, output → 2100; CN/DN in SST pack | Wave in MY; close to Bukku |
| 9 | **AR/AP aging** uses `remainingBalance` (CN + deposits) | Report honesty |
| 10 | **Document numbering** settings (prefix, next, FY reset) | AutoCount migration |
| 11 | **Public HTML invoice** (Pay / PDF / WhatsApp) | Wave customer UX |
| 12 | **Onboarding + MY:** day-1 checklist; Startup can record payment; `ms.json` for nav/invoices/bills; currency from `base_currency` | Wave onboarding; Bukku credibility |
| 13 | **Feature tests:** post invoice, pay, void, webhook reject | Safe velocity |

**Architecture notes:**

- Period lock: `accounting_periods` tenant table; middleware `EnsurePeriodOpen` on post/void/pay routes.
- Tax codes: `tax_codes` tenant table; line FK; SST report reads codes not `%`.
- Public invoice: signed route `/pay/invoice/{uuid}` (HTML), reuse Pay Now gateways.

**Beats:** Wave simplicity + Bukku bookkeeper expectations.

---

## Wave 3 — Bukku deal-breakers + firm moat (days 46–90) ✅ Signed off 2026-08-27

**Goal:** Honest claim: “better than Bukku for firms, better than Wave for Malaysian SMEs.”

**Detailed plan:** [`2026-08-27-wave-3-bukku-parity-practice-moat.md`](./2026-08-27-wave-3-bukku-parity-practice-moat.md)  
**Tests:** **409 passed** — bank rec, SST-02, receipt inbox, close pack, MyInvois vault, async provision

| # | Deliverable | Beats |
|---|---|---|
| 14 | **Bank rec v1** — CSV/PDF statement upload + suggest-match | Wave feeds; Bukku SmartRecon lite |
| 15 | **SST-02 export** from tax codes | Bukku SST filing |
| 16 | **Official receipt + payment voucher** PDFs | AutoCount collections |
| 17 | **Receipt inbox** — OCR jobs as a list (Digital Shoebox lite) | Bukku shoebox |
| 18 | **Practice close pack** + staff invites | AutoCount has no cloud firm desk (**moat**) |
| 19 | **MyInvois payload vault**; e-invoice on Growth (not only Corporate) | Bukku packaging |
| 20 | **Ops scale** — no `tenants:migrate` on every ECS boot; queue tenant provision | Many tenants |

**Architecture notes:**

- Bank rec: `bank_statements`, `bank_statement_lines`, match suggestions to `journal_items` on bank/cash accounts.
- Practice close pack: per-client checklist widget on Practice dashboard (unbilled, overdue AR, SST gaps, payroll remittance, year-end).
- Payload vault: `myinvois_submissions` table stores request/response JSON per document.

**Beats:** Bukku on operational MY accounting; AutoCount on firm workflow.

---

## Wave 4 — Depth + scale (days 91–180)

**Goal:** Cross the **8.0 book-of-record bar**; win trading SMEs and growing enterprises without becoming AutoCount ERP.

| # | Deliverable | Why now |
|---|---|---|
| 21 | **Inventory lite** — qty on hand, GRN/DO movement, weighted-avg COGS on invoice | Goods-flow UI exists; GL does not. AutoCount core buyer. |
| 22 | **Fixed assets lite** — register, straight-line depreciation, disposal journal | Bukku “depreciation in clicks”. Needs period lock. |
| 23 | **IAS 7 cash flow** + **cash vs accrual** P&L toggle | Wave “profit but no cash” story |
| 24 | **Budgets vs actual** v1 — by account and period | ROADMAP Phase 2; some AutoCount firms require it |
| 25 | **True multi-currency** — realized FX on payment; optional unrealized reval | Rate field exists today; engine does not |
| 26 | **Customer portal** v1 — login-free history, statements, saved pay method | After HTML pay page (Wave 2 #11) |
| 27 | **Comparative balance sheet / TB**; drill from P&L to source doc | Bukku report depth |
| 28 | **Employee payroll** — master, PCB/EPF files (beyond paste journal) | Compete with Bukku light payroll |
| 29 | **Observability** — Sentry, JSON logs with `tenant_id`, failed-job alerts | Scale safely |
| 30 | **Frontend consolidation** — invoices on shared document components; shrink AuthenticatedLayout | Velocity |

**Beats:** AutoCount on trading depth; Wave on reporting clarity; Bukku on report count (selectively, not 50-for-50).

---

## Wave dependency graph

```
Wave 1 (trust) ──► Wave 2 (accountant + UX)
                        │
                        ▼
                   Wave 3 (Bukku parity + practice moat)
                        │
                        ▼
                   Wave 4 (inventory, FA, FX, portal)
```

Wave 2 #7 (period lock) blocks Wave 4 #21–22.  
Wave 2 #8 (tax codes) blocks Wave 3 #15 (SST-02).  
Wave 1 #4 (scheduler) blocks Wave 3 #19 (renewals collecting).  
Wave 2 #11 (HTML invoice) blocks Wave 4 #26 (portal).

---

## Competitive positioning after each wave

| After | vs Wave | vs Bukku | vs AutoCount |
|---|---|---|---|
| Wave 1 | Still behind on bank rec | Still behind on SST/inventory | Still behind on stock |
| Wave 2 | **Ahead** on MY + practice; **match** pay UX | Close on tax; still no SmartRecon | **Ahead** on cloud practice |
| Wave 3 | **Clear win** for MY SME + firm | **Parity** on rec/SST; **win** on practice desk | **Win** on firm cloud; trading still gaps |
| Wave 4 | **Win** on depth + AI | **Win** firms; SME parity on stock | **Win** cloud; desktop ERP still deeper |

---

## What we protect (do not regress)

- Practice dual-desk (firm console, client switch, provision/invite)
- Document chain (estimate → SO → DO → invoice; PO → GRN → bill)
- MyInvois (std + consolidated + self-billed)
- Copilot + MY OCR (confirm-gated writes)
- Self-hosted + HMAC API
- Plan-honesty tests (`PlanPermissionAlignmentTest`)

---

## Execution order

1. ~~Wave 1~~ ✅ — [`2026-08-27-wave-1-trust-foundation.md`](./2026-08-27-wave-1-trust-foundation.md)
2. ~~Wave 2~~ ✅ — [`2026-08-27-wave-2-accountant-wave-simplicity.md`](./2026-08-27-wave-2-accountant-wave-simplicity.md)
3. ~~Wave 3~~ ✅ — [`2026-08-27-wave-3-bukku-parity-practice-moat.md`](./2026-08-27-wave-3-bukku-parity-practice-moat.md)
4. **Now:** Wave 4 — inventory lite, fixed assets, FX, portal (plan TBD)
5. Do not start Wave 4 until Wave 3 exit checklist signed off ✅

---

## Success metrics (per wave)

| Metric | Wave 1 | Wave 2 | Wave 3 | Wave 4 |
|---|---|---|---|---|
| Overall score | ≥ 6.6 | ≥ 7.4 | ≥ 7.8 | ≥ 8.5 |
| GL integrity score | ≥ 7 | ≥ 8 | ≥ 8.5 | ≥ 9 |
| PHPUnit Feature tests on money path | ≥ 5 | ≥ 15 | ≥ 25 | ≥ 40 |
| CI blocks bad deploy | Yes | Yes | Yes | Yes |
| Scheduler logs in prod | Yes | Yes | Yes | Yes |
| Firm viewer write blocked | Yes | Yes | Yes | Yes |
