import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import SupplierForm from './_Form';

export default function Edit({ auth, supplier }) {
    const { data, setData, put, processing, errors } = useForm({
        name: supplier.name || '',
        code: supplier.code || '',
        contact_person: supplier.contact_person || '',
        phone: supplier.phone || '',
        email: supplier.email || '',
        tin: supplier.tin || '',
        brn: supplier.brn || '',
        identification_type: supplier.identification_type || 'BRN',
        sst_number: supplier.sst_number || '',
        payment_terms: supplier.payment_terms ?? 30,
        currency: supplier.currency || 'MYR',
        billing_street: supplier.billing_street || '',
        billing_city: supplier.billing_city || '',
        billing_state: supplier.billing_state || '',
        billing_zip: supplier.billing_zip || '',
        billing_country: supplier.billing_country || 'Malaysia',
        website: supplier.website || '',
        region: supplier.region || '',
        segment: supplier.segment || '',
        is_active: supplier.is_active === 1 || supplier.is_active === true,
        internal_notes: supplier.internal_notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('suppliers.update', supplier.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('suppliers.show', supplier.id)}
                    title={data.name || supplier.name || 'Edit supplier'}
                    subtitle={supplier.code}
                    formId="supplier-edit-form"
                    processing={processing}
                    submitLabel="Save supplier"
                />
            }
        >
            <Head title={`Edit ${supplier.name}`} />
            <SupplierForm formId="supplier-edit-form" data={data} setData={setData} errors={errors} onSubmit={submit} mode="edit" />
        </AuthenticatedLayout>
    );
}
