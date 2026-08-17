import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import EstimateForm from './_Form';

const toDateInput = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.slice(0, 10);
    return new Date(value).toISOString().slice(0, 10);
};

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

export default function Edit({ auth, estimate, customers = [], products = [], base_currency = 'MYR' }) {
    const { data, setData, put, processing, errors } = useForm({
        estimate_number: estimate.estimate_number || '',
        customer_id: estimate.customer_id || '',
        currency: estimate.currency || base_currency,
        exchange_rate: estimate.exchange_rate ?? 1,
        issue_date: toDateInput(estimate.issue_date),
        expiry_date: toDateInput(estimate.expiry_date),
        items: (estimate.items || []).map((i) => ({
            description: i.description || '',
            quantity: i.quantity ?? 1,
            unit_price: i.unit_price ?? 0,
            discount_amount: i.discount_amount ?? 0,
            tax_rate: i.tax_rate ?? 0,
            product_id: i.product_id ?? null,
            item_classification: i.item_classification || '022',
        })),
        shipping_amount: estimate.shipping_amount ?? 0,
        customer_notes: estimate.customer_notes || '',
        private_notes: estimate.private_notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('estimates.update', estimate.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link href={route('estimates.show', estimate.id)} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta"><Icons.Document /></span>
                            <div>
                                <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit estimate</h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">{estimate.estimate_number} · Update this quotation before sending</p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('estimates.show', estimate.id)} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200">
                            Cancel
                        </Link>
                        <button type="submit" form="estimate-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 shadow-lg transition-all duration-200">
                            {processing ? 'Saving…' : 'Update estimate'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Edit · ${estimate.estimate_number}`} />
            <EstimateForm
                formId="estimate-form"
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                customers={customers}
                products={products}
                base_currency={base_currency}
            />
        </AuthenticatedLayout>
    );
}
