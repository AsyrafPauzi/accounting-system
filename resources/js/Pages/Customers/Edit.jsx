import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import {
    deriveIndustryState,
    derivePaymentTermsState,
    mergeCustomerFormPayload,
} from '@/constants/customerFormOptions';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import CustomerForm from './_Form';

export default function Edit({ auth, customer, users = [], can_delete_customer = false, delete_blocked_reason = null }) {
    const indState = deriveIndustryState(customer.industry || '');
    const ptState = derivePaymentTermsState(customer.payment_terms ?? 30);

    const { data, setData, put, processing, errors, transform } = useForm({
        name: customer.name || '',
        code: customer.code || '',
        industry_key: indState.industry_key,
        industry_other: indState.industry_other,
        website: customer.website || '',
        tin: customer.tin || '',
        brn: customer.brn || '',
        identification_type: customer.identification_type || 'BRN',
        sst_number: customer.sst_number || '',
        contact_person: customer.contact_person || '',
        phone: customer.phone || '',
        email: customer.email || '',
        credit_limit: customer.credit_limit || 0,
        credit_hold: customer.credit_hold === 1 || customer.credit_hold === true,
        payment_terms_select: ptState.payment_terms_select,
        payment_terms_custom: ptState.payment_terms_custom,
        currency: customer.currency || 'MYR',
        is_active: customer.is_active === 1 || customer.is_active === true,
        risk_rating: customer.risk_rating || '',
        segment: customer.segment || '',
        region: customer.region || '',
        account_manager_id: customer.account_manager_id ?? '',
        billing_street: customer.billing_street || '',
        billing_city: customer.billing_city || '',
        billing_state: customer.billing_state || '',
        billing_zip: customer.billing_zip || '',
        billing_country: customer.billing_country || 'Malaysia',
        shipping_street: customer.shipping_street || '',
        shipping_city: customer.shipping_city || '',
        shipping_state: customer.shipping_state || '',
        shipping_zip: customer.shipping_zip || '',
        shipping_country: customer.shipping_country || 'Malaysia',
        internal_notes: customer.internal_notes || '',
        invoice_delivery_method: customer.invoice_delivery_method || 'email',
        send_statement: customer.send_statement === 1 || customer.send_statement === true,
        contacts: (customer.contacts || []).map((c) => ({
            id: c.id,
            name: c.name || '',
            email: c.email || '',
            phone: c.phone || '',
            type: c.type || 'billing',
            is_primary: c.is_primary === 1 || c.is_primary === true,
        })),
    });

    transform((formData) => mergeCustomerFormPayload(formData));

    const submit = (e) => {
        e.preventDefault();
        put(route('customers.update', customer.id), {
            preserveScroll: true,
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });
    };

    const handleDelete = async () => {
        if (!can_delete_customer) return;
        const ok = await confirm({
            title: 'Delete this customer?',
            text: `Remove "${customer.name}"? This cannot be undone.`,
            confirmText: 'Delete customer',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('customers.destroy', customer.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('customers.show', customer.id)}
                    title={data.name || customer.name || 'Edit customer'}
                    subtitle={`${customer.code || ''} · ${data.is_active ? 'Active' : 'Suspended'}`}
                    formId="customer-edit-form"
                    processing={processing}
                    submitLabel="Save customer"
                />
            }
        >
            <Head title={`Edit ${customer.name}`} />
            <CustomerForm
                formId="customer-edit-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                users={users}
                mode="edit"
            />
            {auth.permissions.includes('customers.delete') && (
                <div className="mt-2 mb-12 p-5 rounded-2xl border border-terracotta/30 bg-terracotta/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 className="font-semibold text-terracotta text-sm">Delete customer</h3>
                        <p className="text-xs text-ink-muted mt-1 max-w-xl">
                            {can_delete_customer
                                ? 'Permanently removes this record. Invoices and credit notes must be cleared first.'
                                : (delete_blocked_reason || 'This customer cannot be deleted yet.')}
                        </p>
                    </div>
                    <button
                        type="button"
                        disabled={!can_delete_customer}
                        onClick={handleDelete}
                        className={`inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold shrink-0 ${
                            can_delete_customer
                                ? 'text-white bg-terracotta hover:bg-terracotta-dark'
                                : 'text-terracotta bg-terracotta/10 cursor-not-allowed'
                        }`}
                    >
                        Delete customer
                    </button>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
