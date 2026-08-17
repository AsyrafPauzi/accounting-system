import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function Create({ auth, suppliers = [], expenseAccounts = [] }) {
    const form = useForm({
        name: '',
        supplier_id: '',
        cadence: 'monthly',
        interval: 1,
        start_date: new Date().toISOString().split('T')[0],
        payment_terms_days: 30,
        auto_post: false,
        notes: '',
        items: [blankPurchaseLine(expenseAccounts[0]?.code || '5000')],
    });

    const { data, setData, processing, errors } = form;

    const submit = (event) => {
        event.preventDefault();
        form.transform((formData) => ({
            ...formData,
            items: formData.items.map((item) => ({
                account_code: item.account_code,
                description: item.description,
                quantity: item.quantity,
                unit_amount: item.unit_price,
                unit_price: item.unit_price,
                amount: (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
            })),
        }));
        form.post(route('recurring-bills.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('recurring-bills.index')}
                    title="New recurring bill"
                    subtitle="Set it up once. Each cycle creates a draft bill."
                    formId="recurring-bill-create-form"
                    processing={processing}
                    submitLabel="Save recurring bill"
                />
            }
        >
            <Head title="New recurring bill" />
            <form id="recurring-bill-create-form" className="space-y-6 pb-12 min-w-0" onSubmit={submit}>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Template details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Internal label</label>
                            <input className={inputClass} placeholder="e.g. Office rent — monthly" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Supplier</label>
                            <select className={inputClass} value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} required>
                                <option value="">Select supplier...</option>
                                {suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
                            </select>
                            {errors.supplier_id && <p className="mt-1 text-xs text-terracotta">{errors.supplier_id}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Cadence</label>
                            <select className={inputClass} value={data.cadence} onChange={(e) => setData('cadence', e.target.value)}>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Repeat every</label>
                            <input type="number" min="1" max="36" className={`${inputClass} font-mono`} value={data.interval} onChange={(e) => setData('interval', e.target.value)} />
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Start date</label>
                            <input type="date" className={inputClass} value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
                            {errors.start_date && <p className="mt-1 text-xs text-terracotta">{errors.start_date}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Payment terms (days)</label>
                            <input type="number" min="0" max="365" className={`${inputClass} font-mono`} value={data.payment_terms_days} onChange={(e) => setData('payment_terms_days', e.target.value)} />
                        </div>
                        <label className="md:col-span-4 inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
                            <input type="checkbox" checked={data.auto_post} onChange={(e) => setData('auto_post', e.target.checked)} className="w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta" />
                            Post generated bills to the ledger automatically
                        </label>
                    </div>
                </div>

                <PurchasesDocLines
                    items={data.items}
                    onChange={(items) => setData('items', items)}
                    expenseAccounts={expenseAccounts}
                />
                {errors.items && <p className="text-xs text-terracotta">{errors.items}</p>}

                <DocumentFormNotesTotals
                    bannerTitle="Draft each cycle"
                    bannerText="Each cycle creates a fresh draft bill. Review and post it when you are ready."
                    notesLabel="Notes (on PDF)"
                    notesValue={data.notes}
                    onNotesChange={(value) => setData('notes', value)}
                    items={data.items}
                />
            </form>
        </AuthenticatedLayout>
    );
}
