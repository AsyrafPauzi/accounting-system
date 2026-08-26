import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import InvoiceForm, { blankInvoiceLine } from './_Form';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';
const emptyCustomer = { name: '', code: '', email: '', tin: '', brn: '', billing_street: '', billing_city: '', billing_state: '', billing_zip: '' };

function QuickCustomerModal({ open, onClose, onCreated, setData }) {
    const [form, setForm] = useState(emptyCustomer);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    if (!open) return null;

    const field = (key, label, opts = {}) => (
        <div>
            <label className={labelClass}>{label}</label>
            <input
                type={opts.type || 'text'}
                value={form[key]}
                onChange={(e) => setForm((c) => ({ ...c, [key]: e.target.value }))}
                className={inputClass}
                required={opts.required}
                placeholder={opts.placeholder}
            />
            {errors[key] && <p className="text-terracotta text-xs mt-1">{errors[key][0]}</p>}
        </div>
    );

    const submit = (e) => {
        e.preventDefault();
        setErrors({});
        setSubmitting(true);
        const snapshot = { ...form };
        router.post(route('customers.quick-store'), {
            name: snapshot.name,
            email: snapshot.email,
            tin: snapshot.tin,
            brn: snapshot.brn,
            ...(snapshot.code && { code: snapshot.code }),
            ...(snapshot.billing_street && { billing_street: snapshot.billing_street }),
            ...(snapshot.billing_city && { billing_city: snapshot.billing_city }),
            ...(snapshot.billing_state && { billing_state: snapshot.billing_state }),
            ...(snapshot.billing_zip && { billing_zip: snapshot.billing_zip }),
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const newId = page.props.flash?.new_customer_id;
                if (newId) {
                    setData('customer_id', String(newId));
                    onCreated({ id: newId, name: snapshot.name, tin: snapshot.tin || null });
                }
                onClose();
                setForm(emptyCustomer);
                setErrors({});
            },
            onError: setErrors,
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50 backdrop-blur-sm" onClick={() => !submitting && onClose()}>
            <div className="bg-surface rounded-2xl shadow-xl border border-border-warm/80 w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                <div className="p-6 border-b border-border-warm">
                    <h3 className="text-lg font-display font-medium text-ink">New customer</h3>
                    <p className="text-sm text-ink-muted mt-0.5">Add a customer to use on this invoice. Name and Email are required.</p>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    {errors.form && <div className="p-3 rounded-xl bg-terracotta/10 text-terracotta text-sm">{errors.form}</div>}
                    {field('name', 'Name *', { required: true })}
                    {field('code', 'Code (optional)', { placeholder: 'Auto-generated if blank' })}
                    {field('email', 'Email *', { type: 'email', required: true })}
                    <div className="grid grid-cols-2 gap-4">
                        {field('tin', 'TIN')}
                        {field('brn', 'BRN')}
                    </div>
                    {field('billing_street', 'Billing street (optional)')}
                    <div className="grid grid-cols-3 gap-4">
                        {field('billing_city', 'City')}
                        {field('billing_state', 'State')}
                        {field('billing_zip', 'Zip')}
                    </div>
                    <div className="flex justify-end gap-2 pt-4 border-t border-border-warm">
                        <button type="button" onClick={() => !submitting && onClose()} className="px-4 py-2.5 rounded-xl font-semibold text-ink hover:bg-surface-alt">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                            {submitting ? 'Saving...' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Create({
    auth,
    customers = [],
    lhdn_codes = [],
    customer_id: preselectedCustomerId = null,
    next_invoice_number: suggestedInvoiceNumber = null,
    base_currency = 'MYR',
    products = [],
    cash_sale = false,
    bankAccounts = [],
    default_customer_notes = '',
}) {
    const { tax_codes: taxCodes = [] } = usePage().props;
    const [showNewCustomerModal, setShowNewCustomerModal] = useState(false);
    const [newCustomers, setNewCustomers] = useState([]);

    const { data, setData, post, processing, errors } = useForm({
        invoice_number: suggestedInvoiceNumber || `INV-${Date.now()}`,
        customer_id: preselectedCustomerId ? String(preselectedCustomerId) : '',
        msic_code: '62011',
        issue_date: new Date().toISOString().split('T')[0],
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        shipping_amount: 0,
        customer_notes: default_customer_notes || '',
        show_signature: false,
        currency: 'MYR',
        exchange_rate: '1',
        bank_account_code: bankAccounts[0]?.code || '',
        payment_date: new Date().toISOString().split('T')[0],
        items: [blankInvoiceLine()],
    });

    const title = cash_sale ? 'Cash sale' : 'New Invoice';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('invoices.index')}
                    title={title}
                    subtitle={cash_sale ? 'Invoice plus full receipt in one save' : 'Bill the customer — post and email when ready'}
                    formId="invoice-create-form"
                    processing={processing}
                    submitLabel={cash_sale ? 'Save cash sale' : 'Create Invoice'}
                />
            }
        >
            <Head title={title} />
            <InvoiceForm
                formId="invoice-create-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={(e) => { e.preventDefault(); post(route(cash_sale ? 'invoices.cash-sale.store' : 'invoices.store')); }}
                customers={customers}
                customerOptions={[...customers, ...newCustomers]}
                lhdn_codes={lhdn_codes}
                products={products}
                taxCodes={taxCodes}
                base_currency={base_currency}
                cash_sale={cash_sale}
                bankAccounts={bankAccounts}
                mode="create"
                showNewCustomer
                onOpenNewCustomer={() => setShowNewCustomerModal(true)}
            />
            <QuickCustomerModal
                open={showNewCustomerModal}
                onClose={() => setShowNewCustomerModal(false)}
                setData={setData}
                onCreated={(c) => setNewCustomers((prev) => (prev.some((x) => String(x.id) === String(c.id)) ? prev : [...prev, c]))}
            />
        </AuthenticatedLayout>
    );
}
