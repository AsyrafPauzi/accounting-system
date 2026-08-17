import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SalesDocLines from '@/Components/SalesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Edit({ auth, debitNote, editable = true, lock_reason = null, products = [] }) {
    const { data, setData, put, processing } = useForm({
        issue_date: debitNote.issue_date?.slice?.(0, 10) || debitNote.issue_date || '',
        reason_description: debitNote.reason_description || '',
        customer_notes: debitNote.customer_notes || '',
        items: (debitNote.items || []).map((i) => ({
            id: i.id,
            description: i.description,
            quantity: i.quantity,
            unit_price: i.unit_price,
            tax_rate: i.tax_rate ?? 0,
            product_id: i.product_id || null,
            discount_amount: i.discount_amount || 0,
        })),
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit {debitNote.dn_number}</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">{debitNote.customer?.name}</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('debit-notes.show', debitNote.id)} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    {editable && (
                        <button type="submit" form="dn-edit-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    )}
                </div>
            </div>
        }>
            <Head title={`Edit ${debitNote.dn_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <form id="dn-edit-form" className="space-y-4 pb-8 min-w-0" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('debit-notes.update', debitNote.id)); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono text-terracotta bg-cream">{debitNote.dn_number}</div>
                        </div>
                        <div>
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Reason</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} disabled={!editable} />
                <textarea className={inputClass} rows={2} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} placeholder="Notes on the PDF (optional)" disabled={!editable} />
            </form>
        </AuthenticatedLayout>
    );
}
