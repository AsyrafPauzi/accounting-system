import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { currencyRoundStep, currencyDecimals, currencySymbol } from '@/utils/currency';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Product: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium text-ink-muted bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';
const lineControlClass = 'w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta';
const lineDescClass = 'block w-full min-w-0 h-8 border border-border-warm rounded-lg py-1.5 px-1.5 text-xs leading-4 font-medium text-ink bg-surface placeholder-ink-muted/60 focus:ring-1 focus:ring-terracotta resize-y';
const lineNumberClass = `${lineControlClass} font-mono tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;
const lineTaxClass = `${lineControlClass} px-0.5 pr-5 text-center tabular-nums`;
const linePickIconClass = 'relative shrink-0 h-8 w-8 rounded-lg border border-border-warm bg-cream/50 text-ink-muted hover:bg-cream hover:text-terracotta transition-colors';

const blankItem = () => ({
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_amount: 0,
    tax_rate: 0,
    product_id: null,
    item_classification: '022',
});

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

/**
 * Shared estimate form. Used by both Create and Edit pages.
 * Caller owns the Inertia useForm instance and submit handler (and page header actions).
 */
export default function EstimateForm({
    formId = 'estimate-form',
    data,
    setData,
    errors,
    onSubmit,
    customers = [],
    products = [],
    base_currency = 'MYR',
}) {
    const [showNewCustomerModal, setShowNewCustomerModal] = useState(false);
    const [newCustomers, setNewCustomers] = useState([]);
    const [quickCustomer, setQuickCustomer] = useState(initialQuickCustomer);
    const [quickCustomerErrors, setQuickCustomerErrors] = useState({});
    const [quickSubmitting, setQuickSubmitting] = useState(false);

    const customerOptions = [...customers, ...newCustomers];
    const curSym = currencySymbol(data.currency || base_currency);
    const decimals = currencyDecimals(data.currency || base_currency);

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
        const product = products.find((p) => String(p.id) === String(productId));
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

    const totals = useMemo(() => {
        const subtotal = data.items.reduce((sum, i) => sum + (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0)), 0);
        const discount = data.items.reduce((sum, i) => sum + parseFloat(i.discount_amount || 0), 0);
        const tax = data.items.reduce((sum, i) => {
            const lineNet = (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0)) - parseFloat(i.discount_amount || 0);
            return sum + (Math.max(0, lineNet) * parseFloat(i.tax_rate || 0) / 100);
        }, 0);
        const shipping = parseFloat(data.shipping_amount || 0);
        const raw = (subtotal - discount) + tax + shipping;
        const step = currencyRoundStep(data.currency || base_currency);
        const rounded = Math.round(raw / step) * step;
        const adjustment = rounded - raw;
        return { subtotal, discount, tax, shipping, raw, rounded, adjustment };
    }, [data.items, data.shipping_amount, data.currency, base_currency]);

    return (
        <>
            <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
                {/* Section 1: Core details */}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Estimate details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Estimate number</label>
                            <div className={`${inputReadonlyClass} font-mono text-terracotta`}>{data.estimate_number}</div>
                            {errors.estimate_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.estimate_number}</p>}
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Customer</label>
                            <div className="flex gap-2 items-stretch min-w-0">
                                <select
                                    value={data.customer_id}
                                    onChange={(e) => setData('customer_id', e.target.value)}
                                    className={`${inputClass} min-w-0 flex-1`}
                                    required
                                >
                                    <option value="">Select customer...</option>
                                    {customerOptions.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => setShowNewCustomerModal(true)}
                                    className="shrink-0 h-11 inline-flex items-center gap-1.5 px-4 rounded-xl font-semibold text-sm text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-colors"
                                >
                                    <Icons.Plus /> New customer
                                </button>
                            </div>
                            {errors.customer_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.customer_id}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Currency</label>
                            <select
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value)}
                                className={inputClass}
                            >
                                {['MYR', 'IDR', 'SGD', 'USD', 'EUR', 'GBP', 'JPY'].map((c) => (
                                    <option key={c} value={c}>{c}</option>
                                ))}
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input
                                type="date"
                                value={data.issue_date}
                                onChange={(e) => setData('issue_date', e.target.value)}
                                className={inputClass}
                                required
                            />
                            {errors.issue_date && <p className="text-terracotta text-xs font-medium mt-1">{errors.issue_date}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Valid until</label>
                            <input
                                type="date"
                                value={data.expiry_date || ''}
                                onChange={(e) => setData('expiry_date', e.target.value)}
                                className={inputClass}
                            />
                            {errors.expiry_date && <p className="text-terracotta text-xs font-medium mt-1">{errors.expiry_date}</p>}
                        </div>
                        {(data.currency || base_currency).toUpperCase() !== (base_currency || 'MYR').toUpperCase() && (
                            <div className="md:col-span-2 min-w-0">
                                <label className={labelClass}>Exchange rate ({(base_currency || 'MYR').toUpperCase()} per 1 {data.currency})</label>
                                <input
                                    type="number"
                                    step="0.000001"
                                    min="0.000001"
                                    value={data.exchange_rate || ''}
                                    onChange={(e) => setData('exchange_rate', e.target.value)}
                                    className={inputClass}
                                    placeholder="e.g. 4.72"
                                />
                                {errors.exchange_rate && <p className="text-terracotta text-xs font-medium mt-1">{errors.exchange_rate}</p>}
                            </div>
                        )}
                    </div>
                </div>

                {/* Section 2: Line items */}
                <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 min-w-0">
                    <div className="overflow-x-auto overscroll-x-contain rounded-2xl">
                        <table className="w-full min-w-[40rem] text-left border-collapse">
                            <colgroup>
                                <col />
                                <col className="w-16" />
                                <col className="w-[4.75rem]" />
                                <col className="w-[4.5rem]" />
                                <col className="w-16" />
                                <col className="w-[5.25rem]" />
                                <col className="w-9" />
                            </colgroup>
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-2 py-2">Description</th>
                                    <th className="px-1 py-2 text-center">Qty</th>
                                    <th className="px-1 py-2 text-right">Price</th>
                                    <th className="px-1 py-2 text-right">Disc</th>
                                    <th className="px-1 py-2 text-center">Tax</th>
                                    <th className="px-2 py-2 text-right">Total</th>
                                    <th className="px-1 py-2"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {data.items.map((item, index) => {
                                    const lineTotal = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - parseFloat(item.discount_amount || 0);
                                    return (
                                        <tr key={index} className="group hover:bg-surface-alt/20 transition-all duration-200">
                                            <td className="px-2 py-2 align-middle">
                                                <div className="flex items-center gap-1.5 min-w-0">
                                                    <textarea
                                                        value={item.description}
                                                        onChange={(e) => updateItem(index, 'description', e.target.value)}
                                                        placeholder="What are you quoting?"
                                                        rows={1}
                                                        className={`${lineDescClass} flex-1`}
                                                        required
                                                    />
                                                    {products.length > 0 && (
                                                        <div className={linePickIconClass} title="Pick a saved product to fill description, price & tax">
                                                            <span className="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                                                <Icons.Product />
                                                            </span>
                                                            <select
                                                                value=""
                                                                onChange={(e) => { applyProduct(index, e.target.value); e.target.value = ''; }}
                                                                className="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                                                                aria-label="Pick product for this line"
                                                            >
                                                                <option value="">Pick product…</option>
                                                                {products.map((p) => (
                                                                    <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                    )}
                                                </div>
                                                {errors[`items.${index}.description`] && (
                                                    <p className="mt-1 text-xs text-terracotta">{errors[`items.${index}.description`]}</p>
                                                )}
                                            </td>
                                            <td className="px-1 py-2 align-middle">
                                                <input type="number" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', e.target.value)} className={`${lineNumberClass} block text-center font-semibold`} />
                                            </td>
                                            <td className="px-1 py-2 align-middle">
                                                <input type="number" value={item.unit_price} onChange={(e) => updateItem(index, 'unit_price', e.target.value)} className={`${lineNumberClass} block text-right font-semibold`} />
                                            </td>
                                            <td className="px-1 py-2 align-middle">
                                                <input type="number" value={item.discount_amount || 0} onChange={(e) => updateItem(index, 'discount_amount', e.target.value)} className={`${lineNumberClass} block text-right text-terracotta font-semibold`} />
                                            </td>
                                            <td className="px-1 py-2 align-middle">
                                                <select value={item.tax_rate} onChange={(e) => updateItem(index, 'tax_rate', e.target.value)} className={`${lineTaxClass} block`}>
                                                    {[0, 6, 8, 16].map((r) => <option key={r} value={r}>{r}%</option>)}
                                                </select>
                                            </td>
                                            <td className="px-2 py-2 align-middle">
                                                <div className="h-8 flex items-center justify-end text-xs font-semibold text-ink font-mono tabular-nums whitespace-nowrap">
                                                    {lineTotal.toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}
                                                </div>
                                            </td>
                                            <td className="px-1 py-2 align-middle text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(index)}
                                                    disabled={data.items.length <= 1}
                                                    className="inline-flex items-center justify-center h-8 w-8 text-ink-muted hover:text-terracotta transition-colors opacity-0 group-hover:opacity-100 disabled:opacity-30"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="p-4 bg-cream/80 border-t border-border-warm">
                        <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors">
                            <Icons.Plus /> Add Line Item
                        </button>
                    </div>
                </div>

                {/* Section 3: Notes + totals */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-surface-alt border border-border-warm/80 p-6 rounded-2xl shadow-sm">
                            <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">Quote only</h4>
                            <p className="text-terracotta text-sm leading-relaxed">
                                Estimates don&apos;t post to the General Ledger. Convert to an invoice when the customer accepts.
                            </p>
                        </div>
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <label className={labelClass}>Customer notes (on PDF)</label>
                            <textarea
                                value={data.customer_notes || ''}
                                onChange={(e) => setData('customer_notes', e.target.value)}
                                className={`${inputClass} resize-none h-28`}
                                placeholder="Payment terms, delivery details, thank you message…"
                            />
                        </div>
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <label className={labelClass}>Private notes (internal only)</label>
                            <textarea
                                value={data.private_notes || ''}
                                onChange={(e) => setData('private_notes', e.target.value)}
                                className={`${inputClass} resize-none h-20`}
                                placeholder="Never shown to the customer."
                            />
                        </div>
                    </div>

                    <div className="space-y-4 min-w-0">
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3 overflow-hidden min-w-0">
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal</span>
                                <span className="text-sm font-mono tabular-nums text-ink">{curSym} {totals.subtotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-terracotta uppercase">Line discounts</span>
                                <span className="text-sm font-mono tabular-nums text-terracotta">- {curSym} {totals.discount.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase">Tax</span>
                                <span className="text-sm font-mono tabular-nums text-ink">+ {curSym} {totals.tax.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex items-center gap-2 min-w-0 pt-3 border-t border-border-warm">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase min-w-0 flex-1 leading-tight">Shipping</span>
                                <input
                                    type="number"
                                    value={data.shipping_amount}
                                    onChange={(e) => setData('shipping_amount', e.target.value)}
                                    className="w-20 max-w-[45%] shrink-0 text-right text-sm border-border-warm rounded-xl font-mono tabular-nums text-ink px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                />
                            </div>
                            {Math.abs(totals.adjustment) > 0.001 && (
                                <div className="flex justify-between text-xs text-ink-muted">
                                    <span>Rounding</span>
                                    <span className="font-mono tabular-nums">{totals.adjustment.toFixed(decimals)}</span>
                                </div>
                            )}
                            <div className="flex justify-between items-baseline pt-3 border-t border-border-warm">
                                <span className="text-eyebrow font-semibold text-ink uppercase">Grand total</span>
                                <span className="text-lg font-mono tabular-nums font-semibold text-terracotta">
                                    {curSym} {totals.rounded.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {showNewCustomerModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50 backdrop-blur-sm" onClick={closeQuickCustomer}>
                    <div className="bg-surface rounded-2xl shadow-xl border border-border-warm/80 w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b border-border-warm">
                            <h3 className="text-lg font-display font-medium text-ink">New customer</h3>
                            <p className="text-sm text-ink-muted mt-0.5">Add a customer for this estimate. Name and email are required.</p>
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
