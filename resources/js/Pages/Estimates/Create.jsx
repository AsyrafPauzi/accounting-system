import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import EstimateForm from './_Form';

const todayString = () => new Date().toISOString().slice(0, 10);

export default function Create({
    auth,
    customers = [],
    customer_id: preselectedCustomerId = null,
    products = [],
    next_estimate_number = 'EST-1',
    base_currency = 'MYR',
}) {
    const expiry = new Date();
    expiry.setDate(expiry.getDate() + 30);

    const { data, setData, post, processing, errors } = useForm({
        estimate_number: next_estimate_number,
        customer_id: preselectedCustomerId || '',
        currency: base_currency,
        exchange_rate: 1,
        issue_date: todayString(),
        expiry_date: expiry.toISOString().slice(0, 10),
        items: [{ description: '', quantity: 1, unit_price: 0, discount_amount: 0, tax_rate: 0, product_id: null, item_classification: '022' }],
        shipping_amount: 0,
        customer_notes: '',
        private_notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('estimates.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="New estimate" />
            <div className="mb-2 max-w-6xl mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
                <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">New estimate</h1>
                <p className="text-sm text-ink-muted mt-1">Estimates don't post to the General Ledger. They become invoices only when you click <strong>Convert to Invoice</strong>.</p>
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
                    submitLabel="Save estimate"
                />
            </div>
        </AuthenticatedLayout>
    );
}
