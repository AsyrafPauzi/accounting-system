import React from 'react';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'flex items-center gap-1.5 text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none min-h-4';

const CLASSIFICATION_OPTIONS = [
    { value: '022', label: '022 — Professional services' },
    { value: '011', label: '011 — General merchandise' },
    { value: '001', label: '001 — Standard rate' },
    { value: '010', label: '010 — Exempt' },
];

const Icons = {
    Box: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14L12 11m0 0L4 7m8 4v10" /></svg>,
    Currency: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Shield: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
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

function Field({ label, required, myinvois, hint, error, className = '', children }) {
    return (
        <div className={`min-w-0 ${className}`}>
            <label className={labelClass}>
                <span>{label}{required ? <span className="text-terracotta"> *</span> : null}</span>
                {myinvois && (
                    <span className="normal-case tracking-normal font-semibold px-1.5 py-0.5 rounded bg-cream text-ink-muted">
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

export default function ProductForm({
    formId = 'product-form',
    data,
    setData,
    errors = {},
    onSubmit,
    incomeAccounts = [],
}) {
    const knownClass = CLASSIFICATION_OPTIONS.some((opt) => opt.value === String(data.classification_code));

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

            <Card icon={Icons.Box} title="Product" hint="Shown in the invoice line picker. You can still change the wording on each invoice.">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <Field label="Name" required error={errors.name} className="md:col-span-2">
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. SEO consulting (monthly retainer)"
                            className={inputClass}
                            required
                        />
                    </Field>
                    <Field label="Code" error={errors.code} hint="Optional SKU / internal reference">
                        <input
                            type="text"
                            value={data.code || ''}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder="SEO-MO"
                            className={`${inputClass} font-mono`}
                        />
                    </Field>
                    <Field label="Status">
                        <select
                            value={String(!!data.is_active)}
                            onChange={(e) => setData('is_active', e.target.value === 'true')}
                            className={inputClass}
                        >
                            <option value="true">Active</option>
                            <option value="false">Inactive — hide from picker</option>
                        </select>
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-4" hint="Default line text. Editable per invoice.">
                        <textarea
                            value={data.description || ''}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="What appears on the invoice line"
                            className={`${inputClass} h-20 resize-none`}
                        />
                    </Field>
                </div>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card icon={Icons.Currency} title="Pricing" hint="Defaults for new lines. Existing invoices stay as they were.">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 items-start">
                        <Field label="Unit price (RM)" required error={errors.unit_price}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.unit_price}
                                onChange={(e) => setData('unit_price', e.target.value)}
                                className={`${inputClass} text-right font-mono`}
                                required
                            />
                        </Field>
                        <Field label="Tax rate (%)" error={errors.tax_rate} hint="0 if tax is added on the invoice">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={data.tax_rate}
                                onChange={(e) => setData('tax_rate', e.target.value)}
                                placeholder="0.00"
                                className={`${inputClass} text-right font-mono`}
                            />
                        </Field>
                    </div>
                </Card>

                <Card icon={Icons.Shield} title="Accounting" hint="Used when this product is picked on an invoice or e-invoice.">
                    <div className="grid grid-cols-1 gap-y-5">
                        <Field label="Revenue account" error={errors.account_code} hint="Leave blank to pick the account on each invoice">
                            <select
                                value={data.account_code || ''}
                                onChange={(e) => setData('account_code', e.target.value)}
                                className={inputClass}
                            >
                                <option value="">— Pick on each invoice —</option>
                                {incomeAccounts.map((a) => (
                                    <option key={a.value} value={a.value}>{a.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Classification"
                            myinvois
                            error={errors.classification_code}
                            hint="Copied onto invoice lines for LHDN"
                        >
                            <select
                                value={data.classification_code || '022'}
                                onChange={(e) => setData('classification_code', e.target.value)}
                                className={inputClass}
                            >
                                {CLASSIFICATION_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                                {!knownClass && data.classification_code && (
                                    <option value={data.classification_code}>{data.classification_code}</option>
                                )}
                            </select>
                        </Field>
                    </div>
                </Card>
            </div>
        </form>
    );
}
