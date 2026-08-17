import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function CreateStandalone({ auth, customers = [], next_number, lhdn_reasons = [] }) {
    const { data, setData, post, processing } = useForm({
        customer_id: '',
        cn_number: next_number,
        reason_code: '02',
        reason_description: '',
        items: [blankSalesLine()],
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Standalone credit note</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Issue a credit without tying it to a specific invoice first</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('credit-notes.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="cn-standalone-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-mustard disabled:opacity-50">
                        {processing ? 'Saving…' : 'Issue credit note'}
                    </button>
                </div>
            </div>
        }>
            <Head title="Standalone credit note" />
            <form id="cn-standalone-form" onSubmit={(e) => { e.preventDefault(); post(route('credit-notes.store')); }} className="space-y-4 min-w-0">
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className={labelClass}>Number</label>
                            <div className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono bg-cream">{data.cn_number}</div>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            <select className={inputClass} value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)} required>
                                <option value="">Select customer…</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Reason</label>
                            <select className={inputClass} value={data.reason_code} onChange={(e) => setData('reason_code', e.target.value)}>
                                {lhdn_reasons.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </select>
                        </div>
                    </div>
                </div>
                <SalesDocLines items={data.items} onChange={(items) => setData('items', items)} />
            </form>
        </AuthenticatedLayout>
    );
}
