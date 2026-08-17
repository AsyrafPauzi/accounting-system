import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import PurchaseOrderForm from './_Form';

export default function Edit({ auth, order, editable = true, lock_reason = null, suppliers = [], products = [], expenseAccounts = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        supplier_id: order.supplier_id || '',
        issue_date: order.issue_date?.slice?.(0, 10) || order.issue_date || '',
        expected_date: order.expected_date?.slice?.(0, 10) || order.expected_date || '',
        notes: order.notes || '',
        items: (order.items || []).map((i) => ({
            id: i.id,
            description: i.description,
            quantity: i.quantity,
            unit_price: i.unit_price,
            tax_rate: i.tax_rate ?? 0,
            discount_amount: i.discount_amount || 0,
            account_code: i.account_code || expenseAccounts[0]?.code || '5000',
            product_id: i.product_id || null,
        })),
    });

    const submit = (e) => {
        e.preventDefault();
        if (editable) put(route('purchase-orders.update', order.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('purchase-orders.show', order.id)}
                    title={`Edit ${order.po_number}`}
                    subtitle="Save the order, then receive goods or convert to a bill"
                    formId="po-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={editable}
                />
            }
        >
            <Head title={`Edit ${order.po_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <PurchaseOrderForm
                formId="po-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                suppliers={suppliers}
                products={products}
                expenseAccounts={expenseAccounts}
                number={order.po_number}
                disabled={!editable}
                bannerTitle="Confirmed order"
                bannerText="Line quantity cannot go below what has already been received."
            />
        </AuthenticatedLayout>
    );
}
