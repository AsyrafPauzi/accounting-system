import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SalesDocLines from '@/Components/SalesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

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
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('debit-notes.show', debitNote.id)}
                    title={`Edit ${debitNote.dn_number}`}
                    subtitle={debitNote.customer?.name}
                    formId="dn-edit-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={editable}
                />
            }
        >
            <Head title={`Edit ${debitNote.dn_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <form id="dn-edit-form" className="space-y-6 pb-12 min-w-0" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('debit-notes.update', debitNote.id)); }}>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Debit note details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Number</label>
                            <div className={inputReadonlyClass}>{debitNote.dn_number}</div>
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Reason</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} disabled={!editable} />
                <DocumentFormNotesTotals
                    notesValue={data.customer_notes}
                    onNotesChange={(value) => setData('customer_notes', value)}
                    notesDisabled={!editable}
                    items={data.items}
                />
            </form>
        </AuthenticatedLayout>
    );
}
