import React from 'react';
import {
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
    Location: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>,
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

export default function SupplierForm({ formId, data, setData, errors = {}, onSubmit, mode = 'create' }) {
    const gaps = myinvoisPartyGapEntries(data);
    const missing = Object.fromEntries(gaps.map((g) => [g.key, true]));
    const needId = myinvoisIdNumberRequired(data);
    const ready = gaps.length === 0;

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

            <Card icon={Icons.Building} title="Supplier" hint="Vendor name and how you reach them. Used on bills and self-billed e-invoices.">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="Legal name" required myinvois missing={missing.name} error={errors.name} className="md:col-span-2">
                        <input id="myinvois-name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputCls(missing.name)} required />
                    </Field>
                    <Field label="Supplier code" error={errors.code}>
                        <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} className={`${inputClass} font-mono`} required />
                    </Field>
                    <Field label="Status">
                        <select value={String(!!data.is_active)} onChange={(e) => setData('is_active', e.target.value === 'true')} className={inputClass}>
                            <option value="true">Active</option>
                            <option value="false">Suspended</option>
                        </select>
                    </Field>
                    <Field label="Contact person" className="md:col-span-2">
                        <input type="text" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Phone" myinvois missing={missing.phone} error={errors.phone} hint="Needed on self-billed e-invoices">
                        <input id="myinvois-phone" type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+60 3 1234 5678" className={inputCls(missing.phone)} />
                    </Field>
                    <Field label="Payment terms (days)" error={errors.payment_terms}>
                        <input type="number" min="0" max="365" value={data.payment_terms} onChange={(e) => setData('payment_terms', e.target.value)} className={inputClass} required />
                    </Field>
                    <Field label="Currency">
                        <input type="text" value={data.currency || 'MYR'} readOnly className={inputReadonlyClass} />
                    </Field>
                    <Field label="Segment">
                        <input type="text" value={data.segment || ''} onChange={(e) => setData('segment', e.target.value)} placeholder="e.g. Office supplies" className={inputClass} />
                    </Field>
                    <Field label="Website" className="md:col-span-2" error={errors.website}>
                        <input type="url" value={data.website} onChange={(e) => setData('website', e.target.value)} placeholder="https://" className={inputClass} />
                    </Field>
                </div>
            </Card>

            <Card
                icon={Icons.Shield}
                title="MyInvois"
                hint="Required to submit a self-billed e-invoice for this vendor. You can save now and complete later."
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
                    <Field label="LHDN TIN" myinvois missing={missing.tin} error={errors.tin}>
                        <input id="myinvois-tin" type="text" value={data.tin} onChange={(e) => setData('tin', e.target.value)} className={inputCls(missing.tin, 'font-mono')} placeholder="C1234567890" />
                    </Field>
                    <Field label="ID type">
                        <select value={data.identification_type || 'BRN'} onChange={(e) => setData('identification_type', e.target.value)} className={inputClass}>
                            {ID_TYPE_OPTIONS.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                        </select>
                    </Field>
                    <Field label={idNumberLabel(data.identification_type)} myinvois={needId} missing={missing.brn} error={errors.brn} hint={needId ? 'Must match MyInvois taxpayer search' : 'Not required for general public TIN'}>
                        <input id="myinvois-brn" type="text" value={data.brn} onChange={(e) => setData('brn', e.target.value)} className={inputCls(missing.brn, 'font-mono')} placeholder={data.identification_type === 'BRN' ? '202401021234' : 'As on MyInvois'} />
                    </Field>
                    <Field label="SST number" hint="Leave blank if not SST-registered">
                        <input type="text" value={data.sst_number || ''} onChange={(e) => setData('sst_number', e.target.value)} className={`${inputClass} font-mono`} />
                    </Field>
                </div>
            </Card>

            <Card icon={Icons.Location} title="Address" hint="Goes on self-billed e-invoices.">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="Street" className="md:col-span-4" error={errors.billing_street} myinvois missing={missing.billing_street}>
                        <textarea id="myinvois-billing_street" value={data.billing_street} onChange={(e) => setData('billing_street', e.target.value)} placeholder="Lot / unit, street" className={`${inputCls(missing.billing_street)} h-20 resize-none`} />
                    </Field>
                    <Field label="City" myinvois missing={missing.billing_city}>
                        <input id="myinvois-billing_city" type="text" value={data.billing_city} onChange={(e) => setData('billing_city', e.target.value)} placeholder="Kuala Lumpur" className={inputCls(missing.billing_city)} />
                    </Field>
                    <Field label="Postcode" myinvois missing={missing.billing_zip}>
                        <input id="myinvois-billing_zip" type="text" value={data.billing_zip} onChange={(e) => setData('billing_zip', e.target.value)} placeholder="50000" className={inputCls(missing.billing_zip)} />
                    </Field>
                    <Field label="State" myinvois missing={missing.billing_state}>
                        <select id="myinvois-billing_state" value={data.billing_state || ''} onChange={(e) => setData('billing_state', e.target.value)} className={inputCls(missing.billing_state)}>
                            <option value="">Select</option>
                            {MALAYSIAN_STATES.map((state) => <option key={state} value={state}>{state}</option>)}
                        </select>
                    </Field>
                    <Field label="Country">
                        <input type="text" value={data.billing_country || 'Malaysia'} readOnly className={inputReadonlyClass} />
                    </Field>
                </div>
            </Card>

            <Card icon={Icons.Document} title="Internal notes" hint="Private. Not printed on bills.">
                <textarea value={data.internal_notes} onChange={(e) => setData('internal_notes', e.target.value)} className={`${inputClass} h-28 resize-none`} placeholder="Payment habits, who to call…" />
            </Card>
        </form>
    );
}
