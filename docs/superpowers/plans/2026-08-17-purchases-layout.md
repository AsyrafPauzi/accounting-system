# Purchases Layout Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle every simple Purchases (Expenses) create / index / show to match its Sales twin, including multi-line items via `PurchasesDocLines`.

**Architecture:** UI-only. Clone Sales Order / Debit Note / Recurring Invoice / AR deposit / Customer statement JSX. One new component `PurchasesDocLines` (SalesDocLines + expense account column). Pass `expenseAccounts` into SCN/SDN create. Recurring bill submit maps `unit_price` → `unit_amount`. No new routes.

**Tech Stack:** Laravel 12, Inertia React, existing tenant models.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-17-purchases-layout-design.md`
- Do **not** change `Bills/*`, `Bills/Batch`, `AccountsPayable/Index`, `Suppliers/*`
- Do **not** add batch PO, statement PDF/email, SCN/SDN edit, recurring-bill edit
- Subtitles are locked in the spec Copy section
- PHPUnit: `PHP_INI_SCAN_DIR= /opt/homebrew/bin/php -d auto_prepend_file= vendor/bin/phpunit …`
- Do not commit unless the user asks

## File map

| File | Role |
|------|------|
| Create: `resources/js/Components/PurchasesDocLines.jsx` | Shared line table |
| Modify: PO Create/Edit/Index/Show | Clone SO |
| Modify: GR Index/Show | Clone DO index; keep GR actions |
| Modify: RecurringBills Create/Index/Show | Clone recurring invoice |
| Modify: SCN/SDN Create/Index/Show | Clone CN/DN |
| Modify: ApDeposits Create/Index/Show | Clone AR deposits |
| Modify: SupplierStatements Index/Show | Clone customer statements (no email/PDF) |
| Modify: SCN/SDN controllers `create()` | Pass `expenseAccounts` |
| Modify: `app/Services/RecurringBillService.php` | Accept `unit_price` as `unit_amount` fallback |

---

### Task 1: PurchasesDocLines + recurring unit_price fallback

**Files:**
- Create: `resources/js/Components/PurchasesDocLines.jsx`
- Modify: `app/Services/RecurringBillService.php` (`syncItems`)
- Test: `tests/Unit/Purchases/PurchaseOrderCancelReturnTest.php` (keep green); add `tests/Unit/Purchases/RecurringBillItemMappingTest.php` only if you can construct items without DB. Prefer the one-line fallback and rely on existing unit tests.

**Interfaces:**
- Consumes: `SalesDocLines` chrome
- Produces: `blankPurchaseLine(accountCode)`, `purchaseLineAmount(item)`, default export `PurchasesDocLines({ items, onChange, products = [], expenseAccounts = [] })`
- Line shape: `{ description, quantity, unit_price, tax_rate, account_code, product_id }`

- [ ] **Step 1: Write `PurchasesDocLines.jsx`**

Copy `resources/js/Components/SalesDocLines.jsx` and add the account column. Exact file:

```jsx
import React from 'react';

const lineControl = 'w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta';
const lineNumber = `${lineControl} [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;

export function blankPurchaseLine(accountCode = '5000') {
    return { description: '', quantity: 1, unit_price: 0, tax_rate: 0, account_code: accountCode, product_id: null };
}

export function purchaseLineAmount(item) {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const tax = Number(item.tax_rate) || 0;
    const net = qty * price;
    return net + net * (tax / 100);
}

export default function PurchasesDocLines({ items, onChange, products = [], expenseAccounts = [] }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';
    const update = (index, patch) => {
        onChange(items.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const applyProduct = (index, id) => {
        const p = products.find((x) => String(x.id) === String(id));
        if (!p) return;
        update(index, {
            product_id: p.id,
            description: p.name,
            unit_price: p.unit_price,
            tax_rate: p.tax_rate ?? 0,
            account_code: p.account_code || items[index].account_code || defaultAccount,
        });
    };

    return (
        <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 overflow-hidden min-w-0">
            <div className="overflow-x-auto">
                <table className="w-full table-fixed text-left border-collapse">
                    <colgroup>
                        <col />
                        <col className="w-28" />
                        <col className="w-16" />
                        <col className="w-[5.5rem]" />
                        <col className="w-16" />
                        <col className="w-[6rem]" />
                        <col className="w-9" />
                    </colgroup>
                    <thead>
                        <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                            <th className="px-2 py-2">Description</th>
                            <th className="px-1 py-2">Account</th>
                            <th className="px-1 py-2 text-center">Qty</th>
                            <th className="px-1 py-2 text-right">Price</th>
                            <th className="px-1 py-2 text-center">Tax</th>
                            <th className="px-2 py-2 text-right">Total</th>
                            <th className="px-1 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border-warm">
                        {items.map((item, index) => (
                            <tr key={index} className="hover:bg-surface-alt/20">
                                <td className="px-2 py-2 align-top">
                                    <div className="flex items-center gap-1.5 min-w-0">
                                        <textarea
                                            value={item.description}
                                            onChange={(e) => update(index, { description: e.target.value })}
                                            placeholder="What is this line for?"
                                            rows={1}
                                            required
                                            className="flex-1 min-w-0 h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta resize-y"
                                        />
                                        {products.length > 0 && (
                                            <select
                                                value=""
                                                onChange={(e) => { applyProduct(index, e.target.value); e.target.value = ''; }}
                                                className="shrink-0 w-[4.25rem] h-8 border border-border-warm rounded-lg text-[10px] font-medium text-ink-muted bg-cream/50 px-1"
                                            >
                                                <option value="">+ Pick</option>
                                                {products.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <select
                                        value={item.account_code || defaultAccount}
                                        onChange={(e) => update(index, { account_code: e.target.value })}
                                        className={lineControl}
                                        required
                                    >
                                        {expenseAccounts.map((a) => (
                                            <option key={a.code} value={a.code}>{a.code}</option>
                                        ))}
                                    </select>
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <input type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => update(index, { quantity: e.target.value })} className={`${lineNumber} text-center font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <input type="number" step="0.01" value={item.unit_price} onChange={(e) => update(index, { unit_price: e.target.value })} className={`${lineNumber} text-right font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <select value={item.tax_rate} onChange={(e) => update(index, { tax_rate: e.target.value })} className={lineControl}>
                                        <option value="0">0%</option>
                                        <option value="6">6%</option>
                                        <option value="8">8%</option>
                                        <option value="16">16%</option>
                                    </select>
                                </td>
                                <td className="px-2 py-2 align-middle">
                                    <div className="h-8 flex items-center justify-end text-xs font-semibold font-mono tabular-nums">
                                        {purchaseLineAmount(item).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                    </div>
                                </td>
                                <td className="px-1 py-2 align-top">
                                    {items.length > 1 && (
                                        <button type="button" className="h-8 w-8 text-ink-muted hover:text-terracotta" onClick={() => onChange(items.filter((_, i) => i !== index))}>×</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="px-3 py-2 border-t border-border-warm bg-cream/40 flex justify-between items-center">
                <button
                    type="button"
                    className="text-xs font-semibold text-terracotta"
                    onClick={() => onChange([...items, blankPurchaseLine(defaultAccount)])}
                >
                    + Add line
                </button>
                <p className="text-sm font-semibold font-mono tabular-nums">
                    {items.reduce((sum, row) => sum + purchaseLineAmount(row), 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                </p>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Recurring bill `syncItems` accepts `unit_price`**

In `app/Services/RecurringBillService.php` `syncItems`, change the unit line to:

```php
$unit = (float) ($item['unit_amount'] ?? $item['unit_price'] ?? $item['amount'] ?? 0);
```

- [ ] **Step 3: Run existing purchases tests**

```bash
PHP_INI_SCAN_DIR= /opt/homebrew/bin/php -d auto_prepend_file= vendor/bin/phpunit tests/Unit/Purchases tests/Feature/Purchases
```

Expected: all pass.

---

### Task 2: Purchase order create + edit

**Files:**
- Modify: `resources/js/Pages/PurchaseOrders/Create.jsx`
- Modify: `resources/js/Pages/PurchaseOrders/Edit.jsx`

**Interfaces:**
- Consumes: `blankPurchaseLine`, `PurchasesDocLines`
- Produces: create posts `items[]` with `unit_price` (already validated by `PurchaseOrderController::store`)

- [ ] **Step 1: Replace Create.jsx** with Sales Order create, supplier instead of customer.

```jsx
import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], products = [], expenseAccounts = [], next_number }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';
    const { data, setData, post, processing } = useForm({
        po_number: next_number,
        supplier_id: '',
        issue_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        notes: '',
        items: [blankPurchaseLine(defaultAccount)],
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New purchase order</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Save the order, then receive goods or convert to a bill</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('purchase-orders.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="po-create-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Save purchase order'}
                    </button>
                </div>
            </div>
        }>
            <Head title="New purchase order" />
            <form id="po-create-form" className="space-y-4 pb-8 min-w-0" onSubmit={(e) => { e.preventDefault(); post(route('purchase-orders.store')); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{data.po_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Supplier</label>
                            <select className={inputClass} value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} required>
                                <option value="">Select supplier…</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Expected date</label>
                            <input type="date" className={inputClass} value={data.expected_date} onChange={(e) => setData('expected_date', e.target.value)} />
                        </div>
                    </div>
                </div>
                <PurchasesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} expenseAccounts={expenseAccounts} />
                <textarea className={inputClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Notes on the PDF (optional)" />
            </form>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Restyle Edit.jsx**

Keep lock_reason, `put(route('purchase-orders.update', order.id))`, disabled when `!editable`. Header: title `Edit {order.po_number}`, Cancel + Save changes. Same document card + `PurchasesDocLines`. Map existing items:

```js
items: (order.items || []).map((i) => ({
    id: i.id,
    description: i.description,
    quantity: i.quantity,
    unit_price: i.unit_price,
    tax_rate: i.tax_rate ?? 0,
    account_code: i.account_code || expenseAccounts[0]?.code || '5000',
    product_id: i.product_id || null,
}))
```

Pass `disabled` into inputs when `!editable`. Do not add Batch.

---

### Task 3: Purchase order index + show

**Files:**
- Modify: `resources/js/Pages/PurchaseOrders/Index.jsx`
- Modify: `resources/js/Pages/PurchaseOrders/Show.jsx`

- [ ] **Step 1: Index — clone `SalesOrders/Index.jsx`**

Open `resources/js/Pages/SalesOrders/Index.jsx`. Copy structure. Substitutions:
- Title `Purchase Orders`
- Subtitle: `Confirm the order, then receive goods and convert to a bill from the same document`
- New link: `route('purchase-orders.create')` label `New purchase order` — **omit Batch**
- Search PO # or supplier
- Columns: Purchase order, Supplier, Status, Total, Open
- Status badges: `draft`, `confirmed`, `partially_received`, `received`, `billed`, `cancelled` (use forest/mustard/terracotta/muted like SO)
- Row link: `purchase-orders.show`
- KPI chip: `{orders?.total ?? rows.length}`
- Permission: `bills.create` (same as current PO index)

- [ ] **Step 2: Show — keep all actions, match SO/Bills header**

Keep: Edit, PDF, Email, Cancel, Convert to bill, Create goods receipt, qty inputs, trail, GR/bill lists. Change wrapper from cramped `max-w-4xl mx-auto p-6` to `space-y-4 min-w-0` (AR deposit show style). Header: title + `supplier · status` + button row using the same `btn` / `primary` classes already on the page. Line table: thead uppercase tracking-widest on cream.

---

### Task 4: Goods receipts index + show

**Files:**
- Modify: `resources/js/Pages/GoodsReceipts/Index.jsx`
- Modify: `resources/js/Pages/GoodsReceipts/Show.jsx`

- [ ] **Step 1: Index — clone `DeliveryOrders/Index.jsx`**

- Title `Goods Receipts`
- Subtitle: `Created from a purchase order. Open a receipt to return it or convert to a bill.`
- **No New button**
- Search GRN # or supplier
- Columns: GRN, Supplier, Status, Open
- Status badges: `received`, `billed`, `cancelled`
- KPI: count of receipts

- [ ] **Step 2: Show**

Keep PDF, Email, Return, Convert to bill, trail. Same spacing as PO show (`space-y-4 min-w-0`). Labeled line list in a card.

---

### Task 5: Recurring bills

**Files:**
- Modify: `resources/js/Pages/RecurringBills/Create.jsx`
- Modify: `resources/js/Pages/RecurringBills/Index.jsx`
- Modify: `resources/js/Pages/RecurringBills/Show.jsx`

- [ ] **Step 1: Create**

Header: `New recurring bill` / `Set it up once. Each cycle creates a draft bill.` Cancel + Save recurring bill.

Form fields (already accepted by `RecurringBillController::store` / service): `name`, `supplier_id`, `cadence`, `interval`, `start_date`, `payment_terms_days`, `auto_post`, `items`.

On submit, map lines so `amount` validates:

```js
post(route('recurring-bills.store'), {
    transform: (data) => ({
        ...data,
        items: data.items.map((i) => ({
            account_code: i.account_code,
            description: i.description,
            quantity: i.quantity,
            unit_amount: i.unit_price,
            unit_price: i.unit_price,
            amount: (Number(i.quantity) || 0) * (Number(i.unit_price) || 0),
        })),
    }),
});
```

(If Inertia `transform` is awkward, compute `amount`/`unit_amount` in `onSubmit` via `setData` then `post`.)

Document card: name, supplier, cadence select, start date, payment terms, auto-post checkbox. Then `PurchasesDocLines` without products (pass `expenseAccounts` only).

- [ ] **Step 2: Index — clone RecurringInvoices/Index chrome, client-side filter**

Do not add destroy/search query params (no new routes). Client-filter `templates` by name/supplier. Header + New. Table: name, supplier, cadence, next run (`formatDate`), Active/Paused badge, Run now. Empty: `No recurring bills yet.`

- [ ] **Step 3: Show**

`space-y-4 min-w-0`. Title, supplier · cadence · next date. Pause/Resume + Run now. Card of lines. Keep existing `router.post` targets.

---

### Task 6: Supplier credit + debit notes

**Files:**
- Modify: `app/Http/Controllers/SupplierCreditNoteController.php` `create()`
- Modify: `app/Http/Controllers/SupplierDebitNoteController.php` `create()`
- Modify: `resources/js/Pages/SupplierCreditNotes/Create.jsx`
- Modify: `resources/js/Pages/SupplierDebitNotes/Create.jsx`
- Modify: `resources/js/Pages/SupplierCreditNotes/Index.jsx`
- Modify: `resources/js/Pages/SupplierDebitNotes/Index.jsx`
- Modify: `resources/js/Pages/SupplierCreditNotes/Show.jsx`
- Modify: `resources/js/Pages/SupplierDebitNotes/Show.jsx`

- [ ] **Step 1: Pass expense accounts**

Both `create()` methods already import `Account`. Add:

```php
'expenseAccounts' => Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']),
```

- [ ] **Step 2: SCN Create — clone `CreditNotes/CreateStandalone.jsx`**

- Number: `data.scn_number` (mono cream)
- Supplier select, or cream box + `Against {bill.bill_number}` when `bill` is set
- Issue date if the store already accepts `issue_date` (it does)
- `PurchasesDocLines` with `expenseAccounts`; seed lines from `bill.items` mapping `unit_amount` → `unit_price`
- Header: `Issue supplier credit note` / `Reduce what you owe the supplier`
- Primary: `Issue credit note`
- Hidden/keep `bill_id` in form data

- [ ] **Step 3: SDN Create — clone `DebitNotes/Create.jsx`**

Same as SCN with `sdn_number`, route `supplier-debit-notes.store`, subtitle `Additional charges from the supplier`, button `Issue debit note`.

- [ ] **Step 4: Indexes — clone `CreditNotes/Index.jsx`**

KPI count, search number/supplier, columns Number / Supplier / Amount / Open. New button. SCN mustard is optional; terracotta is fine for both to match Purchases.

- [ ] **Step 5: Shows**

Keep apply / refund / void / PDF. Labeled inputs (`labelClass` + `inputClass`). Header like Bills show. Do not add Edit.

---

### Task 7: AP deposits

**Files:**
- Modify: `resources/js/Pages/ApDeposits/Create.jsx`
- Modify: `resources/js/Pages/ApDeposits/Index.jsx`
- Modify: `resources/js/Pages/ApDeposits/Show.jsx`

- [ ] **Step 1: Create — clone `ArDeposits/Create.jsx`**

Keep `router.get(route('ap-deposits.create'), { supplier_id })` for open bills. Header: `New supplier payment` / `Pay the supplier once, then allocate across open bills`. Cancel + Save payment. Labeled grid: supplier, amount, date, bank, reference, notes. Allocate table with Fill oldest first. Leftover prepaid copy stays (1300).

- [ ] **Step 2: Index — clone `ArDeposits/Index.jsx`**

Title `Supplier deposits`. Subtitle: `One bank payment, then knock off bills. Leftover stays as a prepaid deposit.` New payment. KPI: count + unapplied. Search reference/supplier. Columns: Number, Supplier, Date, Unapplied, Open.

- [ ] **Step 3: Show**

Clone `ArDeposits/Show.jsx` layout. **Omit PDF / Email / Edit** (AP has no those routes). Keep apply-to-bill form with labels.

---

### Task 8: Supplier statements

**Files:**
- Modify: `resources/js/Pages/SupplierStatements/Index.jsx`
- Modify: `resources/js/Pages/SupplierStatements/Show.jsx`

- [ ] **Step 1: Index — clone `CustomerStatements/Index.jsx`**

Title `Supplier Statements`. Subtitle: `Pick a supplier to see bills and payments over a date range`. Search by name. Columns: Supplier, Outstanding, Statement (View statement button). Empty state copy: add suppliers under Purchases → Suppliers.

Keep existing `router.get(route('supplier-statements.index'), { search })`.

- [ ] **Step 2: Show — clone `CustomerStatements/Show.jsx` chrome**

Date range + Apply. Three summary cards: Opening, Charges, Closing. Ledger table unchanged. **No email, preview, or PDF buttons.** Back link to statements index.

---

### Task 9: Verify

**Files:**
- Test: `tests/Feature/Purchases/PurchasesPolishRoutesTest.php` (unchanged routes)
- Test: `tests/Unit/Purchases/PurchaseOrderCancelReturnTest.php`

- [ ] **Step 1: Run PHPUnit**

```bash
PHP_INI_SCAN_DIR= /opt/homebrew/bin/php -d auto_prepend_file= vendor/bin/phpunit tests/Unit/Purchases tests/Feature/Purchases
```

Expected: pass.

- [ ] **Step 2: Manual click-through** (http://127.0.0.1:8011, testdemo)

- [ ] PO create: two lines, save, show has both
- [ ] PO edit still locks after receive
- [ ] PO / GR indexes: search + badges
- [ ] Recurring bill: two lines, save, Run now still works
- [ ] SCN / SDN: labeled create, two lines, Issue
- [ ] AP deposit: allocate table labeled, leftover shown
- [ ] Supplier statements: search + date range
- [ ] Bills create / AP aging unchanged

---

## Spec coverage

| Spec item | Task |
|-----------|------|
| PurchasesDocLines | 1 |
| PO create/edit | 2 |
| PO index/show | 3 |
| GR index/show | 4 |
| Recurring bill create/index/show | 5 |
| SCN/SDN create/index/show | 6 |
| AP deposit create/index/show | 7 |
| Supplier statements | 8 |
| Tests + Bills/AP unchanged | 9 |
| No new routes / no FormShell | Global constraints |
