import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import RecurringInvoiceForm from './_Form';

const toDateInput = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.slice(0, 10);
    return new Date(value).toISOString().slice(0, 10);
};

export default function Edit({ auth, template, customers = [], products = [], base_currency = 'MYR' }) {
    const { data, setData, put, processing, errors } = useForm({
        name: template.name || '',
        customer_id: template.customer_id || '',
        cadence: template.cadence || 'monthly',
        interval: template.interval || 1,
        start_date: toDateInput(template.start_date),
        next_run_date: toDateInput(template.next_run_date),
        end_date: toDateInput(template.end_date),
        is_active: !!template.is_active,
        currency: template.currency || base_currency,
        exchange_rate: template.exchange_rate ?? 1,
        shipping_amount: template.shipping_amount ?? 0,
        payment_terms_days: template.payment_terms_days ?? 30,
        msic_code: template.msic_code || '00000',
        items: (template.items || []).map(i => ({
            description: i.description || '',
            quantity: i.quantity ?? 1,
            unit_price: i.unit_price ?? 0,
            discount_amount: i.discount_amount ?? 0,
            tax_rate: i.tax_rate ?? 0,
            product_id: i.product_id ?? null,
            item_classification: i.item_classification || '022',
        })),
        customer_notes: template.customer_notes || '',
        private_notes: template.private_notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('recurring-invoices.update', template.id));
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Edit · ${template.name || 'Recurring invoice'}`} />
            <div className="mb-2 max-w-6xl mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
                <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">Edit recurring invoice</h1>
                <p className="text-sm text-ink-muted mt-1">Changes apply to <strong>future</strong> generated invoices. Past invoices already created stay exactly as they were.</p>
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
                    submitLabel="Update recurring invoice"
                />
            </div>
        </AuthenticatedLayout>
    );
}
