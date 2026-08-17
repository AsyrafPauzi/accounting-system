import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-3 text-sm font-medium text-ink bg-surface focus:ring-2 focus:ring-terracotta focus:border-terracotta';

const blankRow = () => ({
    supplier_id: '',
    purchase_kind: 'credit',
    description: '',
    quantity: 1,
    unit_amount: '',
    account_code: '',
    bill_date: new Date().toISOString().split('T')[0],
    bank_account_code: '',
});

function lineTotal(row) {
    return (Number(row.quantity) || 0) * (Number(row.unit_amount) || 0);
}

export default function Batch({ auth, suppliers = [], expenseAccounts = [], bankAccounts = [] }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';
    const defaultBank = bankAccounts[0]?.value || '';
    const { data, setData, post, processing, errors } = useForm({
        rows: [{ ...blankRow(), account_code: defaultAccount, bank_account_code: defaultBank }],
    });

    const updateRow = (index, key, value) => {
        setData('rows', data.rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    };

    const addRow = () => {
        const last = data.rows[data.rows.length - 1];
        setData('rows', [...data.rows, {
            ...blankRow(),
            supplier_id: last?.supplier_id || '',
            purchase_kind: last?.purchase_kind || 'credit',
            account_code: last?.account_code || defaultAccount,
            bank_account_code: last?.bank_account_code || defaultBank,
        }]);
    };

    const removeRow = (index) => {
        if (data.rows.length <= 1) {
            setData('rows', [{ ...blankRow(), account_code: defaultAccount, bank_account_code: defaultBank }]);
            return;
        }
        setData('rows', data.rows.filter((_, i) => i !== index));
    };

    const grandTotal = data.rows.reduce((sum, row) => sum + lineTotal(row), 0);
    const readyCount = data.rows.filter((r) => r.supplier_id && r.description && Number(r.unit_amount) > 0 && (r.purchase_kind !== 'cash' || r.bank_account_code)).length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Batch bills</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Each row creates one bill. Cash rows are paid on save.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('bills.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                        <button type="submit" form="bill-batch-form" disabled={processing || readyCount === 0} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                            {processing ? 'Creating…' : readyCount === 1 ? 'Create 1 bill' : `Create ${readyCount} bills`}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Batch bills" />
            <form id="bill-batch-form" className="space-y-4 pb-8" onSubmit={(e) => { e.preventDefault(); post(route('bills.batch.store')); }}>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-xl">
                    <div className="bg-surface rounded-2xl border p-4"><p className="text-[10px] font-semibold uppercase text-ink-muted">Rows</p><p className="text-xl font-display mt-1">{data.rows.length}</p></div>
                    <div className="bg-terracotta text-white rounded-2xl p-4"><p className="text-[10px] font-semibold uppercase opacity-90">Ready</p><p className="text-xl font-display mt-1">{readyCount}</p></div>
                    <div className="bg-surface rounded-2xl border p-4"><p className="text-[10px] font-semibold uppercase text-ink-muted">Est. total</p><p className="text-xl font-display mt-1">{formatCurrency(grandTotal, 'MYR')}</p></div>
                </div>
                {errors.rows && <div className="rounded-xl border border-terracotta/30 bg-terracotta/5 px-4 py-3 text-sm text-terracotta">{typeof errors.rows === 'string' ? errors.rows : 'Check each row and try again.'}</div>}
                <div className="space-y-3">
                    {data.rows.map((row, index) => (
                        <div key={index} className="bg-surface rounded-2xl border p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
                            <select className={inputClass} value={row.supplier_id} onChange={(e) => updateRow(index, 'supplier_id', e.target.value)}>
                                <option value="">Supplier / claimant</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            <select className={inputClass} value={row.purchase_kind} onChange={(e) => updateRow(index, 'purchase_kind', e.target.value)}>
                                <option value="credit">Credit</option>
                                <option value="cash">Cash</option>
                                <option value="claim">Claim</option>
                            </select>
                            <input className={`${inputClass} md:col-span-2`} placeholder="Description" value={row.description} onChange={(e) => updateRow(index, 'description', e.target.value)} />
                            <input type="number" min="0.01" step="0.01" className={inputClass} value={row.quantity} onChange={(e) => updateRow(index, 'quantity', e.target.value)} />
                            <input type="number" min="0" step="0.01" className={inputClass} placeholder="Amount" value={row.unit_amount} onChange={(e) => updateRow(index, 'unit_amount', e.target.value)} />
                            <select className={inputClass} value={row.account_code} onChange={(e) => updateRow(index, 'account_code', e.target.value)}>
                                {expenseAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                            </select>
                            {row.purchase_kind === 'cash' && (
                                <select className={`${inputClass} md:col-span-2`} value={row.bank_account_code} onChange={(e) => updateRow(index, 'bank_account_code', e.target.value)}>
                                    {(bankAccounts || []).map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                                </select>
                            )}
                            <button type="button" className="text-sm text-ink-muted hover:text-terracotta" onClick={() => removeRow(index)}>Remove</button>
                        </div>
                    ))}
                </div>
                <button type="button" onClick={addRow} className="text-sm font-semibold text-terracotta">+ Add row</button>
            </form>
        </AuthenticatedLayout>
    );
}
