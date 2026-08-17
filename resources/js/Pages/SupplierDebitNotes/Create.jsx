import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function Create({ auth, suppliers = [], expenseAccounts = [], bill = null, next_number }) {
    const { data, setData, transform, post, processing, errors } = useForm({
        supplier_id: bill?.supplier_id || '',
        bill_id: bill?.id ?? null,
        sdn_number: next_number,
        issue_date: new Date().toISOString().split('T')[0],
        reason_description: '',
        notes: '',
        items: bill?.items?.length
            ? bill.items.map((i) => ({
                description: i.description,
                quantity: i.quantity,
                unit_price: i.unit_amount,
                tax_rate: i.tax_rate ?? 0,
                discount_amount: i.discount_amount || 0,
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
        post(route('supplier-debit-notes.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('supplier-debit-notes.index')}
                    title="Issue supplier debit note"
                    subtitle="Additional charges from the supplier"
                    formId="sdn-create-form"
                    processing={processing}
                    submitLabel="Issue debit note"
                />
            }
        >
            <Head title="Issue supplier debit note" />
            <form id="sdn-create-form" className="space-y-6 pb-12 min-w-0" onSubmit={submit}>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Debit note details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Number</label>
                            <div className={inputReadonlyClass}>{data.sdn_number}</div>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Supplier</label>
                            {bill ? (
                                <div className={`${inputReadonlyClass} text-ink font-sans font-medium`}>
                                    <span className="font-semibold">{bill.supplier?.name}</span>
                                    <span className="text-ink-muted ml-1">· Against {bill.bill_number}</span>
                                </div>
                            ) : (
                                <select className={inputClass} value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} required>
                                    <option value="">Select supplier...</option>
                                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            )}
                            {errors.supplier_id && <p className="mt-1 text-xs text-terracotta">{errors.supplier_id}</p>}
                            {errors.bill_id && <p className="mt-1 text-xs text-terracotta">{errors.bill_id}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} required />
                        </div>
                        <div className="md:col-span-4 min-w-0">
                            <label className={labelClass}>Reason (optional)</label>
                            <input className={inputClass} value={data.reason_description} onChange={(e) => setData('reason_description', e.target.value)} placeholder="Why is this extra charge being issued?" />
                        </div>
                    </div>
                </div>
                <PurchasesDocLines items={data.items} onChange={(items) => setData('items', items)} expenseAccounts={expenseAccounts} />
                {errors.items && <p className="text-xs text-terracotta">{errors.items}</p>}
                <DocumentFormNotesTotals
                    bannerTitle="Adds payable"
                    bannerText="This debit note increases what you owe the supplier."
                    notesLabel="Notes (on PDF)"
                    notesValue={data.notes}
                    onNotesChange={(value) => setData('notes', value)}
                    items={data.items}
                />
            </form>
        </AuthenticatedLayout>
    );
}
