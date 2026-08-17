import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SalesOrderForm from './_Form';

export default function Edit({ auth, order, editable = true, lock_reason = null, customers = [], products = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        customer_id: order.customer_id || '',
        issue_date: order.issue_date?.slice?.(0, 10) || order.issue_date || '',
        expected_date: order.expected_date?.slice?.(0, 10) || order.expected_date || '',
        customer_notes: order.customer_notes || '',
        shipping_amount: order.shipping_amount ?? 0,
        items: (order.items || []).map((i) => ({
            id: i.id,
            description: i.description,
            quantity: i.quantity,
            unit_price: i.unit_price,
            tax_rate: i.tax_rate ?? 0,
            product_id: i.product_id || null,
            account_code: i.account_code || null,
            discount_amount: i.discount_amount || 0,
            qty_delivered: i.qty_delivered || 0,
        })),
    });

    const submit = (e) => {
        e.preventDefault();
        if (editable) put(route('sales-orders.update', order.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('sales-orders.show', order.id)}
                    title={`Edit ${order.so_number}`}
                    subtitle="Line qty cannot go below already delivered"
                    formId="so-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={editable}
                />
            }
        >
            <Head title={`Edit ${order.so_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <SalesOrderForm
                formId="so-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                customers={customers}
                products={products}
                number={order.so_number}
                disabled={!editable}
                allowNewCustomer={editable}
                bannerTitle="Confirmed order"
                bannerText="Line quantity cannot go below what has already been delivered."
            />
        </AuthenticatedLayout>
    );
}
