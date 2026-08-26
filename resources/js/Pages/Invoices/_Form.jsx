import React, { useEffect, useMemo } from 'react';
import SalesDocLines from '@/Components/SalesDocLines';
import {
    SUPPORTED_CURRENCIES,
    currencySymbol,
    currencyRoundStep,
    roundingLabel,
    normalizeCurrency,
} from '@/utils/currency';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

const BANNER_COPY = {
    create: {
        title: 'Draft Mode',
        className: 'bg-surface-alt border border-border-warm/80',
        textClassName: 'text-terracotta',
        text: <>Invoice saves as <strong>Draft</strong>. Ledger entries post only after you validate and post.</>,
    },
    edit: {
        title: 'Audit Notice',
        className: 'bg-mustard/15 border border-mustard/40/80',
        textClassName: 'text-mustard',
        text: <>Draft edits won&apos;t affect the ledger. Posted invoices will <strong>auto-sync</strong> GL entries on save.</>,
    },
};

export function blankInvoiceLine(extra = {}) {
    return {
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 8,
        tax_code_id: null,
        discount_amount: 0,
        item_classification: '011',
        product_id: null,
        ...extra,
    };
}

export function itemsFromInvoice(invoice) {
    if (!invoice?.items?.length) {
        return [blankInvoiceLine()];
    }
    return invoice.items.map((item) => ({
        description: item.description,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        tax_rate: parseFloat(item.tax_rate),
        tax_code_id: item.tax_code_id ?? null,
        discount_amount: parseFloat(item.discount_amount || 0),
        item_classification: item.item_classification || '011',
        product_id: item.product_id ?? null,
    }));
}

export function computeInvoiceTotals(data, baseCurrency = 'MYR') {
    const items = data.items || [];
    const subtotal = items.reduce(
        (sum, item) => sum + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)),
        0,
    );
    const totalDiscount = items.reduce(
        (sum, item) => sum + (parseFloat(item.discount_amount || 0)),
        0,
    );
    const totalTax = items.reduce((sum, item) => {
        const lineAmount = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0))
            - parseFloat(item.discount_amount || 0);
        return sum + (Math.max(0, lineAmount) * parseFloat(item.tax_rate || 0) / 100);
    }, 0);
    const shipping = parseFloat(data.shipping_amount || 0);
    const invCur = normalizeCurrency(data.currency);
    const roundStep = currencyRoundStep(invCur);
    const rawTotal = (subtotal - totalDiscount) + totalTax + shipping;
    const roundedTotal = Math.round(rawTotal / roundStep) * roundStep;
    const roundingAdjustment = roundedTotal - rawTotal;
    const curSym = currencySymbol(data.currency);

    return {
        subtotal,
        totalDiscount,
        totalTax,
        shipping,
        rawTotal,
        roundedTotal,
        roundingAdjustment,
        curSym,
        invCur,
    };
}

