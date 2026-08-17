import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import { blankPurchaseLine } from '@/Components/PurchasesDocLines';
import PurchaseOrderForm from './_Form';

export default function Create({ auth, suppliers = [], products = [], expenseAccounts = [], next_number }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';
    const { data, setData, post, processing, errors } = useForm({
        po_number: next_number,
        supplier_id: '',
        issue_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        notes: '',
        items: [blankPurchaseLine(defaultAccount)],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('purchase-orders.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('purchase-orders.index')}
                    title="New purchase order"
                    subtitle="Save the order, then receive goods or convert to a bill"
                    formId="po-form"
                    processing={processing}
                    submitLabel="Save purchase order"
                />
            }
        >
            <Head title="New purchase order" />
            <PurchaseOrderForm
                formId="po-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                suppliers={suppliers}
                products={products}
                expenseAccounts={expenseAccounts}
                number={data.po_number}
            />
        </AuthenticatedLayout>
    );
}
