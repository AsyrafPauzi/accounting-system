import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import RecurringInvoiceForm from './_Form';

const todayString = () => new Date().toISOString().slice(0, 10);

export default function Create({
    auth,
    customers = [],
    customer_id: preselectedCustomerId = null,
    products = [],
    base_currency = 'MYR',
}) {
    const today = todayString();

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        customer_id: preselectedCustomerId || '',
        cadence: 'monthly',
        interval: 1,
        start_date: today,
        next_run_date: today,
        end_date: '',
        is_active: true,
        currency: base_currency,
        exchange_rate: 1,
        shipping_amount: 0,
        payment_terms_days: 30,
        msic_code: '00000',
        items: [{ description: '', quantity: 1, unit_price: 0, discount_amount: 0, tax_rate: 0, product_id: null, item_classification: '022' }],
        customer_notes: '',
        private_notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('recurring-invoices.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="New recurring invoice" />
            <div className="mb-2 max-w-6xl mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
                <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">New recurring invoice</h1>
                <p className="text-sm text-ink-muted mt-1">Set it up once. Each cycle creates a fresh <strong>draft</strong> invoice for you to review and post — no auto-send, no auto-post.</p>
            </div>
            <div className="max-w-6xl mx-auto p-4 sm:p-6">
                <RecurringInvoiceForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    customers={customers}
                    products={products}
                    base_currency={base_currency}
                    submitLabel="Save recurring invoice"
                />
            </div>
        </AuthenticatedLayout>
    );
}
