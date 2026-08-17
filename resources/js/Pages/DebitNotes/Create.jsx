import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

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
                discount_amount: i.discount_amount || 0,
                product_id: i.product_id || null,
            }))
            : [blankSalesLine()],
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('debit-notes.index')}
                    title="Issue debit note"
                    subtitle={invoice ? `Against ${invoice.invoice_number}` : 'Standalone extra charge — posts to the ledger on save'}
                    formId="dn-create-form"
                    processing={processing}
                    submitLabel="Issue debit note"
                />
            }
        >
            <Head title="Issue debit note" />
            <form id="dn-create-form" className="space-y-6 pb-12 min-w-0" onSubmit={(e) => { e.preventDefault(); post(route('debit-notes.store')); }}>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Debit note details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Number</label>
                            <div className={inputReadonlyClass}>{data.dn_number}</div>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Customer</label>
                            {invoice ? (
                                <div className={`${inputReadonlyClass} text-ink font-sans font-medium`}>{invoice.customer?.name || invoice.customer_name}</div>
                            ) : (
                                <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required>
                                    <option value="">Select customer...</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            )}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} />
                        </div>
                        {invoice && (
                            <div className="md:col-span-2 min-w-0">
                                <label className={labelClass}>Related invoice</label>
                                <Link href={route('invoices.show', invoice.id)} className="h-11 flex items-center text-sm font-semibold text-terracotta hover:underline">{invoice.invoice_number}</Link>
                            </div>
                        )}
                        <div className={invoice ? 'md:col-span-2 min-w-0' : 'md:col-span-4 min-w-0'}>
                            <label className={labelClass}>Reason (optional)</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} placeholder="Why is extra being charged?" />
                        </div>
                    </div>
                </div>

                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} products={products} />

                <DocumentFormNotesTotals
                    bannerTitle="Posts on save"
                    bannerText="Debit notes post to the ledger immediately. Use them for extra charges after the original invoice."
                    notesValue={data.customer_notes}
                    onNotesChange={(value) => setData('customer_notes', value)}
                    items={data.items}
                />
            </form>
        </AuthenticatedLayout>
    );
}
