import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import EstimateForm from './_Form';

const toDateInput = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.slice(0, 10);
    return new Date(value).toISOString().slice(0, 10);
};

export default function Edit({ auth, estimate, customers = [], products = [], base_currency = 'MYR' }) {
    const { data, setData, put, processing, errors } = useForm({
        estimate_number: estimate.estimate_number || '',
        customer_id: estimate.customer_id || '',
        currency: estimate.currency || base_currency,
        exchange_rate: estimate.exchange_rate ?? 1,
        issue_date: toDateInput(estimate.issue_date),
        expiry_date: toDateInput(estimate.expiry_date),
        items: (estimate.items || []).map(i => ({
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
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Edit · ${estimate.estimate_number}`} />
            <div className="mb-2 max-w-6xl mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
                <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">Edit estimate</h1>
                <p className="text-sm text-ink-muted mt-1">Editing changes the customer-facing quote. The status stays the same — change it from the estimate's view page.</p>
            </div>
            <div className="max-w-6xl mx-auto p-4 sm:p-6">
                <EstimateForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    customers={customers}
                    products={products}
                    base_currency={base_currency}
                    submitLabel="Update estimate"
                />
            </div>
        </AuthenticatedLayout>
    );
}
