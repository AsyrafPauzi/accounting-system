import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import ProductForm from './_Form';

export default function Create({ auth, incomeAccounts = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        description: '',
        unit_price: '',
        account_code: '',
        tax_rate: 0,
        classification_code: '022',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('products.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('products.index')}
                    title="New product"
                    subtitle="Save it once, then pick it on invoice lines"
                    formId="product-form"
                    processing={processing}
                    submitLabel="Save product"
                />
            }
        >
            <Head title="New product" />
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
