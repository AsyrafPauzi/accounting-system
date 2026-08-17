import React from 'react';
import {
    INDUSTRY_OPTIONS,
    PAYMENT_TERM_PRESETS,
    PAYMENT_TERM_CUSTOM,
    MALAYSIAN_STATES,
    ID_TYPE_OPTIONS,
    idNumberLabel,
    myinvoisPartyGapEntries,
    myinvoisIdNumberRequired,
} from '@/constants/customerFormOptions';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors scroll-mt-28';
const inputReadonlyClass = `${inputClass} text-ink-muted bg-cream`;
const labelClass = 'flex items-center gap-1.5 text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none min-h-4';

function inputCls(missing, extra = '') {
    return `${inputClass} ${extra} ${missing ? 'border-amber-400 bg-amber-50/70 ring-1 ring-amber-200 focus:ring-amber-500 focus:border-amber-500' : ''}`.trim();
}

const Icons = {
    Building: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    Shield: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
    Location: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    CreditCard: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

function Card({ icon: Icon, title, hint, extra, children }) {
    return (
        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
            <div className="flex items-start justify-between gap-3 mb-5">
                <div className="flex items-start gap-2 min-w-0">
                    <span className="p-2 rounded-xl bg-surface-alt text-ink shrink-0"><Icon /></span>
                    <div className="min-w-0">
                        <h3 className="font-semibold text-ink text-sm">{title}</h3>
                        {hint && <p className="text-xs text-ink-muted mt-0.5">{hint}</p>}
                    </div>
                </div>
                {extra}
            </div>
            {children}
        </div>
    );
}

function Field({ label, required, myinvois, missing, hint, error, className = '', children }) {
    return (
        <div className={`min-w-0 ${className}`}>
            <label className={`${labelClass} ${missing ? 'text-amber-800' : ''}`}>
                <span>{label}{required ? <span className="text-terracotta"> *</span> : null}</span>
                {myinvois && (
                    <span className={`normal-case tracking-normal font-semibold px-1.5 py-0.5 rounded ${missing ? 'bg-amber-100 text-amber-900' : 'bg-cream text-ink-muted'}`}>
                        MyInvois
                    </span>
                )}
            </label>
            {children}
            {hint && !error && <p className="mt-1 text-[11px] text-ink-muted leading-snug">{hint}</p>}
            {error && <p className="mt-1 text-xs text-terracotta">{error}</p>}
        </div>
    );
}

function AddressFields({ prefix, data, setData, errors, states, missing = {} }) {
    const myinvois = prefix === 'billing';
    return (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
            <Field label="Street" className="md:col-span-4" error={errors[`${prefix}_street`]} myinvois={myinvois} missing={missing[`${prefix}_street`]}>
                <textarea
                    id={myinvois ? 'myinvois-billing_street' : undefined}
                    value={data[`${prefix}_street`] || ''}
                    onChange={(e) => setData(`${prefix}_street`, e.target.value)}
                    placeholder="Lot / unit, street"
                    className={`${inputCls(missing[`${prefix}_street`])} h-20 resize-none`}
                />
            </Field>
            <Field label="City" error={errors[`${prefix}_city`]} myinvois={myinvois} missing={missing[`${prefix}_city`]}>
                <input id={myinvois ? 'myinvois-billing_city' : undefined} type="text" value={data[`${prefix}_city`] || ''} onChange={(e) => setData(`${prefix}_city`, e.target.value)} placeholder="Kuala Lumpur" className={inputCls(missing[`${prefix}_city`])} />
            </Field>
            <Field label="Postcode" error={errors[`${prefix}_zip`]} myinvois={myinvois} missing={missing[`${prefix}_zip`]}>
                <input id={myinvois ? 'myinvois-billing_zip' : undefined} type="text" value={data[`${prefix}_zip`] || ''} onChange={(e) => setData(`${prefix}_zip`, e.target.value)} placeholder="50000" className={inputCls(missing[`${prefix}_zip`])} />
            </Field>
            <Field label="State" error={errors[`${prefix}_state`]} myinvois={myinvois} missing={missing[`${prefix}_state`]}>
                <select id={myinvois ? 'myinvois-billing_state' : undefined} value={data[`${prefix}_state`] || ''} onChange={(e) => setData(`${prefix}_state`, e.target.value)} className={inputCls(missing[`${prefix}_state`])}>
                    <option value="">Select</option>
                    {states.map((state) => <option key={state} value={state}>{state}</option>)}
                </select>
            </Field>
            <Field label="Country">
                <input type="text" value={data[`${prefix}_country`] || 'Malaysia'} readOnly className={inputReadonlyClass} />
            </Field>
        </div>
    );
}

export default function CustomerForm({
    formId,
    data,
    setData,
    errors = {},
    onSubmit,
    users = [],
    mode = 'create',
}) {
    const gaps = myinvoisPartyGapEntries(data);
    const missing = Object.fromEntries(gaps.map((g) => [g.key, true]));
    const needId = myinvoisIdNumberRequired(data);
    const ready = gaps.length === 0;

    const addContact = () => {
        setData('contacts', [...(data.contacts || []), { id: null, name: '', email: '', phone: '', type: 'billing', is_primary: false }]);
    };
    const removeContact = (index) => {
        setData('contacts', (data.contacts || []).filter((_, i) => i !== index));
    };
    const updateContact = (index, field, value) => {
        const next = [...(data.contacts || [])];
        next[index] = { ...next[index], [field]: value };
        setData('contacts', next);
    };
    const copyBillingToShipping = () => {
        setData((prev) => ({
            ...prev,
            shipping_street: prev.billing_street,
            shipping_city: prev.billing_city,
            shipping_state: prev.billing_state,
            shipping_zip: prev.billing_zip,
            shipping_country: prev.billing_country,
        }));
    };

    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
            {Object.keys(errors).length > 0 && (
                <div className="bg-terracotta/10 border border-terracotta/30 rounded-xl p-4 text-terracotta text-sm">
                    <p className="font-semibold mb-2">Please fix the following:</p>
                    <ul className="list-disc list-inside space-y-0.5">
                        {Object.entries(errors).map(([field, message]) => (
                            <li key={field}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Card icon={Icons.Building} title="Customer" hint="Name and how you reach them. Email is used for invoices and statements.">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="Legal name" required myinvois missing={missing.name} error={errors.name} className="md:col-span-2" hint="As registered with SSM / LHDN">
                        <input id="myinvois-name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputCls(missing.name)} required />
                    </Field>
                    <Field label="Customer code" error={errors.code} hint={mode === 'edit' ? 'Locked after create' : 'Internal reference'}>
                        <input
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            readOnly={mode === 'edit'}
                            className={`${mode === 'edit' ? inputReadonlyClass : inputClass} font-mono`}
                        />
                    </Field>
                    {mode === 'edit' && (
                        <Field label="Status">
                            <select value={String(data.is_active)} onChange={(e) => setData('is_active', e.target.value === 'true')} className={inputClass}>
                                <option value="true">Active</option>
                                <option value="false">Suspended</option>
                            </select>
                        </Field>
                    )}
                    <Field label="Contact person" error={errors.contact_person} className="md:col-span-2">
                        <input type="text" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} placeholder="Billing contact" className={inputClass} />
                    </Field>
                    <Field label="Email" required error={errors.email} hint="Invoices and statements">
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="billing@company.com" className={inputClass} required />
                    </Field>
                    <Field label="Phone" myinvois missing={missing.phone} error={errors.phone} hint="Needed on e-invoices">
                        <input id="myinvois-phone" type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+60 3 1234 5678" className={inputCls(missing.phone)} />
                    </Field>
                </div>
            </Card>

            <Card
                icon={Icons.Shield}
                title="MyInvois"
                hint="LHDN needs these on every e-invoice. You can save now and complete them before submitting."
                extra={
                    <span className={`shrink-0 text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md ${ready ? 'bg-forest/10 text-forest' : 'bg-amber-50 text-amber-800'}`}>
                        {ready ? 'Ready' : `${gaps.length} missing`}
                    </span>
                }
            >
                {!ready && (
                    <div className="mb-4 flex flex-wrap gap-1.5">
                        {gaps.map((gap) => (
                            <a
                                key={gap.key}
                                href={`#myinvois-${gap.key}`}
                                className="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 hover:bg-amber-200"
                            >
                                {gap.label}
                            </a>
                        ))}
                    </div>
                )}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="LHDN TIN" myinvois missing={missing.tin} error={errors.tin} hint="e.g. C1234567890 or EI00000000010 for general public">
                        <input id="myinvois-tin" type="text" value={data.tin} onChange={(e) => setData('tin', e.target.value)} className={inputCls(missing.tin, 'font-mono')} placeholder="C1234567890" />
                    </Field>
                    <Field label="ID type" error={errors.identification_type}>
                        <select value={data.identification_type || 'BRN'} onChange={(e) => setData('identification_type', e.target.value)} className={inputClass}>
                            {ID_TYPE_OPTIONS.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                        </select>
                    </Field>
                    <Field label={idNumberLabel(data.identification_type)} myinvois={needId} missing={missing.brn} error={errors.brn} hint={needId ? 'Must match MyInvois taxpayer search' : 'Not required for general public TIN'}>
                        <input id="myinvois-brn" type="text" value={data.brn} onChange={(e) => setData('brn', e.target.value)} className={inputCls(missing.brn, 'font-mono')} placeholder={data.identification_type === 'BRN' ? '202401021234' : 'As on MyInvois'} />
                    </Field>
                    <Field label="SST number" error={errors.sst_number} hint="Leave blank if not SST-registered (sent as NA)">
                        <input type="text" value={data.sst_number || ''} onChange={(e) => setData('sst_number', e.target.value)} className={`${inputClass} font-mono`} placeholder="Optional" />
                    </Field>
                </div>
            </Card>

            <Card
                icon={Icons.Location}
                title="Addresses"
                hint="Billing address goes on the e-invoice. Shipping is for delivery only."
                extra={
                    <button type="button" onClick={copyBillingToShipping} className="text-xs font-semibold text-terracotta hover:underline shrink-0">
                        Copy billing → shipping
                    </button>
                }
            >
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <p className="text-xs font-semibold text-ink mb-3 flex items-center gap-1.5">
                            Billing
                            <span className={`normal-case tracking-normal text-[10px] font-semibold px-1.5 py-0.5 rounded ${missing.billing_street || missing.billing_city || missing.billing_zip || missing.billing_state ? 'bg-amber-100 text-amber-900' : 'bg-cream text-ink-muted'}`}>
                                MyInvois
                            </span>
                        </p>
                        <AddressFields prefix="billing" data={data} setData={setData} errors={errors} states={MALAYSIAN_STATES} missing={missing} />
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-ink mb-3">Shipping</p>
                        <AddressFields prefix="shipping" data={data} setData={setData} errors={errors} states={MALAYSIAN_STATES} />
                    </div>
                </div>
            </Card>

            <Card icon={Icons.CreditCard} title="Credit & statements" hint="Terms, credit control, and how invoices are sent. Used for audit and collections.">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="Payment terms" error={errors.payment_terms}>
                        <select value={String(data.payment_terms_select)} onChange={(e) => setData('payment_terms_select', e.target.value)} className={inputClass}>
                            {PAYMENT_TERM_PRESETS.map((p) => <option key={p.value} value={String(p.value)}>{p.label}</option>)}
                            <option value={PAYMENT_TERM_CUSTOM}>Custom (days)</option>
                        </select>
                        {data.payment_terms_select === PAYMENT_TERM_CUSTOM && (
                            <input type="number" min={0} max={365} value={data.payment_terms_custom} onChange={(e) => setData('payment_terms_custom', e.target.value)} className={`${inputClass} mt-2`} placeholder="0–365" />
                        )}
                    </Field>
                    <Field label="Credit limit (RM)" error={errors.credit_limit}>
                        <input type="number" step="0.01" min="0" value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} className={`${inputClass} font-mono text-right`} />
                    </Field>
                    <Field label="Credit hold">
                        <select value={String(!!data.credit_hold)} onChange={(e) => setData('credit_hold', e.target.value === 'true')} className={inputClass}>
                            <option value="false">No</option>
                            <option value="true">Yes — block posting</option>
                        </select>
                    </Field>
                    <Field label="Currency">
                        <input type="text" value={data.currency || 'MYR'} readOnly className={inputReadonlyClass} />
                    </Field>
                    <Field label="Account manager" className="md:col-span-2">
                        <select value={data.account_manager_id || ''} onChange={(e) => setData('account_manager_id', e.target.value || '')} className={inputClass}>
                            <option value="">— None —</option>
                            {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Invoice delivery">
                        <select value={data.invoice_delivery_method || 'email'} onChange={(e) => setData('invoice_delivery_method', e.target.value)} className={inputClass}>
                            <option value="email">Email PDF</option>
                            <option value="none">Do not email</option>
                        </select>
                    </Field>
                    <Field label="Monthly statement">
                        <select value={String(!!data.send_statement)} onChange={(e) => setData('send_statement', e.target.value === 'true')} className={inputClass}>
                            <option value="false">No</option>
                            <option value="true">Yes</option>
                        </select>
                    </Field>
                    <Field label="Risk">
                        <select value={data.risk_rating || ''} onChange={(e) => setData('risk_rating', e.target.value)} className={inputClass}>
                            <option value="">—</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </Field>
                    <Field label="Segment">
                        <select value={data.segment || ''} onChange={(e) => setData('segment', e.target.value)} className={inputClass}>
                            <option value="">—</option>
                            <option value="SME">SME</option>
                            <option value="Enterprise">Enterprise</option>
                            <option value="Govt">Govt</option>
                        </select>
                    </Field>
                    <Field label="Region">
                        <input type="text" value={data.region || ''} onChange={(e) => setData('region', e.target.value)} placeholder="e.g. MY-KL" className={inputClass} />
                    </Field>
                    <Field label="Industry">
                        <select value={data.industry_key} onChange={(e) => setData('industry_key', e.target.value)} className={inputClass}>
                            <option value="">Select</option>
                            {INDUSTRY_OPTIONS.map((opt) => <option key={opt} value={opt}>{opt}</option>)}
                        </select>
                        {data.industry_key === 'Others' && (
                            <input type="text" value={data.industry_other} onChange={(e) => setData('industry_other', e.target.value)} className={`${inputClass} mt-2`} placeholder="Specify industry" />
                        )}
                    </Field>
                    <Field label="Website" className="md:col-span-2" error={errors.website}>
                        <input type="url" value={data.website} onChange={(e) => setData('website', e.target.value)} placeholder="https://" className={inputClass} />
                    </Field>
                </div>
            </Card>

            <Card
                icon={Icons.Users}
                title="Additional contacts"
                hint="Optional. Billing, finance, or operations people besides the primary contact."
                extra={
                    <button type="button" onClick={addContact} className="text-xs font-semibold text-terracotta hover:underline">
                        + Add contact
                    </button>
                }
            >
                {(data.contacts || []).length === 0 ? (
                    <p className="text-sm text-ink-muted">None yet.</p>
                ) : (
                    <div className="space-y-3">
                        {(data.contacts || []).map((contact, index) => (
                            <div key={contact.id || index} className="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 border border-border-warm rounded-xl bg-cream/40">
                                <Field label="Name" className="md:col-span-2">
                                    <input type="text" value={contact.name} onChange={(e) => updateContact(index, 'name', e.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Email" className="md:col-span-3">
                                    <input type="email" value={contact.email} onChange={(e) => updateContact(index, 'email', e.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Phone" className="md:col-span-2">
                                    <input type="text" value={contact.phone} onChange={(e) => updateContact(index, 'phone', e.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Type" className="md:col-span-2">
                                    <select value={contact.type} onChange={(e) => updateContact(index, 'type', e.target.value)} className={inputClass}>
                                        <option value="billing">Billing</option>
                                        <option value="finance">Finance</option>
                                        <option value="operations">Operations</option>
                                    </select>
                                </Field>
                                <div className="md:col-span-2 flex items-end gap-3 pb-1">
                                    <label className="inline-flex items-center gap-2 text-xs font-medium text-ink cursor-pointer">
                                        <input type="checkbox" checked={!!contact.is_primary} onChange={(e) => updateContact(index, 'is_primary', e.target.checked)} className="rounded border-border-warm text-terracotta" />
                                        Primary
                                    </label>
                                    <button type="button" onClick={() => removeContact(index)} className="text-xs font-medium text-ink-muted hover:text-terracotta">Remove</button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            <Card icon={Icons.Document} title="Internal notes" hint="Private. Not printed on invoices or sent to the customer.">
                <textarea
                    value={data.internal_notes}
                    onChange={(e) => setData('internal_notes', e.target.value)}
                    placeholder="Credit history, collection notes, who to call…"
                    className={`${inputClass} h-28 resize-none`}
                />
            </Card>
        </form>
    );
}
