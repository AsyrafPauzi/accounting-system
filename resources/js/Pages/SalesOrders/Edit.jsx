import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SalesDocLines from '@/Components/SalesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Edit({ auth, order, editable = true, lock_reason = null, customers = [], products = [] }) {
    const { data, setData, put, processing } = useForm({
        customer_id: order.customer_id || '',
        issue_date: order.issue_date?.slice?.(0, 10) || order.issue_date || '',
        expected_date: order.expected_date?.slice?.(0, 10) || order.expected_date || '',
        customer_notes: order.customer_notes || '',
        items: (order.items || []).map((i) => ({
            id: i.id,
            description: i.description,
            quantity: i.quantity,
            unit_price: i.unit_price,
            tax_rate: i.tax_rate ?? 0,
            product_id: i.product_id || null,
            account_code: i.account_code || null,
            discount_amount: i.discount_amount || 0,
            qty_delivered: i.qty_delivered || 0,
        })),
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit {order.so_number}</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Line qty cannot go below already delivered</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('sales-orders.show', order.id)} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    {editable && (
                        <button type="submit" form="so-edit-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    )}
                </div>
            </div>
        }>
            <Head title={`Edit ${order.so_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <form id="so-edit-form" className="space-y-4 pb-8 min-w-0" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('sales-orders.update', order.id)); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{order.so_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required disabled={!editable}>
                                <option value="">Select customer…</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div>
                            <label className={labelClass}>Expected date</label>
                            <input type="date" className={inputClass} value={data.expected_date} onChange={(e) => setData('expected_date', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} disabled={!editable} />
                <textarea className={inputClass} rows={2} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} placeholder="Notes on the PDF (optional)" disabled={!editable} />
            </form>
        </AuthenticatedLayout>
    );
}
