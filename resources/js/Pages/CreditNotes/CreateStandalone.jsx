import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function CreateStandalone({ auth, customers = [], next_number, lhdn_reasons = [] }) {
    const { data, setData, post, processing } = useForm({
        customer_id: '',
        cn_number: next_number,
        reason_code: '02',
        reason_description: '',
        customer_notes: '',
        items: [blankSalesLine()],
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('credit-notes.index')}
                    title="Standalone credit note"
                    subtitle="Issue a credit without tying it to a specific invoice first"
                    formId="cn-standalone-form"
                    processing={processing}
                    submitLabel="Issue credit note"
                    accent="mustard"
                />
            }
        >
            <Head title="Standalone credit note" />
            <form id="cn-standalone-form" onSubmit={(e) => { e.preventDefault(); post(route('credit-notes.store')); }} className="space-y-6 pb-12 min-w-0">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Credit note details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Number</label>
                            <div className={inputReadonlyClass}>{data.cn_number}</div>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Customer</label>
                            <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required>
                                <option value="">Select customer...</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Reason</label>
                            <select className={inputClass} value={data.reason_code} onChange={(e) => setData('reason_code', e.target.value)}>
                                {lhdn_reasons.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </select>
                        </div>
                    </div>
                </div>
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} />
                <DocumentFormNotesTotals
                    bannerTitle="Standalone credit"
                    bannerText="This credit is not tied to a specific invoice. Apply it later from the customer’s open invoices."
                    notesValue={data.customer_notes}
                    onNotesChange={(value) => setData('customer_notes', value)}
                    items={data.items}
                />
            </form>
        </AuthenticatedLayout>
    );
}
