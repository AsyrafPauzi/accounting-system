import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import SalesDocLines from '@/Components/SalesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

const initialQuickCustomer = {
    name: '',
    code: '',
    email: '',
    tin: '',
    brn: '',
    billing_street: '',
    billing_city: '',
    billing_state: '',
    billing_zip: '',
};

export default function SalesOrderForm({
    formId = 'so-form',
    data,
    setData,
    errors = {},
    onSubmit,
    customers = [],
    products = [],
    number,
    disabled = false,
    allowNewCustomer = true,
    bannerTitle = 'Order first',
    bannerText = 'Save the order, then create a delivery or convert to an invoice. Nothing posts to the ledger until you invoice.',
}) {
    const [showNewCustomerModal, setShowNewCustomerModal] = useState(false);
    const [newCustomers, setNewCustomers] = useState([]);
    const [quickCustomer, setQuickCustomer] = useState(initialQuickCustomer);
    const [quickCustomerErrors, setQuickCustomerErrors] = useState({});
    const [quickSubmitting, setQuickSubmitting] = useState(false);

    const customerOptions = [...customers, ...newCustomers];

    const closeQuickCustomer = () => {
        if (quickSubmitting) return;
        setShowNewCustomerModal(false);
        setQuickCustomer(initialQuickCustomer);
        setQuickCustomerErrors({});
    };

    const submitQuickCustomer = (e) => {
        e.preventDefault();
        setQuickCustomerErrors({});
        setQuickSubmitting(true);
        const snapshot = { ...quickCustomer };
        const payload = {
            name: snapshot.name,
            email: snapshot.email,
            tin: snapshot.tin,
            brn: snapshot.brn,
            ...(snapshot.code && { code: snapshot.code }),
            ...(snapshot.billing_street && { billing_street: snapshot.billing_street }),
            ...(snapshot.billing_city && { billing_city: snapshot.billing_city }),
            ...(snapshot.billing_state && { billing_state: snapshot.billing_state }),
            ...(snapshot.billing_zip && { billing_zip: snapshot.billing_zip }),
        };
        router.post(route('customers.quick-store'), payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const newId = page.props.flash?.new_customer_id;
                if (newId) {
                    setData('customer_id', String(newId));
                    setNewCustomers((prev) => {
                        if (prev.some((c) => String(c.id) === String(newId))) return prev;
                        return [...prev, { id: newId, name: snapshot.name, tin: snapshot.tin || null }];
                    });
                }
                setShowNewCustomerModal(false);
                setQuickCustomer(initialQuickCustomer);
                setQuickCustomerErrors({});
            },
            onError: (errs) => setQuickCustomerErrors(errs),
            onFinish: () => setQuickSubmitting(false),
        });
    };

    return (
        <>
            <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Order details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Number</label>
                            <div className={inputReadonlyClass}>{number}</div>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Customer</label>
                            <div className="flex gap-2 items-stretch min-w-0">
                                <select
                                    value={data.customer_id}
                                    onChange={(e) => setData('customer_id', e.target.value)}
                                    className={`${inputClass} min-w-0 flex-1`}
                                    required
                                    disabled={disabled}
                                >
                                    <option value="">Select customer...</option>
                                    {customerOptions.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>
                                    ))}
                                </select>
                                {allowNewCustomer && !disabled && (
                                    <button
                                        type="button"
                                        onClick={() => setShowNewCustomerModal(true)}
                                        className="shrink-0 h-11 inline-flex items-center gap-1.5 px-4 rounded-xl font-semibold text-sm text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-colors"
                                    >
                                        <Icons.Plus /> New customer
                                    </button>
                                )}
                            </div>
                            {errors.customer_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.customer_id}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} className={inputClass} required disabled={disabled} />
                            {errors.issue_date && <p className="text-terracotta text-xs font-medium mt-1">{errors.issue_date}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Expected date</label>
                            <input type="date" value={data.expected_date || ''} onChange={(e) => setData('expected_date', e.target.value)} className={inputClass} disabled={disabled} />
                        </div>
                    </div>
                </div>

                <SalesDocLines
                    items={data.items}
                    onChange={(items) => setData('items', items)}
                    products={products}
                    disabled={disabled}
                    descriptionPlaceholder="What are you selling?"
                />

                <DocumentFormNotesTotals
                    bannerTitle={bannerTitle}
                    bannerText={bannerText}
                    notesValue={data.customer_notes || ''}
                    onNotesChange={(value) => setData('customer_notes', value)}
                    notesPlaceholder="Delivery details, thank you message…"
                    notesDisabled={disabled}
                    items={data.items}
                    shipping={data.shipping_amount}
                    onShippingChange={(value) => setData('shipping_amount', value)}
                    showShipping
                />
            </form>

            {showNewCustomerModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50 backdrop-blur-sm" onClick={closeQuickCustomer}>
                    <div className="bg-surface rounded-2xl shadow-xl border border-border-warm/80 w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b border-border-warm">
                            <h3 className="text-lg font-display font-medium text-ink">New customer</h3>
                            <p className="text-sm text-ink-muted mt-0.5">Add a customer for this sales order. Name and email are required.</p>
                        </div>
                        <form onSubmit={submitQuickCustomer} className="p-6 space-y-4">
                            {quickCustomerErrors.form && (
                                <div className="p-3 rounded-xl bg-terracotta/10 text-terracotta text-sm">{quickCustomerErrors.form}</div>
                            )}
                            <div>
                                <label className={labelClass}>Name *</label>
                                <input type="text" value={quickCustomer.name} onChange={(e) => setQuickCustomer((c) => ({ ...c, name: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.name && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.name[0]}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Code (optional)</label>
                                <input type="text" value={quickCustomer.code} onChange={(e) => setQuickCustomer((c) => ({ ...c, code: e.target.value }))} className={inputClass} placeholder="Auto-generated if blank" />
                            </div>
                            <div>
                                <label className={labelClass}>Email *</label>
                                <input type="email" value={quickCustomer.email} onChange={(e) => setQuickCustomer((c) => ({ ...c, email: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.email && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.email[0]}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>TIN</label>
                                    <input type="text" value={quickCustomer.tin} onChange={(e) => setQuickCustomer((c) => ({ ...c, tin: e.target.value }))} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>BRN</label>
                                    <input type="text" value={quickCustomer.brn} onChange={(e) => setQuickCustomer((c) => ({ ...c, brn: e.target.value }))} className={inputClass} />
                                </div>
                            </div>
                            <div>
                                <label className={labelClass}>Billing street (optional)</label>
                                <input type="text" value={quickCustomer.billing_street} onChange={(e) => setQuickCustomer((c) => ({ ...c, billing_street: e.target.value }))} className={inputClass} />
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className={labelClass}>City</label>
                                    <input type="text" value={quickCustomer.billing_city} onChange={(e) => setQuickCustomer((c) => ({ ...c, billing_city: e.target.value }))} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>State</label>
                                    <input type="text" value={quickCustomer.billing_state} onChange={(e) => setQuickCustomer((c) => ({ ...c, billing_state: e.target.value }))} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Zip</label>
                                    <input type="text" value={quickCustomer.billing_zip} onChange={(e) => setQuickCustomer((c) => ({ ...c, billing_zip: e.target.value }))} className={inputClass} />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-4 border-t border-border-warm">
                                <button type="button" onClick={closeQuickCustomer} className="px-4 py-2.5 rounded-xl font-semibold text-ink hover:bg-surface-alt">Cancel</button>
                                <button type="submit" disabled={quickSubmitting} className="px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50">
                                    {quickSubmitting ? 'Saving…' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
