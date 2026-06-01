import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import {
    SUPPORTED_CURRENCIES,
    currencySymbol,
    currencyRoundStep,
    roundingLabel,
    normalizeCurrency,
} from '@/utils/currency';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    LockClosed: () => <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
};

const inputClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors";
const inputReadonlyClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink-muted bg-cream";
const labelClass = "block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5";

function currencyPrefix(currency) {
    return currencySymbol(currency);
}

export default function Edit({ auth, invoice, customers = [], lhdn_codes = [], journal_entry_id = null, base_currency = 'MYR' }) {
    // Initialize form with existing invoice data and its nested items
    const { data, setData, put, processing, errors } = useForm({
        customer_id: invoice.customer_id || '',
        invoice_number: invoice.invoice_number || '',
        msic_code: invoice.msic_code || '62011',
        issue_date: invoice.issue_date || '',
        due_date: invoice.due_date || '',
        shipping_amount: parseFloat(invoice.shipping_amount || 0),
        customer_notes: invoice.customer_notes || '',
        show_signature: invoice.show_signature ?? true,
        currency: (invoice.currency || 'MYR').toUpperCase(),
        exchange_rate: (() => {
            const cur = (invoice.currency || 'MYR').toUpperCase();
            const base = (base_currency || 'MYR').toUpperCase();
            if (cur === base) {
                return '1';
            }
            const er = invoice.exchange_rate;
            if (er != null && Number(er) > 0) {
                return String(Number(er));
            }
            return '';
        })(),
        // Map existing items from the database to the form state
        items: invoice.items && invoice.items.length > 0 
            ? invoice.items.map(item => ({
                description: item.description,
                quantity: parseFloat(item.quantity),
                unit_price: parseFloat(item.unit_price),
                tax_rate: parseFloat(item.tax_rate),
                discount_amount: parseFloat(item.discount_amount || 0),
                item_classification: item.item_classification || '011'
            })) 
            : [{ description: '', quantity: 1, unit_price: 0, tax_rate: 8, discount_amount: 0, item_classification: '011' }],
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
        put(route('invoices.update', invoice.id));
    };

    const getStatusBadge = () => {
        const styles = {
            paid: 'bg-forest/10 text-forest',
            void: 'bg-surface-alt text-ink-muted',
            draft: 'bg-surface-alt text-ink',
            unpaid: 'bg-terracotta/10 text-terracotta',
            'partially paid': 'bg-surface-alt text-terracotta',
        };
        return styles[invoice.status] || 'bg-surface-alt text-ink';
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                (invoice.status === 'paid' || invoice.status === 'void') ? (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-surface-alt text-ink-muted"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">{invoice.invoice_number}</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-ink-muted text-sm font-medium mt-1">Document locked — read only</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {auth.planPermissions['general-ledger.view'] && journal_entry_id && (
                                <Link href={route('general-ledger.show', journal_entry_id)} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-all duration-200">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                    View Accounting Entry
                                </Link>
                            )}
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                Back to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit Invoice</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-ink-muted text-sm font-medium mt-1">{invoice.invoice_number} · {customers.find(c => c.id == invoice.customer_id)?.name || 'Customer'}</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.ArrowDownTray /> PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                Cancel
                            </Link>
                            <button type="submit" form="invoice-edit-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg  transition-all duration-200">
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                )
            }
        >
            <Head title={`Edit ${invoice.invoice_number}`} />

            <div className="space-y-6 pb-12">
                {/* Locked state: Paid or Void */}
                {(invoice.status === 'paid' || invoice.status === 'void') ? (
                    <div className="bg-surface p-12 rounded-2xl border border-border-warm/80 shadow-sm text-center space-y-6">
                        <div className="flex justify-center">
                            <span className="p-4 rounded-2xl bg-surface-alt text-ink-muted">
                                <Icons.LockClosed />
                            </span>
                        </div>
                        <div>
                            <h3 className="text-xl font-display font-medium text-ink mb-2">Document Locked</h3>
                            <p className="text-ink-muted max-w-md mx-auto leading-relaxed text-sm">
                                This invoice is marked as <strong className="text-ink">{invoice.status}</strong>. 
                                To maintain audit integrity, finalized documents cannot be modified. 
                                Issue a <strong>Credit Note</strong> to reverse charges.
                            </p>
                        </div>
                        <div className="flex gap-3 justify-center">
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white bg-ink hover:bg-ink transition-colors">
                                Return to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <form id="invoice-edit-form" onSubmit={submit} className="space-y-6">
                        {/* Section 1: Core Details */}
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <div className="flex items-center gap-2 mb-6">
                                <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                                <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Invoice Details</h3>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div>
                                    <label className={labelClass}>Invoice Number</label>
                                    <input
                                        type="text"
                                        value={data.invoice_number}
                                        onChange={e => setData('invoice_number', e.target.value)}
                                        className={`${inputClass} font-mono text-ink`}
                                        required
                                    />
                                    {errors.invoice_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.invoice_number}</p>}
                                    <p className="text-xs text-ink-muted mt-1.5">Must be unique. Another invoice cannot reuse this number while it exists.</p>
                                </div>
                                <div>
                                    <label className={labelClass}>MSIC Code</label>
                                    <input type="text" value={data.msic_code} onChange={e => setData('msic_code', e.target.value)} className={inputClass} />
                                    {errors.msic_code && <p className="text-terracotta text-xs font-medium mt-1">{errors.msic_code}</p>}
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Customer</label>
                                    <select value={data.customer_id} onChange={e => setData('customer_id', e.target.value)} className={inputClass} required>
                                        <option value="">Select customer...</option>
                                        {customers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                    {errors.customer_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.customer_id}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Issue Date</label>
                                    <input type="date" value={data.issue_date} onChange={e => setData('issue_date', e.target.value)} className={inputClass} required />
                                </div>
                                <div>
                                    <label className={labelClass}>Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Invoice currency</label>
                                    <select value={data.currency} onChange={e => setData('currency', e.target.value)} className={inputClass}>
                                        {SUPPORTED_CURRENCIES.map((c) => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>
                                    {errors.currency && <p className="text-terracotta text-xs font-medium mt-1">{errors.currency}</p>}
                                </div>
                                {(data.currency || 'MYR').toUpperCase() !== (base_currency || 'MYR').toUpperCase() && (
                                    <div className="md:col-span-2">
                                        <label className={labelClass}>Exchange rate ({(base_currency || 'MYR').toUpperCase()} per 1 {data.currency})</label>
                                        <input type="number" step="0.000001" min="0.000001" value={data.exchange_rate} onChange={e => setData('exchange_rate', e.target.value)} className={inputClass} />
                                        <p className="text-xs text-ink-muted mt-1.5">Ledger posting converts amounts using this rate.</p>
                                        {errors.exchange_rate && <p className="text-terracotta text-xs font-medium mt-1">{errors.exchange_rate}</p>}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Section 2: Line Items */}
                        <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                        <th className="p-6">LHDN classification</th>
                                        <th className="p-6">Description</th>
                                        <th className="p-6 text-center w-24">Qty</th>
                                        <th className="p-6 w-32">Price ({invCur})</th>
                                        <th className="p-6 w-32">Disc ({invCur})</th>
                                        <th className="p-6 text-center w-32">Tax</th>
                                        <th className="p-6 text-right w-40">Total</th>
                                        <th className="p-6 w-16"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border-warm">
                                    {data.items.map((item, index) => (
                                        <tr key={index} className="group hover:bg-surface-alt/20 transition-all duration-200">
                                            <td className="p-4">
                                                <select 
                                                    value={item.item_classification} 
                                                    onChange={e => updateItem(index, 'item_classification', e.target.value)}
                                                    className="w-full border-border-warm rounded-xl text-[10px] font-display font-medium text-ink-muted focus:ring-terracotta py-2"
                                                >
                                                    {lhdn_codes.map(code => (
                                                        <option key={code.id} value={code.id}>{code.id} - {code.name}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="p-4">
                                                <input 
                                                    type="text" 
                                                    value={item.description} 
                                                    onChange={e => updateItem(index, 'description', e.target.value)}
                                                    className="w-full border-none focus:ring-0 p-2 text-sm font-display font-medium text-ink bg-transparent"
                                                    required
                                                />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.quantity} onChange={e => updateItem(index, 'quantity', e.target.value)} className="w-full border-border-warm rounded-xl text-sm text-center py-2 focus:ring-terracotta font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.unit_price} onChange={e => updateItem(index, 'unit_price', e.target.value)} className="w-full border-border-warm rounded-xl text-sm py-2 focus:ring-terracotta font-mono font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.discount_amount} onChange={e => updateItem(index, 'discount_amount', e.target.value)} className="w-full border-border-warm rounded-xl text-sm py-2 focus:ring-terracotta font-mono text-terracotta font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <select value={item.tax_rate} onChange={e => updateItem(index, 'tax_rate', e.target.value)} className="w-full border-border-warm rounded-xl text-sm font-display font-medium text-ink focus:ring-terracotta py-2.5">
                                                    <option value="0">0%</option>
                                                    <option value="6">6%</option>
                                                    <option value="8">8%</option>
                                                    <option value="16">16%</option>
                                                </select>
                                            </td>
                                            <td className="p-4 text-right">
                                                <div className="text-sm font-display font-semibold text-ink font-mono">
                                                    {((parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - (parseFloat(item.discount_amount || 0))).toLocaleString('en-MY', {minimumFractionDigits: 2})}
                                                </div>
                                            </td>
                                            <td className="p-4 text-center">
                                                <button type="button" onClick={() => removeItem(index)} className="text-ink-muted hover:text-terracotta transition-colors opacity-0 group-hover:opacity-100">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="p-6 bg-cream/80 border-t border-border-warm">
                                <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors">
                                    <Icons.Plus /> Add Line Item
                                </button>
                            </div>
                        </div>

                        {/* Section 3: Footer & Calculations */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div className="lg:col-span-2 space-y-6">
                                <div className="bg-mustard/15 border border-mustard/40/80 p-6 rounded-2xl shadow-sm">
                                    <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">Audit Notice</h4>
                                    <p className="text-mustard text-sm leading-relaxed">
                                        Draft edits won&apos;t affect the ledger. Posted invoices will <strong>auto-sync</strong> GL entries on save.
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
                                            id="invoice-show-signature-edit"
                                            checked={Boolean(data.show_signature)}
                                            onChange={(e) => setData('show_signature', e.target.checked)}
                                            className="mt-1 h-4 w-4 rounded border-border-warm text-terracotta focus:ring-terracotta"
                                        />
                                        <label htmlFor="invoice-show-signature-edit" className="text-sm text-ink cursor-pointer select-none">
                                            <span className="font-semibold text-ink">Show signature lines on PDF</span>
                                            <span className="block text-ink-muted text-xs mt-0.5">When enabled, customer and authorized signature blocks appear at the bottom of the invoice PDF. Turn off for a computer-generated layout only.</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3">
                                    <div className="flex justify-between items-baseline">
                                        <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal (Gross)</span>
                                        <span className="text-sm font-mono font-tabular text-ink">{curSym} {subtotal.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between items-baseline">
                                        <span className="text-eyebrow font-semibold text-terracotta uppercase">Line Discounts</span>
                                        <span className="text-sm font-mono font-tabular text-terracotta">- {curSym} {totalDiscount.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between items-baseline">
                                        <span className="text-eyebrow font-semibold text-ink-muted uppercase">SST (Tax)</span>
                                        <span className="text-sm font-mono font-tabular text-ink">+ {curSym} {totalTax.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between items-center pt-3 border-t border-border-warm">
                                        <span className="text-eyebrow font-semibold text-ink-muted uppercase">Shipping</span>
                                        <input
                                            type="number"
                                            value={data.shipping_amount}
                                            onChange={e => setData('shipping_amount', e.target.value)}
                                            className="w-28 text-right text-sm border-border-warm rounded-xl font-mono font-tabular text-ink"
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
                )}
            </div>
        </AuthenticatedLayout>
    );
}