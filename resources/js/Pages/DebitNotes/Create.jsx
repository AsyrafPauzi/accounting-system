import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, customers = [], products = [], invoice = null, next_number }) {
    const { data, setData, post, processing } = useForm({
        customer_id: invoice?.customer_id || '',
        invoice_id: invoice?.id || '',
        dn_number: next_number,
        issue_date: new Date().toISOString().split('T')[0],
        reason_description: '',
        customer_notes: '',
        items: invoice?.items?.length
            ? invoice.items.map((i) => ({
                description: i.description,
                quantity: i.quantity,
                unit_price: i.unit_price,
                tax_rate: i.tax_rate ?? 8,
                product_id: i.product_id || null,
            }))
            : [blankSalesLine()],
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Issue debit note</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">
                        {invoice ? `Against ${invoice.invoice_number}` : 'Standalone extra charge — posts to the ledger on save'}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('debit-notes.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="dn-create-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Issue debit note'}
                    </button>
                </div>
            </div>
        }>
            <Head title="Issue debit note" />
            <form id="dn-create-form" className="space-y-4 pb-8 min-w-0" onSubmit={(e) => { e.preventDefault(); post(route('debit-notes.store')); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{data.dn_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            {invoice ? (
                                <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm bg-cream">{invoice.customer?.name || invoice.customer_name}</div>
                            ) : (
                                <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required>
                                    <option value="">Select customer…</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} />
                        </div>
                        {invoice && (
                            <div className="md:col-span-2">
                                <label className={labelClass}>Related invoice</label>
                                <Link href={route('invoices.show', invoice.id)} className="block text-sm font-semibold text-terracotta hover:underline py-2.5">{invoice.invoice_number}</Link>
                            </div>
                        )}
                        <div className={invoice ? 'md:col-span-2' : 'md:col-span-4'}>
                            <label className={labelClass}>Reason (optional)</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} placeholder="Why is extra being charged?" />
                        </div>
                    </div>
                </div>

                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} />

                <textarea className={inputClass} rows={2} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} placeholder="Notes on the PDF (optional)" />
            </form>
        </AuthenticatedLayout>
    );
}
