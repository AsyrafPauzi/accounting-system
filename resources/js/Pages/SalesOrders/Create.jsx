import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import { blankSalesLine } from '@/Components/SalesDocLines';
import SalesOrderForm from './_Form';

export default function Create({ auth, customers = [], products = [], next_number, customer_id }) {
    const { data, setData, post, processing, errors } = useForm({
        so_number: next_number,
        customer_id: customer_id || '',
        issue_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        customer_notes: '',
        shipping_amount: 0,
        items: [blankSalesLine()],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('sales-orders.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('sales-orders.index')}
                    title="New sales order"
                    subtitle="Save the order, then create a delivery or convert to an invoice"
                    formId="so-form"
                    processing={processing}
                    submitLabel="Save sales order"
                />
            }
        >
            <Head title="New sales order" />
            <SalesOrderForm
                formId="so-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                customers={customers}
                products={products}
                number={data.so_number}
            />
        </AuthenticatedLayout>
    );
}
