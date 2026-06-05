import React, { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { formatCurrency, currencyRoundStep, currencyDecimals } from '@/utils/currency';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

const blankItem = () => ({
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_amount: 0,
    tax_rate: 0,
    product_id: null,
    item_classification: '022',
});

/**
 * Shared estimate form. Used by both Create and Edit pages.
 * Caller owns the Inertia useForm instance and submit handler.
 */
export default function EstimateForm({
    data, setData, errors, processing, onSubmit, customers = [], products = [],
    base_currency = 'MYR', submitLabel = 'Save estimate',
}) {
    const updateItem = (index, field, value) => {
        const next = [...data.items];
        next[index] = { ...next[index], [field]: value };
        setData('items', next);
    };

    const addItem = () => setData('items', [...data.items, blankItem()]);

    const removeItem = (index) => {
        if (data.items.length <= 1) return;
        setData('items', data.items.filter((_, i) => i !== index));
    };

    const applyProduct = (index, productId) => {
        if (!productId) return;
        const product = products.find(p => String(p.id) === String(productId));
        if (!product) return;
        const next = [...data.items];
        next[index] = {
            ...next[index],
            description: product.description ? `${product.name} — ${product.description}` : product.name,
            unit_price: parseFloat(product.unit_price) || 0,
            tax_rate: parseFloat(product.tax_rate) || 0,
            product_id: product.id,
        };
        setData('items', next);
    };

    const totals = useMemo(() => {
        const subtotal = data.items.reduce((sum, i) => sum + (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0)), 0);
        const discount = data.items.reduce((sum, i) => sum + parseFloat(i.discount_amount || 0), 0);
        const tax = data.items.reduce((sum, i) => {
            const lineNet = (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0)) - parseFloat(i.discount_amount || 0);
            return sum + (lineNet * parseFloat(i.tax_rate || 0) / 100);
        }, 0);
        const shipping = parseFloat(data.shipping_amount || 0);
        const raw = (subtotal - discount) + tax + shipping;
        const step = currencyRoundStep(data.currency || base_currency);
        const rounded = Math.round(raw / step) * step;
        const adjustment = rounded - raw;
        return { subtotal, discount, tax, shipping, raw, rounded, adjustment };
    }, [data.items, data.shipping_amount, data.currency, base_currency]);

    const decimals = currencyDecimals(data.currency || base_currency);

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            {/* Header */}
            <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                    <h2 className="text-sm font-display font-medium text-ink">Estimate details</h2>
                </div>
                <div className="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label className={labelClass}>Estimate # *</label>
                        <input
                            type="text"
                            value={data.estimate_number}
                            onChange={e => setData('estimate_number', e.target.value)}
                            className={inputClass + ' font-mono'}
                            required
                        />
                        {errors.estimate_number && <p className="mt-1 text-xs text-terracotta">{errors.estimate_number}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Customer *</label>
                        <select
                            value={data.customer_id}
                            onChange={e => setData('customer_id', e.target.value)}
                            className={inputClass}
                            required
                        >
                            <option value="">— Select a customer —</option>
                            {customers.map(c => (
                                <option key={c.id} value={c.id}>{c.name}{c.tin ? ` · ${c.tin}` : ''}</option>
                            ))}
                        </select>
                        {errors.customer_id && <p className="mt-1 text-xs text-terracotta">{errors.customer_id}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Currency</label>
                        <select
                            value={data.currency}
                            onChange={e => setData('currency', e.target.value)}
                            className={inputClass}
                        >
                            {['MYR', 'IDR', 'SGD', 'USD', 'EUR', 'GBP', 'JPY'].map(c => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className={labelClass}>Issue date *</label>
                        <input
                            type="date"
                            value={data.issue_date}
                            onChange={e => setData('issue_date', e.target.value)}
                            className={inputClass}
                            required
                        />
                        {errors.issue_date && <p className="mt-1 text-xs text-terracotta">{errors.issue_date}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Valid until</label>
                        <input
                            type="date"
                            value={data.expiry_date || ''}
                            onChange={e => setData('expiry_date', e.target.value)}
                            className={inputClass}
                        />
                        {errors.expiry_date && <p className="mt-1 text-xs text-terracotta">{errors.expiry_date}</p>}
                    </div>

                    {data.currency !== base_currency && (
                        <div>
                            <label className={labelClass}>Exchange rate (1 {data.currency} = ? {base_currency})</label>
                            <input
                                type="number"
                                step="0.000001"
                                min="0.000001"
                                value={data.exchange_rate || ''}
                                onChange={e => setData('exchange_rate', e.target.value)}
                                className={inputClass + ' font-mono text-right'}
                            />
                            {errors.exchange_rate && <p className="mt-1 text-xs text-terracotta">{errors.exchange_rate}</p>}
                        </div>
                    )}
                </div>
            </div>

            {/* Line items */}
            <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                    <h2 className="text-sm font-display font-medium text-ink">Line items</h2>
                    <button type="button" onClick={addItem} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt hover:bg-cream border border-border-warm transition-colors">
                        <Icons.Plus /> Add line
                    </button>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <colgroup>
                            <col className="w-[42%]" />
                            <col className="w-[10%]" />
                            <col className="w-[14%]" />
                            <col className="w-[10%]" />
                            <col className="w-[10%]" />
                            <col className="w-[10%]" />
                            <col className="w-[4%]" />
                        </colgroup>
                        <thead className="bg-cream/50 text-[10px] font-display text-ink-muted uppercase tracking-widest">
                            <tr>
                                <th className="px-3 py-3 text-left">Description</th>
                                <th className="px-3 py-3 text-center">Qty</th>
                                <th className="px-3 py-3 text-right">Unit price</th>
                                <th className="px-3 py-3 text-right">Discount</th>
                                <th className="px-3 py-3 text-center">Tax %</th>
                                <th className="px-3 py-3 text-right">Total</th>
                                <th className="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {data.items.map((item, index) => {
                                const lineTotal = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - parseFloat(item.discount_amount || 0);
                                return (
                                    <tr key={index}>
                                        <td className="px-3 py-3 align-top">
                                            {products.length > 0 && (
                                                <select
                                                    value=""
                                                    onChange={e => { applyProduct(index, e.target.value); e.target.value = ''; }}
                                                    className="mb-1.5 w-full border border-border-warm rounded-lg text-[10px] font-semibold text-ink-muted bg-cream/50 hover:bg-cream py-1 px-2 focus:ring-1 focus:ring-terracotta uppercase tracking-wider cursor-pointer"
                                                >
                                                    <option value="">+ Pick from catalogue</option>
                                                    {products.map(p => (
                                                        <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>
                                                    ))}
                                                </select>
                                            )}
                                            <input
                                                type="text"
                                                value={item.description}
                                                onChange={e => updateItem(index, 'description', e.target.value)}
                                                placeholder="What are you quoting?"
                                                className="w-full border border-border-warm rounded-lg py-2 px-2 text-sm focus:ring-1 focus:ring-terracotta"
                                                required
                                            />
                                            {errors[`items.${index}.description`] && <p className="mt-1 text-xs text-terracotta">{errors[`items.${index}.description`]}</p>}
                                        </td>
                                        <td className="px-3 py-3 align-top">
                                            <input type="number" step="0.01" min="0.01" value={item.quantity} onChange={e => updateItem(index, 'quantity', e.target.value)} className="w-full text-center border border-border-warm rounded-lg py-2 px-2 text-sm font-mono focus:ring-1 focus:ring-terracotta" />
                                        </td>
                                        <td className="px-3 py-3 align-top">
                                            <input type="number" step="0.01" min="0" value={item.unit_price} onChange={e => updateItem(index, 'unit_price', e.target.value)} className="w-full text-right border border-border-warm rounded-lg py-2 px-2 text-sm font-mono focus:ring-1 focus:ring-terracotta" />
                                        </td>
                                        <td className="px-3 py-3 align-top">
                                            <input type="number" step="0.01" min="0" value={item.discount_amount || 0} onChange={e => updateItem(index, 'discount_amount', e.target.value)} className="w-full text-right border border-border-warm rounded-lg py-2 px-2 text-sm font-mono text-terracotta focus:ring-1 focus:ring-terracotta" />
                                        </td>
                                        <td className="px-3 py-3 align-top">
                                            <select value={item.tax_rate} onChange={e => updateItem(index, 'tax_rate', e.target.value)} className="w-full text-center border border-border-warm rounded-lg py-2 px-2 text-sm focus:ring-1 focus:ring-terracotta">
                                                {[0, 6, 8, 16].map(r => <option key={r} value={r}>{r}%</option>)}
                                            </select>
                                        </td>
                                        <td className="px-3 py-3 align-top text-right font-mono text-ink font-semibold">
                                            {lineTotal.toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}
                                        </td>
                                        <td className="px-3 py-3 align-top text-center">
                                            <button
                                                type="button"
                                                onClick={() => removeItem(index)}
                                                disabled={data.items.length <= 1}
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-muted hover:text-terracotta hover:bg-terracotta/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                                title={data.items.length <= 1 ? 'At least one line is required' : 'Remove line'}
                                            >
                                                <Icons.Trash />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Totals + notes */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6">
                        <label className={labelClass}>Customer-facing notes</label>
                        <textarea
                            value={data.customer_notes || ''}
                            onChange={e => setData('customer_notes', e.target.value)}
                            rows={3}
                            placeholder="Payment terms, delivery details, warranty conditions…"
                            className={inputClass}
                        />
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6">
                        <label className={labelClass}>Private notes (internal only)</label>
                        <textarea
                            value={data.private_notes || ''}
                            onChange={e => setData('private_notes', e.target.value)}
                            rows={2}
                            placeholder="Never shown to the customer. Use for internal context."
                            className={inputClass}
                        />
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6 space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-ink-muted">Subtotal</span>
                        <span className="font-mono text-ink">{formatCurrency(totals.subtotal, data.currency || base_currency)}</span>
                    </div>
                    {totals.discount > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-ink-muted">Discount</span>
                            <span className="font-mono text-terracotta">- {formatCurrency(totals.discount, data.currency || base_currency)}</span>
                        </div>
                    )}
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-ink-muted">Tax</span>
                        <span className="font-mono text-ink">{formatCurrency(totals.tax, data.currency || base_currency)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <label className="text-ink-muted">Shipping</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.shipping_amount || 0}
                            onChange={e => setData('shipping_amount', e.target.value)}
                            className="w-32 text-right border border-border-warm rounded-lg py-1.5 px-2 text-sm font-mono focus:ring-1 focus:ring-terracotta"
                        />
                    </div>
                    {Math.abs(totals.adjustment) > 0.001 && (
                        <div className="flex items-center justify-between text-xs text-ink-muted/70">
                            <span>Rounding</span>
                            <span className="font-mono">{totals.adjustment >= 0 ? '+' : ''}{totals.adjustment.toFixed(decimals)}</span>
                        </div>
                    )}
                    <div className="border-t border-border-warm pt-3 flex items-center justify-between">
                        <span className="text-sm font-display font-medium text-ink">Total</span>
                        <span className="text-xl font-display font-semibold text-terracotta font-mono tabular-nums">
                            {formatCurrency(totals.rounded, data.currency || base_currency)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Actions */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                <Link href={route('estimates.index')} className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
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
    );
}
