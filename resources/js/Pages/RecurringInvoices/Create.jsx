import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import RecurringInvoiceForm from './_Form';
import { blankSalesLine } from '@/Components/SalesDocLines';

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
        items: [{ ...blankSalesLine(), tax_rate: 0 }],
        customer_notes: '',
        private_notes: '',
        auto_email: false,
        auto_post: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('recurring-invoices.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('recurring-invoices.index')}
                    title="New recurring invoice"
                    subtitle="Set it up once. Each cycle creates a fresh draft invoice. Turn on auto-email if you want the draft PDF sent to the customer."
                    formId="recurring-invoice-form"
                    processing={processing}
                    submitLabel="Save recurring invoice"
                />
            }
        >
            <Head title="New recurring invoice" />
            <RecurringInvoiceForm
                formId="recurring-invoice-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                customers={customers}
                products={products}
                base_currency={base_currency}
            />
        </AuthenticatedLayout>
    );
}
