import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link, router, usePage } from '@inertiajs/react';
import {
    SUPPORTED_CURRENCIES,
    currencySymbol,
    currencyRoundStep,
    currencyInputStep,
    roundingLabel,
    normalizeCurrency,
} from '@/utils/currency';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Product: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>,
};

const inputClass = "w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors";
const labelClass = "block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4";
const lineControlClass = "w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta";
const lineDescClass = "block w-full min-w-0 h-8 border border-border-warm rounded-lg py-1.5 px-1.5 text-xs leading-4 font-medium text-ink bg-surface placeholder-ink-muted/60 focus:ring-1 focus:ring-terracotta resize-y";
const lineNumberClass = `${lineControlClass} font-mono tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;
const lineTaxClass = `${lineControlClass} px-0.5 pr-5 text-center tabular-nums`;
const linePickIconClass = "relative shrink-0 h-8 w-8 rounded-lg border border-border-warm bg-cream/50 text-ink-muted hover:bg-cream hover:text-terracotta transition-colors";

function currencyPrefix(currency) {
    return currencySymbol(currency);
}

const initialQuickCustomer = { name: '', code: '', email: '', tin: '', brn: '', billing_street: '', billing_city: '', billing_state: '', billing_zip: '' };

export default function Create({ auth, customers = [], lhdn_codes = [], customer_id: preselectedCustomerId = null, next_invoice_number: suggestedInvoiceNumber = null, base_currency = 'MYR', products = [], cash_sale = false, bankAccounts = [], default_customer_notes = '' }) {
    const { tax_codes = [] } = usePage().props;
    const [showNewCustomerModal, setShowNewCustomerModal] = useState(false);
    const [newCustomers, setNewCustomers] = useState([]);
    const [quickCustomer, setQuickCustomer] = useState(initialQuickCustomer);
    const [quickCustomerErrors, setQuickCustomerErrors] = useState({});
    const [quickSubmitting, setQuickSubmitting] = useState(false);

    const customerOptions = [...customers, ...newCustomers];

    const { data, setData, post, processing, errors } = useForm({
        invoice_number: suggestedInvoiceNumber || 'INV-' + Date.now(),
        customer_id: preselectedCustomerId ? String(preselectedCustomerId) : '',
        msic_code: '62011', // Default MSIC for Tech/Programming
        issue_date: new Date().toISOString().split('T')[0],
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // Default Net 30
        shipping_amount: 0,
        customer_notes: default_customer_notes || '',
        show_signature: false,
        currency: 'MYR',
        exchange_rate: '1',
        bank_account_code: bankAccounts[0]?.code || '',
        payment_date: new Date().toISOString().split('T')[0],
        items: [
            { 
                description: '', 
                quantity: 1, 
                unit_price: 0, 
                tax_rate: 8,
                tax_code_id: null,
                discount_amount: 0,
                item_classification: '011'
            }
        ],
    });

    useEffect(() => {
        const cur = (data.currency || 'MYR').toUpperCase();
        const base = (base_currency || 'MYR').toUpperCase();
        if (cur === base) {
            if (String(data.exchange_rate) !== '1') {
                setData('exchange_rate', '1');
            }
            return;
        }
        if (data.exchange_rate === '1' || data.exchange_rate === 1) {
            setData('exchange_rate', '');
        }
    }, [data.currency, base_currency]);

    const addItem = () => {
        setData('items', [
            ...data.items,
            { description: '', quantity: 1, unit_price: 0, tax_rate: 8, discount_amount: 0, item_classification: '011' }
        ]);
    };

    const removeItem = (index) => {
        if (data.items.length > 1) {
            const newItems = data.items.filter((_, i) => i !== index);
            setData('items', newItems);
        }
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        newItems[index][field] = value;
        setData('items', newItems);
    };

    /**
     * Apply a saved product to a line: fills description, unit price and tax rate.
     * Leaves quantity and discount alone since those are usually per-deal.
     */
    const applyProduct = (index, productId) => {
        if (!productId) return;
        const product = products.find(p => String(p.id) === String(productId));
        if (!product) return;
        const newItems = [...data.items];
        newItems[index] = {
            ...newItems[index],
            description: product.description ? `${product.name} — ${product.description}` : product.name,
            unit_price: parseFloat(product.unit_price) || 0,
            tax_rate: parseFloat(product.tax_rate) || 0,
            product_id: product.id,
            item_classification: product.classification_code || newItems[index].item_classification,
        };
        setData('items', newItems);
    };

    // --- Enterprise Math Engine ---
    
    const calculateSubtotal = () => {
        return data.items.reduce((sum, item) => sum + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)), 0);
    };

    const calculateTotalDiscount = () => {
        return data.items.reduce((sum, item) => sum + (parseFloat(item.discount_amount || 0)), 0);
    };

    const calculateTax = () => {
        return data.items.reduce((sum, item) => {
            const lineAmount = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - parseFloat(item.discount_amount || 0);
            return sum + (Math.max(0, lineAmount) * parseFloat(item.tax_rate || 0) / 100);
        }, 0);
    };

    const subtotal = calculateSubtotal();
    const totalDiscount = calculateTotalDiscount();
    const totalTax = calculateTax();
    const shipping = parseFloat(data.shipping_amount || 0);
    const invCur = normalizeCurrency(data.currency);
    const roundStep = currencyRoundStep(invCur);
    const rawTotal = (subtotal - totalDiscount) + totalTax + shipping;
    const roundedTotal = (Math.round(rawTotal / roundStep) * roundStep);
    const roundingAdjustment = roundedTotal - rawTotal;
    const curSym = currencyPrefix(data.currency);

    const submit = (e) => {
        e.preventDefault();
        post(route(cash_sale ? 'invoices.cash-sale.store' : 'invoices.store'));
    };

    const submitQuickCustomer = async (e) => {
        e.preventDefault();
        setQuickCustomerErrors({});
        setQuickSubmitting(true);
        const payload = {
            name: quickCustomer.name,
            email: quickCustomer.email,
            tin: quickCustomer.tin,
            brn: quickCustomer.brn,
            ...(quickCustomer.code && { code: quickCustomer.code }),
            ...(quickCustomer.billing_street && { billing_street: quickCustomer.billing_street }),
            ...(quickCustomer.billing_city && { billing_city: quickCustomer.billing_city }),
            ...(quickCustomer.billing_state && { billing_state: quickCustomer.billing_state }),
            ...(quickCustomer.billing_zip && { billing_zip: quickCustomer.billing_zip }),
        };
        router.post(route('customers.quick-store'), payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const newId = page.props.flash.new_customer_id;
                if (newId) {
                    setData('customer_id', String(newId));
                }
                setShowNewCustomerModal(false);
                setQuickCustomer(initialQuickCustomer);
                setQuickCustomerErrors({});
            },
            onError: (errors) => {
                setQuickCustomerErrors(errors);
            },
            onFinish: () => {
                setQuickSubmitting(false);
            }
        });
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div className="flex items-center gap-2">
                        <Link href={route('invoices.index')} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-2.5">
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.Document /></span>
                            <div>
                                <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">{cash_sale ? 'Cash sale' : 'New Invoice'}</h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">
                                    {cash_sale ? 'Invoice plus full receipt in one save' : 'Bill the customer — post and email when ready'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                            Cancel
                        </Link>
                        <button type="submit" form="invoice-create-form" disabled={processing} className="inline-flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg transition-all duration-200">
                            {processing ? 'Saving...' : (cash_sale ? 'Save cash sale' : 'Create Invoice')}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={cash_sale ? 'Cash sale' : 'New Invoice'} />

            <form id="invoice-create-form" onSubmit={submit} className="space-y-6 pb-12 min-w-0">
                {/* Section 1: Core Details */}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Invoice Details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Invoice Number</label>
                            <input
                                type="text"
                                value={data.invoice_number}
                                onChange={e => setData('invoice_number', e.target.value)}
                                className={`${inputClass} font-mono text-terracotta`}
                                required
                            />
                            {errors.invoice_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.invoice_number}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={`${labelClass} flex items-center gap-1.5`}>
                                MSIC Code
                                <span className="relative inline-flex group/msic">
                                    <button
                                        type="button"
                                        className="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full border border-border-warm text-[9px] font-bold text-ink-muted hover:text-ink hover:border-ink-muted focus:outline-none focus:ring-2 focus:ring-terracotta"
                                        aria-describedby="msic-code-help"
                                        aria-label="What is MSIC Code?"
                                    >
                                        ?
                                    </button>
                                    <span
                                        id="msic-code-help"
                                        role="tooltip"
                                        className="pointer-events-none absolute left-1/2 top-full z-20 mt-2 w-64 -translate-x-1/2 rounded-xl border border-border-warm bg-surface px-3 py-2 text-[11px] font-medium normal-case tracking-normal text-ink shadow-lg opacity-0 transition-opacity duration-150 group-hover/msic:opacity-100 group-focus-within/msic:opacity-100"
                                    >
                                        <span className="font-semibold text-ink">Malaysia Standard Industrial Classification</span>
                                        <span className="mt-1 block text-ink-muted font-normal leading-snug">
                                            5-digit business activity code required for LHDN MyInvois e-invoicing. Example: <span className="font-mono text-ink">62011</span> for computer programming.
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <input
                                type="text"
                                value={data.msic_code}
                                onChange={e => setData('msic_code', e.target.value)}
                                className={inputClass}
                                placeholder="e.g. 62011"
                            />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Customer</label>
                            <div className="flex gap-2 items-stretch min-w-0">
                                <select value={data.customer_id} onChange={e => setData('customer_id', e.target.value)} className={`${inputClass} min-w-0 flex-1`} required>
                                    <option value="">Select customer...</option>
                                    {customerOptions.map(c => <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>)}
                                </select>
                                <button type="button" onClick={() => setShowNewCustomerModal(true)} className="shrink-0 h-11 inline-flex items-center gap-1.5 px-4 rounded-xl font-semibold text-sm text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-colors">
                                    <Icons.Plus className="w-4 h-4" /> New customer
                                </button>
                            </div>
                            {errors.customer_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.customer_id}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Issue Date</label>
                            <input type="date" value={data.issue_date} onChange={e => setData('issue_date', e.target.value)} className={inputClass} />
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Due Date</label>
                            <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className={inputClass} />
                        </div>
                        {cash_sale ? (
                            <>
                                <div className="min-w-0">
                                    <label className={labelClass}>Bank / cash account</label>
                                    <select value={data.bank_account_code} onChange={e => setData('bank_account_code', e.target.value)} className={inputClass}>
                                        {bankAccounts.map((a) => (
                                            <option key={a.code} value={a.code}>{a.name} ({a.code})</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="min-w-0">
                                    <label className={labelClass}>Payment date</label>
                                    <input type="date" value={data.payment_date} onChange={e => setData('payment_date', e.target.value)} className={inputClass} />
                                </div>
                                <div className="md:col-span-2 min-w-0">
                                    <label className={labelClass}>Invoice currency</label>
                                    <select value={data.currency} onChange={e => setData('currency', e.target.value)} className={inputClass}>
                                        {SUPPORTED_CURRENCIES.map((c) => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>
                                    {errors.currency && <p className="text-terracotta text-xs font-medium mt-1">{errors.currency}</p>}
                                </div>
                            </>
                        ) : (
                            <div className="md:col-span-2 min-w-0">
                                <label className={labelClass}>Invoice currency</label>
                                <select value={data.currency} onChange={e => setData('currency', e.target.value)} className={inputClass}>
                                    {SUPPORTED_CURRENCIES.map((c) => (
                                        <option key={c.value} value={c.value}>{c.label}</option>
                                    ))}
                                </select>
                                {errors.currency && <p className="text-terracotta text-xs font-medium mt-1">{errors.currency}</p>}
                            </div>
                        )}
                        {(data.currency || 'MYR').toUpperCase() !== (base_currency || 'MYR').toUpperCase() && (
                            <div className="md:col-span-2 min-w-0">
                                <label className={labelClass}>Exchange rate ({(base_currency || 'MYR').toUpperCase()} per 1 {data.currency})</label>
                                <input type="number" step="0.000001" min="0.000001" value={data.exchange_rate} onChange={e => setData('exchange_rate', e.target.value)} className={inputClass} placeholder="e.g. 4.72" />
                                <p className="text-xs text-ink-muted mt-1.5">Ledger posting converts line totals into your company base currency using this rate.</p>
                                {errors.exchange_rate && <p className="text-terracotta text-xs font-medium mt-1">{errors.exchange_rate}</p>}
                            </div>
                        )}
                    </div>
                </div>

                {/* Section 2: Line Items */}
                <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 min-w-0">
                        <div className="overflow-x-auto overscroll-x-contain rounded-2xl">
                        <table className="w-full min-w-[44rem] text-left border-collapse">
                            <colgroup>
                                <col className="w-[5.5rem]" />
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
                                    <th className="px-2 py-2">LHDN</th>
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
                                {data.items.map((item, index) => (
                                    <tr key={index} className="group hover:bg-surface-alt/20 transition-all duration-200">
                                        <td className="px-2 py-2 align-middle">
                                            <select
                                                value={item.item_classification}
                                                onChange={e => updateItem(index, 'item_classification', e.target.value)}
                                                className={`${lineControlClass} block truncate`}
                                                title={(() => {
                                                    const selected = lhdn_codes.find((code) => String(code.id) === String(item.item_classification));
                                                    return selected ? `${selected.id} — ${selected.name}` : 'LHDN classification';
                                                })()}
                                            >
                                                {lhdn_codes.map(code => (
                                                    <option key={code.id} value={code.id} title={`${code.id} — ${code.name}`}>
                                                        {code.id}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-2 py-2 align-middle">
                                            <div className="flex items-center gap-1.5 min-w-0">
                                                <textarea
                                                    value={item.description}
                                                    onChange={e => updateItem(index, 'description', e.target.value)}
                                                    placeholder="What are you selling?"
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
                                                            onChange={e => { applyProduct(index, e.target.value); e.target.value = ''; }}
                                                            className="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                                                            aria-label="Pick product for this line"
                                                        >
                                                            <option value="">Pick product…</option>
                                                            {products.map(p => (
                                                                <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-1 py-2 align-middle">
                                            <input type="number" value={item.quantity} onChange={e => updateItem(index, 'quantity', e.target.value)} className={`${lineNumberClass} block text-center font-semibold`} />
                                        </td>
                                        <td className="px-1 py-2 align-middle">
                                            <input type="number" value={item.unit_price} onChange={e => updateItem(index, 'unit_price', e.target.value)} className={`${lineNumberClass} block text-right font-semibold`} />
                                        </td>
                                        <td className="px-1 py-2 align-middle">
                                            <input type="number" value={item.discount_amount} onChange={e => updateItem(index, 'discount_amount', e.target.value)} className={`${lineNumberClass} block text-right text-terracotta font-semibold`} />
                                        </td>
                                        <td className="px-1 py-2 align-middle">
                                            <select
                                                value={item.tax_code_id ?? item.tax_rate}
                                                onChange={(e) => {
                                                    const val = e.target.value;
                                                    const code = tax_codes.find((c) => String(c.id) === val);
                                                    if (code) {
                                                        updateItem(index, 'tax_code_id', code.id);
                                                        updateItem(index, 'tax_rate', code.rate);
                                                    } else {
                                                        updateItem(index, 'tax_code_id', null);
                                                        updateItem(index, 'tax_rate', val);
                                                    }
                                                }}
                                                className={`${lineTaxClass} block`}
                                            >
                                                {tax_codes.length > 0 ? tax_codes.map((code) => (
                                                    <option key={code.id} value={code.id}>{code.code} ({code.rate}%)</option>
                                                )) : (
                                                    <>
                                                        <option value="0">0%</option>
                                                        <option value="6">6%</option>
                                                        <option value="8">8%</option>
                                                        <option value="16">16%</option>
                                                    </>
                                                )}
                                            </select>
                                        </td>
                                        <td className="px-2 py-2 align-middle">
                                            <div className="h-8 flex items-center justify-end text-xs font-semibold text-ink font-mono tabular-nums whitespace-nowrap">
                                                {((parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - (parseFloat(item.discount_amount || 0))).toLocaleString('en-MY', {minimumFractionDigits: 2})}
                                            </div>
                                        </td>
                                        <td className="px-1 py-2 align-middle text-center">
                                            <button type="button" onClick={() => removeItem(index)} className="inline-flex items-center justify-center h-8 w-8 text-ink-muted hover:text-terracotta transition-colors opacity-0 group-hover:opacity-100">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                        <div className="p-4 bg-cream/80 border-t border-border-warm">
                            <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors">
                                <Icons.Plus /> Add Line Item
                            </button>
                        </div>
                    </div>

                {/* Section 3: Footer & Calculations */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-surface-alt border border-border-warm/80 p-6 rounded-2xl shadow-sm">
                            <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">Draft Mode</h4>
                            <p className="text-terracotta text-sm leading-relaxed">
                                Invoice saves as <strong>Draft</strong>. Ledger entries post only after you validate and post.
                            </p>
                        </div>
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <label className={labelClass}>Customer Notes (on PDF)</label>
                            <textarea 
                                value={data.customer_notes} 
                                onChange={e => setData('customer_notes', e.target.value)}
                                className={`${inputClass} resize-none h-28`}
                                placeholder="Payment instructions, thank you message..."
                            />
                            <div className="flex items-start gap-3 mt-4 pt-4 border-t border-border-warm">
                                <input
                                    type="checkbox"
                                    id="invoice-show-signature"
                                    checked={Boolean(data.show_signature)}
                                    onChange={(e) => setData('show_signature', e.target.checked)}
                                    className="mt-1 h-4 w-4 rounded border-border-warm text-terracotta focus:ring-terracotta"
                                />
                                <label htmlFor="invoice-show-signature" className="text-sm text-ink cursor-pointer select-none">
                                    <span className="font-semibold text-ink">Show signature lines on PDF</span>
                                    <span className="block text-ink-muted text-xs mt-0.5">When enabled, customer and authorized signature blocks appear at the bottom of the invoice PDF. Turn off for a computer-generated layout only.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4 min-w-0">
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3 overflow-hidden min-w-0">
                                <div className="flex justify-between items-baseline">
                                    <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal (Gross)</span>
                                    <span className="text-sm font-mono font-tabular text-ink">{curSym} {subtotal.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div className="flex justify-between items-baseline">
                                    <span className="text-eyebrow font-semibold text-terracotta uppercase">Line Discounts</span>
                                    <span className="text-sm font-mono font-tabular text-terracotta">- {curSym} {totalDiscount.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div className="flex justify-between items-baseline">
                                    <span className="text-eyebrow font-semibold text-ink-muted uppercase">SST (Service Tax)</span>
                                    <span className="text-sm font-mono font-tabular text-ink">+ {curSym} {totalTax.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div className="flex items-center gap-2 min-w-0 pt-3 border-t border-border-warm">
                                    <span className="text-eyebrow font-semibold text-ink-muted uppercase min-w-0 flex-1 leading-tight">Shipping/Handling</span>
                                    <input
                                        type="number"
                                        value={data.shipping_amount}
                                        onChange={e => setData('shipping_amount', e.target.value)}
                                        className="w-20 max-w-[45%] shrink-0 text-right text-sm border-border-warm rounded-xl font-mono font-tabular text-ink px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    />
                                </div>
                                <div className="flex justify-between text-xs text-ink-muted">
                                    <span>{roundingLabel(invCur)}</span>
                                    <span className="font-mono font-tabular">{roundingAdjustment.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between items-baseline pt-3 border-t border-border-warm">
                                    <span className="text-eyebrow font-semibold text-ink uppercase">Grand Total</span>
                                    <span className="text-lg font-mono font-tabular font-semibold text-terracotta">
                                        {curSym} {roundedTotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                        </div>
                    </div>
                </div>
            </form>

            {/* New customer modal (quick-create from invoice) */}
            {showNewCustomerModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50 backdrop-blur-sm" onClick={() => !quickSubmitting && setShowNewCustomerModal(false)}>
                    <div className="bg-surface rounded-2xl shadow-xl border border-border-warm/80 w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                        <div className="p-6 border-b border-border-warm">
                            <h3 className="text-lg font-display font-medium text-ink">New customer</h3>
                            <p className="text-sm text-ink-muted mt-0.5">Add a customer to use on this invoice. Name and Email are required.</p>
                        </div>
                        <form onSubmit={submitQuickCustomer} className="p-6 space-y-4">
                            {quickCustomerErrors.form && (
                                <div className="p-3 rounded-xl bg-terracotta/10 text-terracotta text-sm">{quickCustomerErrors.form}</div>
                            )}
                            <div>
                                <label className={labelClass}>Name *</label>
                                <input type="text" value={quickCustomer.name} onChange={e => setQuickCustomer(c => ({ ...c, name: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.name && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.name[0]}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Code (optional)</label>
                                <input type="text" value={quickCustomer.code} onChange={e => setQuickCustomer(c => ({ ...c, code: e.target.value }))} className={inputClass} placeholder="Auto-generated if blank" />
                                {quickCustomerErrors.code && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.code[0]}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Email *</label>
                                <input type="email" value={quickCustomer.email} onChange={e => setQuickCustomer(c => ({ ...c, email: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.email && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.email[0]}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>TIN</label>
                                    <input type="text" value={quickCustomer.tin} onChange={e => setQuickCustomer(c => ({ ...c, tin: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.tin && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.tin[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>BRN</label>
                                    <input type="text" value={quickCustomer.brn} onChange={e => setQuickCustomer(c => ({ ...c, brn: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.brn && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.brn[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className={labelClass}>Billing street (optional)</label>
                                <input type="text" value={quickCustomer.billing_street} onChange={e => setQuickCustomer(c => ({ ...c, billing_street: e.target.value }))} className={inputClass} />
                                {quickCustomerErrors.billing_street && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.billing_street[0]}</p>}
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className={labelClass}>City</label>
                                    <input type="text" value={quickCustomer.billing_city} onChange={e => setQuickCustomer(c => ({ ...c, billing_city: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_city && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.billing_city[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>State</label>
                                    <input type="text" value={quickCustomer.billing_state} onChange={e => setQuickCustomer(c => ({ ...c, billing_state: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_state && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.billing_state[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Zip</label>
                                    <input type="text" value={quickCustomer.billing_zip} onChange={e => setQuickCustomer(c => ({ ...c, billing_zip: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_zip && <p className="text-terracotta text-xs mt-1">{quickCustomerErrors.billing_zip[0]}</p>}
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-4 border-t border-border-warm">
                                <button type="button" onClick={() => !quickSubmitting && (setShowNewCustomerModal(false), setQuickCustomer(initialQuickCustomer), setQuickCustomerErrors({}))} className="px-4 py-2.5 rounded-xl font-semibold text-ink hover:bg-surface-alt">
                                    Cancel
                                </button>
                                <button type="submit" disabled={quickSubmitting} className="px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                                    {quickSubmitting ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}