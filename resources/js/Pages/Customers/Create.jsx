import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { mergeCustomerFormPayload } from '@/constants/customerFormOptions';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import CustomerForm from './_Form';

export default function Create({ auth, users = [] }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        code: 'CUST-' + Math.floor(1000 + Math.random() * 9000),
        industry_key: '',
        industry_other: '',
        website: '',
        tin: '',
        brn: '',
        identification_type: 'BRN',
        sst_number: '',
        contact_person: '',
        phone: '',
        email: '',
        credit_limit: 5000,
        credit_hold: false,
        payment_terms_select: '30',
        payment_terms_custom: '30',
        currency: 'MYR',
        risk_rating: '',
        segment: '',
        region: '',
        account_manager_id: '',
        billing_street: '',
        billing_city: '',
        billing_state: '',
        billing_zip: '',
        billing_country: 'Malaysia',
        shipping_street: '',
        shipping_city: '',
        shipping_state: '',
        shipping_zip: '',
        shipping_country: 'Malaysia',
        internal_notes: '',
        invoice_delivery_method: 'email',
        send_statement: false,
        contacts: [],
    });

    transform((formData) => mergeCustomerFormPayload(formData));

    const submit = (e) => {
        e.preventDefault();
        post(route('customers.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('customers.index')}
                    title="New customer"
                    subtitle="Save now. Complete MyInvois fields before you submit an e-invoice."
                    formId="customer-create-form"
                    processing={processing}
                    submitLabel="Create customer"
                />
            }
        >
            <Head title="New customer" />
            <CustomerForm
                formId="customer-create-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                users={users}
                mode="create"
            />
        </AuthenticatedLayout>
    );
}
