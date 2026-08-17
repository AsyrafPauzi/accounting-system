import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], expenseAccounts = [], bill = null, next_number }) {
    const { data, setData, transform, post, processing, errors } = useForm({
        supplier_id: bill?.supplier_id || '',
        bill_id: bill?.id ?? null,
        scn_number: next_number,
        issue_date: new Date().toISOString().split('T')[0],
        reason_description: '',
        notes: '',
        items: bill?.items?.length
            ? bill.items.map((i) => ({
                description: i.description,
                quantity: i.quantity,
                unit_price: i.unit_amount,
                tax_rate: i.tax_rate ?? 0,
                account_code: i.account_code,
                product_id: i.product_id || null,
            }))
            : [blankPurchaseLine(expenseAccounts[0]?.code)],
    });

    const submit = (event) => {
        event.preventDefault();
        transform((formData) => ({
            ...formData,
            bill_id: bill ? formData.bill_id : null,
        }));
        post(route('supplier-credit-notes.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Issue supplier credit note</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Reduce what you owe the supplier</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('supplier-credit-notes.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="scn-create-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Issue credit note'}
                    </button>
                </div>
            </div>
        }>
            <Head title="Issue supplier credit note" />
            <form id="scn-create-form" className="space-y-4 pb-8 min-w-0" onSubmit={submit}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{data.scn_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Supplier</label>
                            {bill ? (
                                <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm bg-cream">
                                    <span className="font-semibold">{bill.supplier?.name}</span>
                                    <span className="text-ink-muted"> · Against {bill.bill_number}</span>
                                </div>
                            ) : (
                                <select className={inputClass} value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} required>
                                    <option value="">Select supplier…</option>
                                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            )}
                            {errors.supplier_id && <p className="mt-1 text-xs text-terracotta">{errors.supplier_id}</p>}
                            {errors.bill_id && <p className="mt-1 text-xs text-terracotta">{errors.bill_id}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} required />
                        </div>
                        <div className="md:col-span-4">
                            <label className={labelClass}>Reason (optional)</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} placeholder="Why is this credit being issued?" />
                        </div>
                    </div>
                </div>
                <PurchasesDocLines items={data.items} onChange={(items) => setData('items', items)} expenseAccounts={expenseAccounts} />
                {errors.items && <p className="text-xs text-terracotta">{errors.items}</p>}
                <div>
                    <label className={labelClass}>Notes (optional)</label>
                    <textarea className={inputClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Notes on the PDF (optional)" />
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
