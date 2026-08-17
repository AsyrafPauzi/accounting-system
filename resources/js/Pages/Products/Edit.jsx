import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import ProductForm from './_Form';

export default function Edit({ auth, product, incomeAccounts = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        code: product.code || '',
        name: product.name || '',
        description: product.description || '',
        unit_price: product.unit_price ?? 0,
        account_code: product.account_code || '',
        tax_rate: product.tax_rate ?? 0,
        classification_code: product.classification_code || '022',
        is_active: !!product.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Edit · ${product.name}`} />
            <div className="mb-3">
                <h1 className="text-2xl font-display font-medium text-ink">Edit product</h1>
                <p className="text-sm text-ink-muted mt-1">Changes apply to new invoice lines only — past invoices keep the values they had at the time.</p>
            </div>
            <ProductForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                incomeAccounts={incomeAccounts}
                submitLabel="Update product"
            />
        </AuthenticatedLayout>
    );
}
