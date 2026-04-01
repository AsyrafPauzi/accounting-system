# Product roadmap

Priorities (from product direction): **core accounting depth** and **reporting** are must-haves; **LHDN e-Invoice** when the company is **Malaysia-only**; **process and integrations** are out of scope for now; **security and operations** are handled separately; **product and scale** foundations are worth doing early.

---

## Phase 0 — Foundations (do early, small)

- Feature flags per tenant for risky modules (new GL rules, new reports, etc.).
- Structured logging and visibility into queues / failed jobs (retries, backoff).
- Clear staging vs production workflow and migration discipline.

---

## Phase 1 — Core accounting depth (must, first major build)

- **Accounting periods** and **period lock** (no posting or material edits in closed periods; controlled reopen).
- **Tax / SST (Malaysia)** as a first-class model: tax codes, effective dates, rounding rules, and correct GL posting—not only a percentage on invoice lines.
- **GL integrity**: balanced journal entries, clear reversal semantics, chart-of-accounts protections (e.g. system accounts).
- **Unified audit trail** for money-moving data: invoices, bills, payments, journal entries, and COA changes (who / when / what changed).

---

## Phase 2 — Reporting and analytics (must, after Phase 1)

- **Trial balance** that reconciles to the general ledger and financial statements.
- **Drill-down** from report lines to transactions and source documents.
- **Comparative periods** (e.g. prior period, YoY) where applicable.
- **Budget vs actual** (even a first version: budgets by account and period).
- **Dashboards / KPIs** with defined, documented formulas.

---

## Phase 3 — Malaysia / LHDN e-Invoice (gated)

- Enable **MyInvois / e-Invoice** features only when the tenant’s company profile is **Malaysia** and required fields (e.g. TIN) are present.
- End-to-end flow: validate → submit → store LHDN references and statuses → handle retries and rejected documents.
- Full audit history of payloads and responses per document.

---

## Phase 4 — Process and integrations (later)

Deferred until explicitly prioritized. Examples include approval workflows, bank feeds, and ERP or payment gateway integrations.

---

## Optional modules (when the business needs them)

- **Multi-currency** with functional currency, rates, and FX handling.
- **Bank reconciliation** (statement import, matching, exceptions).
- **Fixed assets** and depreciation.
- **Dimensional reporting** (e.g. cost centers, projects) on journal lines.

---

## Suggested execution order

**Phase 0 → Phase 1 → Phase 2 → Phase 3**, then **Phase 4** when ready.

Optional items can slot in after Phase 1 stabilizes, or in parallel if resourcing allows.