export default function InvoiceForm({
    formId = 'invoice-form',
    data,
    setData,
    errors = {},
    onSubmit,
    customers = [],
    customerOptions,
    lhdn_codes = [],
    products = [],
    taxCodes = [],
    base_currency = 'MYR',
    cash_sale = false,
    bankAccounts = [],
    mode = 'create',
    showNewCustomer = false,
    onOpenNewCustomer,
    disabled = false,
}) {
    const options = customerOptions ?? customers;
    const banner = BANNER_COPY[mode] || BANNER_COPY.create;
    const signatureId = `invoice-show-signature-${mode}`;
    const msicHelpId = mode === 'edit' ? 'msic-code-help-edit' : 'msic-code-help';

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

    const totals = useMemo(
        () => computeInvoiceTotals(data, base_currency),
        [data.items, data.shipping_amount, data.currency, base_currency],
    );

    const {
        subtotal,
        totalDiscount,
        totalTax,
        roundingAdjustment,
        roundedTotal,
        curSym,
        invCur,
    } = totals;

    const taxLabel = mode === 'create' ? 'SST (Service Tax)' : 'SST (Tax)';
    const shippingLabel = mode === 'create' ? 'Shipping/Handling' : 'Shipping';
    const exchangeHint = mode === 'create'
        ? 'Ledger posting converts line totals into your company base currency using this rate.'
        : 'Ledger posting converts amounts using this rate.';

    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
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
                            onChange={(e) => setData('invoice_number', e.target.value)}
                            className={`${inputClass} font-mono ${mode === 'create' ? 'text-terracotta' : 'text-ink'}`}
                            required
                            disabled={disabled}
                        />
                        {errors.invoice_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.invoice_number}</p>}
                        {mode === 'edit' && (
                            <p className="text-xs text-ink-muted mt-1.5">Must be unique. Another invoice cannot reuse this number while it exists.</p>
                        )}
                    </div>
                    <div className="min-w-0">
                        <label className={`${labelClass} flex items-center gap-1.5`}>
                            MSIC Code
                            <span className="relative inline-flex group/msic">
                                <button
                                    type="button"
                                    className="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full border border-border-warm text-[9px] font-bold text-ink-muted hover:text-ink hover:border-ink-muted focus:outline-none focus:ring-2 focus:ring-terracotta"
                                    aria-describedby={msicHelpId}
                                    aria-label="What is MSIC Code?"
                                >
                                    ?
                                </button>
                                <span
                                    id={msicHelpId}
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
                            onChange={(e) => setData('msic_code', e.target.value)}
                            className={inputClass}
                            placeholder="e.g. 62011"
                            disabled={disabled}
                        />
                        {errors.msic_code && <p className="text-terracotta text-xs font-medium mt-1">{errors.msic_code}</p>}
                    </div>
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>Customer</label>
                        {showNewCustomer ? (
                            <div className="flex gap-2 items-stretch min-w-0">
                                <select
                                    value={data.customer_id}
                                    onChange={(e) => setData('customer_id', e.target.value)}
                                    className={`${inputClass} min-w-0 flex-1`}
                                    required
                                    disabled={disabled}
                                >
                                    <option value="">Select customer...</option>
                                    {options.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={onOpenNewCustomer}
                                    disabled={disabled}
                                    className="shrink-0 h-11 inline-flex items-center gap-1.5 px-4 rounded-xl font-semibold text-sm text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-colors disabled:opacity-50"
                                >
                                    <Icons.Plus /> New customer
                                </button>
                            </div>
                        ) : (
                            <select
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                                className={inputClass}
                                required
                                disabled={disabled}
                            >
                                <option value="">Select customer...</option>
                                {options.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>
                                ))}
                            </select>
                        )}
                        {errors.customer_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.customer_id}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Issue Date</label>
                        <input
                            type="date"
                            value={data.issue_date}
                            onChange={(e) => setData('issue_date', e.target.value)}
                            className={inputClass}
                            required={mode === 'edit'}
                            disabled={disabled}
                        />
                        {errors.issue_date && <p className="text-terracotta text-xs font-medium mt-1">{errors.issue_date}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Due Date</label>
                        <input
                            type="date"
                            value={data.due_date}
                            onChange={(e) => setData('due_date', e.target.value)}
                            className={inputClass}
                            disabled={disabled}
                        />
                    </div>
                    {cash_sale ? (
                        <>
                            <div className="min-w-0">
                                <label className={labelClass}>Bank / cash account</label>
                                <select
                                    value={data.bank_account_code}
                                    onChange={(e) => setData('bank_account_code', e.target.value)}
                                    className={inputClass}
                                    disabled={disabled}
                                >
                                    {bankAccounts.map((a) => (
                                        <option key={a.code} value={a.code}>{a.name} ({a.code})</option>
                                    ))}
                                </select>
                            </div>
                            <div className="min-w-0">
                                <label className={labelClass}>Payment date</label>
                                <input
                                    type="date"
                                    value={data.payment_date}
                                    onChange={(e) => setData('payment_date', e.target.value)}
                                    className={inputClass}
                                    disabled={disabled}
                                />
                            </div>
                            <div className="md:col-span-2 min-w-0">
                                <label className={labelClass}>Invoice currency</label>
                                <select
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                    className={inputClass}
                                    disabled={disabled}
                                >
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
                            <select
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value)}
                                className={inputClass}
                                disabled={disabled}
                            >
                                {SUPPORTED_CURRENCIES.map((c) => (
                                    <option key={c.value} value={c.value}>{c.label}</option>
                                ))}
                            </select>
                            {errors.currency && <p className="text-terracotta text-xs font-medium mt-1">{errors.currency}</p>}
                        </div>
                    )}
                    {(data.currency || 'MYR').toUpperCase() !== (base_currency || 'MYR').toUpperCase() && (
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>
                                Exchange rate ({(base_currency || 'MYR').toUpperCase()} per 1 {data.currency})
                            </label>
                            <input
                                type="number"
                                step="0.000001"
                                min="0.000001"
                                value={data.exchange_rate}
                                onChange={(e) => setData('exchange_rate', e.target.value)}
                                className={inputClass}
                                placeholder="e.g. 4.72"
                                disabled={disabled}
                            />
                            <p className="text-xs text-ink-muted mt-1.5">{exchangeHint}</p>
                            {errors.exchange_rate && <p className="text-terracotta text-xs font-medium mt-1">{errors.exchange_rate}</p>}
                        </div>
                    )}
                </div>
            </div>

            <SalesDocLines
                items={data.items}
                onChange={(items) => setData('items', items)}
                products={products}
                taxCodes={taxCodes}
                lhdnCodes={lhdn_codes}
                disabled={disabled}
                descriptionPlaceholder="What are you selling?"
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <div className={`${banner.className} p-6 rounded-2xl shadow-sm`}>
                        <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">{banner.title}</h4>
                        <p className={`${banner.textClassName} text-sm leading-relaxed`}>{banner.text}</p>
                    </div>
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <label className={labelClass}>Customer Notes (on PDF)</label>
                        <textarea
                            value={data.customer_notes}
                            onChange={(e) => setData('customer_notes', e.target.value)}
                            className={`${inputClass} resize-none h-28`}
                            placeholder="Payment instructions, thank you message..."
                            disabled={disabled}
                        />
                        <div className="flex items-start gap-3 mt-4 pt-4 border-t border-border-warm">
                            <input
                                type="checkbox"
                                id={signatureId}
                                checked={Boolean(data.show_signature)}
                                onChange={(e) => setData('show_signature', e.target.checked)}
                                disabled={disabled}
                                className="mt-1 h-4 w-4 rounded border-border-warm text-terracotta focus:ring-terracotta"
                            />
                            <label htmlFor={signatureId} className="text-sm text-ink cursor-pointer select-none">
                                <span className="font-semibold text-ink">Show signature lines on PDF</span>
                                <span className="block text-ink-muted text-xs mt-0.5">
                                    When enabled, customer and authorized signature blocks appear at the bottom of the invoice PDF. Turn off for a computer-generated layout only.
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div className="space-y-4 min-w-0">
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3 overflow-hidden min-w-0">
                        <div className="flex justify-between items-baseline">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal (Gross)</span>
                            <span className="text-sm font-mono font-tabular text-ink">{curSym} {subtotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div className="flex justify-between items-baseline">
                            <span className="text-eyebrow font-semibold text-terracotta uppercase">Line Discounts</span>
                            <span className="text-sm font-mono font-tabular text-terracotta">- {curSym} {totalDiscount.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div className="flex justify-between items-baseline">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase">{taxLabel}</span>
                            <span className="text-sm font-mono font-tabular text-ink">+ {curSym} {totalTax.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div className="flex items-center gap-2 min-w-0 pt-3 border-t border-border-warm">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase min-w-0 flex-1 leading-tight">{shippingLabel}</span>
                            <input
                                type="number"
                                value={data.shipping_amount}
                                onChange={(e) => setData('shipping_amount', e.target.value)}
                                disabled={disabled}
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
    );
}
