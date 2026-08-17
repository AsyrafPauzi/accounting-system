import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
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
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('products.index')}
                    title={product.name || 'Edit product'}
                    subtitle="Changes apply to new invoice lines only"
                    formId="product-form"
                    processing={processing}
                    submitLabel="Update product"
                />
            }
        >
            <Head title={`Edit · ${product.name}`} />
            <ProductForm
                formId="product-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                incomeAccounts={incomeAccounts}
            />
        </AuthenticatedLayout>
    );
}
