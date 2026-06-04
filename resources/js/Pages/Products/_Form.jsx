import React from 'react';
import { Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
};

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

/**
 * Shared product form. Used by both Create and Edit pages so behaviour stays
 * consistent. The caller owns the Inertia `useForm` instance and submit
 * handler.
 */
export default function ProductForm({ data, setData, errors, processing, onSubmit, incomeAccounts = [], submitLabel = 'Save product' }) {
    return (
        <div className="max-w-3xl mx-auto p-4 sm:p-6">
            <Link href={route('products.index')} className="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink mb-4">
                <Icons.ChevronLeft /> Back to catalogue
            </Link>

            <form onSubmit={onSubmit} className="space-y-6">
                {/* Identity */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <h2 className="text-sm font-display font-medium text-ink">What are you selling?</h2>
                    </div>
                    <div className="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="sm:col-span-2">
                            <label className={labelClass}>Name *</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. SEO consulting (monthly retainer)"
                                className={inputClass}
                                required
                            />
                            {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Code (optional)</label>
                            <input
                                type="text"
                                value={data.code || ''}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="SEO-MO"
                                className={inputClass + ' font-mono'}
                            />
                            {errors.code && <p className="mt-1 text-xs text-terracotta">{errors.code}</p>}
                        </div>
                        <div className="sm:col-span-3">
                            <label className={labelClass}>Description</label>
                            <textarea
                                value={data.description || ''}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                placeholder="Appears as the default description on each invoice line. You can still tweak it per invoice."
                                className={inputClass}
                            />
                            {errors.description && <p className="mt-1 text-xs text-terracotta">{errors.description}</p>}
                        </div>
                    </div>
                </div>

                {/* Pricing */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <h2 className="text-sm font-display font-medium text-ink">Default pricing</h2>
                    </div>
                    <div className="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className={labelClass}>Unit price (RM) *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.unit_price}
                                onChange={(e) => setData('unit_price', e.target.value)}
                                className={inputClass + ' text-right font-mono'}
                                required
                            />
                            {errors.unit_price && <p className="mt-1 text-xs text-terracotta">{errors.unit_price}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Tax rate (%)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={data.tax_rate}
                                onChange={(e) => setData('tax_rate', e.target.value)}
                                placeholder="6.00"
                                className={inputClass + ' text-right font-mono'}
                            />
                            <p className="mt-1.5 text-xs text-ink-muted">e.g. 6 for 6% SST. Leave 0 if not taxable.</p>
                            {errors.tax_rate && <p className="mt-1 text-xs text-terracotta">{errors.tax_rate}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Default revenue account</label>
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
                            <p className="mt-1.5 text-xs text-ink-muted">Where revenue from this product is booked.</p>
                            {errors.account_code && <p className="mt-1 text-xs text-terracotta">{errors.account_code}</p>}
                        </div>
                    </div>
                </div>

                {/* Status */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-6 flex items-start gap-3">
                        <input
                            id="is_active"
                            type="checkbox"
                            checked={!!data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                            className="mt-1 w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta"
                        />
                        <label htmlFor="is_active" className="cursor-pointer">
                            <p className="text-sm font-semibold text-ink">Active</p>
                            <p className="text-xs text-ink-muted mt-0.5">When inactive, this product won't appear in the line-item picker but past invoices keep their reference.</p>
                        </label>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                    <Link href={route('products.index')} className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {processing ? 'Saving…' : submitLabel}
                    </button>
                </div>
            </form>
        </div>
    );
}
