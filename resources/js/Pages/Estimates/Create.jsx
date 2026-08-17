import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import EstimateForm from './_Form';

const todayString = () => new Date().toISOString().slice(0, 10);

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

export default function Create({
    auth,
    customers = [],
    customer_id: preselectedCustomerId = null,
    products = [],
    next_estimate_number = 'EST-1',
    base_currency = 'MYR',
    default_customer_notes = '',
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
        customer_notes: default_customer_notes || '',
        private_notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('estimates.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div className="flex items-center gap-2">
                        <Link href={route('estimates.index')} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-2.5">
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.Document /></span>
                            <div>
                                <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">New estimate</h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">Draft a quotation to send before invoicing</p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('estimates.index')} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200">
                            Cancel
                        </Link>
                        <button type="submit" form="estimate-form" disabled={processing} className="inline-flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 shadow-lg transition-all duration-200">
                            {processing ? 'Saving…' : 'Save estimate'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="New estimate" />
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
