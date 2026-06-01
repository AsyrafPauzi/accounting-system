import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import {
    INDUSTRY_OPTIONS,
    PAYMENT_TERM_PRESETS,
    PAYMENT_TERM_CUSTOM,
    mergeCustomerFormPayload,
} from '@/constants/customerFormOptions';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Building: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    CreditCard: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>,
    Phone: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>,
    Location: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    Truck: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1h1m4-1V6a1 1 0 00-1-1h-2.829M19 6v2a1 1 0 01-1 1h-1m-1 1V6a1 1 0 00-1-1h-1M4 6v2a1 1 0 001 1h1m1 1V6a1 1 0 00-1-1h-1" /></svg>,
    DocumentText: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowPath: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
};

const inputClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink placeholder-ink-muted/60 focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors";
const inputReadonlyClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink-muted bg-cream";
const labelClass = "block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5";

export default function Create({ auth, users = [] }) {
    // 1. All fields required for Enterprise-Level Customer Management
    const { data, setData, post, processing, errors, transform } = useForm({
        // Identity
        name: '',
        code: 'CUST-' + Math.floor(1000 + Math.random() * 9000),
        industry_key: '',
        industry_other: '',
        website: '', // Added Website
        
        // Compliance
        tin: '',
        brn: '',
        
        // Contact
        contact_person: '',
        phone: '',
        email: '',
        
        // Financials
        credit_limit: 5000,
        credit_hold: false,
        payment_terms_select: '30',
        payment_terms_custom: '30',
        currency: 'MYR',
        risk_rating: '',
        segment: '',
        region: '',
        account_manager_id: '',

        // Granular Billing Address
        billing_street: '',
        billing_city: '',
        billing_state: '',
        billing_zip: '',
        billing_country: 'Malaysia',

        // Granular Shipping Address
        shipping_street: '',
        shipping_city: '',
        shipping_state: '',
        shipping_zip: '',
        shipping_country: 'Malaysia',

        // Intelligence
        internal_notes: '',
        invoice_delivery_method: 'email',
        send_statement: false,
        contacts: [],
    });

    transform((formData) => mergeCustomerFormPayload(formData));

    const malaysianStates = [
        'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka', 
        'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 
        'Putrajaya', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu'
    ];

    const addContact = () => {
        setData('contacts', [...(data.contacts || []), { name: '', email: '', phone: '', type: 'billing', is_primary: false }]);
    };
    const removeContact = (index) => {
        setData('contacts', data.contacts.filter((_, i) => i !== index));
    };
    const updateContact = (index, field, value) => {
        const next = [...(data.contacts || [])];
        next[index] = { ...next[index], [field]: value };
        setData('contacts', next);
    };

    const copyBillingToShipping = () => {
        setData(prevData => ({
            ...prevData,
            shipping_street: prevData.billing_street,
            shipping_city: prevData.billing_city,
            shipping_state: prevData.billing_state,
            shipping_zip: prevData.billing_zip,
            shipping_country: prevData.billing_country,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('customers.store'));
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link 
                            href={route('customers.index')} 
                            className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200"
                        >
                            <Icons.ChevronLeft />
                        </Link>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Onboard Customer</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">New enterprise account</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link 
                            href={route('customers.index')} 
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200"
                        >
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            form="customer-create-form"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg  transition-all duration-200"
                        >
                            {processing ? 'Creating...' : 'Create Customer'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Onboard Customer" />
            
            <form id="customer-create-form" onSubmit={submit} className="space-y-6">
                
                {/* Section 1: Identity & Compliance */}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Building /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Identity & Compliance</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div className="md:col-span-2">
                            <label className={labelClass}>Legal Company Name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} required />
                            {errors.name && <p className="text-terracotta text-xs font-medium mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Customer Code</label>
                            <input type="text" value={data.code} onChange={e => setData('code', e.target.value)} className={`${inputClass} font-mono`} placeholder="CUST-1234" />
                        </div>
                        <div>
                            <label className={labelClass}>Industry</label>
                            <select
                                value={data.industry_key}
                                onChange={e => setData('industry_key', e.target.value)}
                                className={inputClass}
                            >
                                <option value="">Select industry</option>
                                {INDUSTRY_OPTIONS.map((opt) => (
                                    <option key={opt} value={opt}>{opt}</option>
                                ))}
                            </select>
                            {data.industry_key === 'Others' && (
                                <input
                                    type="text"
                                    value={data.industry_other}
                                    onChange={e => setData('industry_other', e.target.value)}
                                    className={`${inputClass} mt-2`}
                                    placeholder="Specify industry"
                                />
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Website</label>
                            <input type="url" value={data.website} onChange={e => setData('website', e.target.value)} className={inputClass} placeholder="https://" />
                        </div>
                        <div>
                            <label className={labelClass}>LHDN TIN</label>
                            <input type="text" value={data.tin} onChange={e => setData('tin', e.target.value)} className={inputClass} placeholder="C1234567890" />
                            {errors.tin && <p className="text-terracotta text-xs font-medium mt-1">{errors.tin}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>SSM BRN</label>
                            <input type="text" value={data.brn} onChange={e => setData('brn', e.target.value)} className={inputClass} placeholder="202401021234" />
                            {errors.brn && <p className="text-terracotta text-xs font-medium mt-1">{errors.brn}</p>}
                        </div>
                    </div>
                </div>

                {/* Section 2: Financial & Contact */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.CreditCard /></span>
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Financial</h3>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Credit Limit (RM)</label>
                                <input type="number" step="0.01" min="0" value={data.credit_limit} onChange={e => setData('credit_limit', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className={labelClass}>Payment Terms</label>
                                <select
                                    value={String(data.payment_terms_select)}
                                    onChange={e => setData('payment_terms_select', e.target.value)}
                                    className={inputClass}
                                >
                                    {PAYMENT_TERM_PRESETS.map((p) => (
                                        <option key={p.value} value={String(p.value)}>{p.label}</option>
                                    ))}
                                    <option value={PAYMENT_TERM_CUSTOM}>Custom (days)</option>
                                </select>
                                {data.payment_terms_select === PAYMENT_TERM_CUSTOM && (
                                    <input
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.payment_terms_custom}
                                        onChange={e => setData('payment_terms_custom', e.target.value)}
                                        className={`${inputClass} mt-2`}
                                        placeholder="0–365"
                                    />
                                )}
                            </div>
                            <div className="col-span-2">
                                <label className={labelClass}>Currency</label>
                                <input type="text" value={data.currency} className={inputReadonlyClass} readOnly />
                            </div>
                            <div className="col-span-2">
                                <label className={labelClass}>Account Manager</label>
                                <select
                                    value={data.account_manager_id || ''}
                                    onChange={e => setData('account_manager_id', e.target.value || '')}
                                    className={inputClass}
                                >
                                    <option value="">— None —</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>{u.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Phone /></span>
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Primary Contact</h3>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className={labelClass}>Contact Person</label>
                                <input type="text" placeholder="Full name" value={data.contact_person} onChange={e => setData('contact_person', e.target.value)} className={inputClass} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>Email</label>
                                    <input type="email" placeholder="billing@company.com" value={data.email} onChange={e => setData('email', e.target.value)} className={inputClass} required />
                                    {errors.email && <p className="text-terracotta text-xs font-medium mt-1">{errors.email}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Phone</label>
                                    <input type="text" placeholder="+60 3 1234 5678" value={data.phone} onChange={e => setData('phone', e.target.value)} className={inputClass} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Additional contacts */}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Additional contacts</h3>
                        <button type="button" onClick={addContact} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt">
                            + Add contact
                        </button>
                    </div>
                    <div className="space-y-3">
                        {(data.contacts || []).map((contact, index) => (
                            <div key={index} className="grid grid-cols-1 md:grid-cols-12 md:items-center gap-3 p-3 border border-border-warm rounded-xl bg-cream/50">
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Name</label>
                                    <input type="text" value={contact.name} onChange={e => updateContact(index, 'name', e.target.value)} className={inputClass} placeholder="Name" />
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Email</label>
                                    <input type="email" value={contact.email} onChange={e => updateContact(index, 'email', e.target.value)} className={inputClass} placeholder="email@example.com" />
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Phone</label>
                                    <input type="text" value={contact.phone} onChange={e => updateContact(index, 'phone', e.target.value)} className={inputClass} placeholder="+60" />
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Type</label>
                                    <select value={contact.type} onChange={e => updateContact(index, 'type', e.target.value)} className={inputClass}>
                                        <option value="billing">Billing</option>
                                        <option value="finance">Finance</option>
                                        <option value="operations">Operations</option>
                                    </select>
                                </div>
                                <div className="md:col-span-2 flex items-center gap-2 min-h-[42px]">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" checked={!!contact.is_primary} onChange={e => updateContact(index, 'is_primary', e.target.checked)} className="rounded border-border-warm" />
                                        <span className="text-xs font-medium text-ink">Primary</span>
                                    </label>
                                </div>
                                <div className="md:col-span-2 flex items-center min-h-[42px]">
                                    <button type="button" onClick={() => removeContact(index)} className="text-ink-muted hover:text-terracotta text-xs font-medium">Remove</button>
                                </div>
                            </div>
                        ))}
                        {(data.contacts || []).length === 0 && (
                            <p className="text-ink-muted text-sm">Optional. Add billing, finance, or operations contacts later in Edit.</p>
                        )}
                    </div>
                </div>

                {/* Section 3: Addresses */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Location /></span>
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Billing Address</h3>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className={labelClass}>Street</label>
                                <textarea placeholder="Address line 1" value={data.billing_street} onChange={e => setData('billing_street', e.target.value)} className={`${inputClass} resize-none h-20`} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>City</label>
                                    <input type="text" placeholder="Kuala Lumpur" value={data.billing_city} onChange={e => setData('billing_city', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Postcode</label>
                                    <input type="text" placeholder="50000" value={data.billing_zip} onChange={e => setData('billing_zip', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>State</label>
                                    <select value={data.billing_state} onChange={e => setData('billing_state', e.target.value)} className={inputClass}>
                                        <option value="">Select</option>
                                        {malaysianStates.map(state => <option key={state} value={state}>{state}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className={labelClass}>Country</label>
                                    <input type="text" value={data.billing_country} className={inputReadonlyClass} readOnly />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <div className="flex items-center justify-between mb-6">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Truck /></span>
                                <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Shipping Address</h3>
                            </div>
                            <button 
                                type="button" 
                                onClick={copyBillingToShipping} 
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt transition-colors"
                            >
                                <Icons.ArrowPath /> Same as billing
                            </button>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className={labelClass}>Street</label>
                                <textarea placeholder="Address line 1" value={data.shipping_street} onChange={e => setData('shipping_street', e.target.value)} className={`${inputClass} resize-none h-20`} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>City</label>
                                    <input type="text" placeholder="Kuala Lumpur" value={data.shipping_city} onChange={e => setData('shipping_city', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Postcode</label>
                                    <input type="text" placeholder="50000" value={data.shipping_zip} onChange={e => setData('shipping_zip', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>State</label>
                                    <select value={data.shipping_state} onChange={e => setData('shipping_state', e.target.value)} className={inputClass}>
                                        <option value="">Select</option>
                                        {malaysianStates.map(state => <option key={state} value={state}>{state}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className={labelClass}>Country</label>
                                    <input type="text" value={data.shipping_country} className={inputReadonlyClass} readOnly />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Section 4: Internal Notes */}
                <div className="bg-cream p-6 rounded-2xl relative overflow-hidden shadow-lg">
                    <div className="absolute top-0 right-0 w-32 h-32 bg-terracotta/10 rounded-full -translate-y-1/2 translate-x-1/2" />
                    <div className="relative">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="p-2 rounded-xl bg-terracotta/20 text-terracotta"><Icons.DocumentText /></span>
                            <h3 className="font-semibold text-terracotta text-sm uppercase tracking-wider">Internal Notes</h3>
                        </div>
                        <textarea 
                            placeholder="Private notes about this customer..." 
                            value={data.internal_notes} 
                            onChange={e => setData('internal_notes', e.target.value)} 
                            className="w-full bg-ink/80 border border-ink rounded-xl p-4 text-ink-muted text-sm placeholder-ink-muted/60 focus:ring-2 focus:ring-terracotta focus:border-transparent h-28 resize-none"
                        />
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}