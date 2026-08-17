import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, customers = [], products = [], next_number, customer_id }) {
    const { data, setData, post, processing } = useForm({
        so_number: next_number,
        customer_id: customer_id || '',
        issue_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        customer_notes: '',
        items: [blankSalesLine()],
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New sales order</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Save the order, then create a delivery order or convert to an invoice</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('sales-orders.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="so-create-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Save sales order'}
                    </button>
                </div>
            </div>
        }>
            <Head title="New sales order" />
            <form id="so-create-form" className="space-y-4 pb-8 min-w-0" onSubmit={(e) => { e.preventDefault(); post(route('sales-orders.store')); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{data.so_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required>
                                <option value="">Select customer…</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
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
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} />
                <textarea className={inputClass} rows={2} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} placeholder="Notes on the PDF (optional)" />
            </form>
        </AuthenticatedLayout>
    );
}
