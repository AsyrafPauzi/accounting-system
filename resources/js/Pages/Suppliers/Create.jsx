import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SupplierForm from './_Form';

export default function Create({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: 'SUP-' + String(Math.floor(1000 + Math.random() * 9000)),
        contact_person: '',
        phone: '',
        email: '',
        tin: '',
        brn: '',
        identification_type: 'BRN',
        sst_number: '',
        payment_terms: 30,
        currency: 'MYR',
        billing_street: '',
        billing_city: '',
        billing_state: '',
        billing_zip: '',
        billing_country: 'Malaysia',
        website: '',
        region: '',
        segment: '',
        is_active: true,
        internal_notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('suppliers.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('suppliers.index')}
                    title="New supplier"
                    subtitle="Save now. Complete MyInvois fields before a self-billed e-invoice."
                    formId="supplier-create-form"
                    processing={processing}
                    submitLabel="Create supplier"
                />
            }
        >
            <Head title="New supplier" />
            <SupplierForm formId="supplier-create-form" data={data} setData={setData} errors={errors} onSubmit={submit} mode="create" />
        </AuthenticatedLayout>
    );
}
