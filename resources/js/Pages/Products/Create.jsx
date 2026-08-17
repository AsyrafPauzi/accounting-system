import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
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
        <AuthenticatedLayout user={auth.user}>
            <Head title="New product" />
            <div className="mb-3">
                <h1 className="text-2xl font-display font-medium text-ink">New product or service</h1>
                <p className="text-sm text-ink-muted mt-1">Save it once, then pick it from the dropdown on every invoice line.</p>
            </div>
            <ProductForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                incomeAccounts={incomeAccounts}
                submitLabel="Save product"
            />
        </AuthenticatedLayout>
    );
}
