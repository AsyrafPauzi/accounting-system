import React from 'react';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export function lineNet(item) {
    return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0) - (Number(item.discount_amount) || 0);
}

export function computeDocTotals(items = [], shipping = 0) {
    const subtotal = items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0);
    const discount = items.reduce((sum, item) => sum + (Number(item.discount_amount) || 0), 0);
    const tax = items.reduce((sum, item) => sum + Math.max(0, lineNet(item)) * (Number(item.tax_rate) || 0) / 100, 0);
    const ship = Number(shipping) || 0;
    const raw = (subtotal - discount) + tax + ship;
    const rounded = Math.round(raw * 100) / 100;
    return { subtotal, discount, tax, shipping: ship, raw, rounded, adjustment: rounded - raw };
}

export default function DocumentFormNotesTotals({
    bannerTitle,
    bannerText,
    notesLabel = 'Customer notes (on PDF)',
    notesValue = '',
    onNotesChange,
    notesPlaceholder = 'Notes on the PDF (optional)',
    notesDisabled = false,
    items = [],
    shipping,
    onShippingChange,
    showShipping = false,
    extraNotes = null,
    taxAmount,
    onTaxAmountChange,
}) {
    const totals = computeDocTotals(items, shipping);
    const tax = onTaxAmountChange ? (Number(taxAmount) || 0) : totals.tax;
    const grand = Math.round((totals.subtotal - totals.discount + tax + totals.shipping) * 100) / 100;
    const fmt = (n) => n.toLocaleString('en-MY', { minimumFractionDigits: 2 });

    return (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
                {bannerTitle && bannerText && (
                    <div className="bg-surface-alt border border-border-warm/80 p-6 rounded-2xl shadow-sm">
                        <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">{bannerTitle}</h4>
                        <p className="text-terracotta text-sm leading-relaxed">{bannerText}</p>
                    </div>
                )}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <label className={labelClass}>{notesLabel}</label>
                    <textarea
                        value={notesValue}
                        onChange={(e) => onNotesChange?.(e.target.value)}
                        className={`${inputClass} resize-none h-28`}
                        placeholder={notesPlaceholder}
                        disabled={notesDisabled}
                    />
                    {extraNotes}
                </div>
            </div>
            <div className="space-y-4 min-w-0">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3 overflow-hidden min-w-0">
                    <div className="flex justify-between items-baseline">
                        <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal</span>
                        <span className="text-sm font-mono tabular-nums text-ink">{fmt(totals.subtotal)}</span>
                    </div>
                    {totals.discount > 0 && (
                        <div className="flex justify-between items-baseline">
                            <span className="text-eyebrow font-semibold text-terracotta uppercase">Line discounts</span>
                            <span className="text-sm font-mono tabular-nums text-terracotta">- {fmt(totals.discount)}</span>
                        </div>
                    )}
                    {onTaxAmountChange ? (
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase min-w-0 flex-1 leading-tight">Tax</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={taxAmount}
                                onChange={(e) => onTaxAmountChange(e.target.value)}
                                disabled={notesDisabled}
                                className="w-24 max-w-[50%] shrink-0 text-right text-sm border-border-warm rounded-xl font-mono tabular-nums text-ink px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                        </div>
                    ) : (
                        <div className="flex justify-between items-baseline">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase">Tax</span>
                            <span className="text-sm font-mono tabular-nums text-ink">+ {fmt(totals.tax)}</span>
                        </div>
                    )}
                    {showShipping && (
                        <div className="flex items-center gap-2 min-w-0 pt-3 border-t border-border-warm">
                            <span className="text-eyebrow font-semibold text-ink-muted uppercase min-w-0 flex-1 leading-tight">Shipping</span>
                            <input
                                type="number"
                                value={shipping}
                                onChange={(e) => onShippingChange?.(e.target.value)}
                                disabled={notesDisabled}
                                className="w-20 max-w-[45%] shrink-0 text-right text-sm border-border-warm rounded-xl font-mono tabular-nums text-ink px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                        </div>
                    )}
                    <div className="flex justify-between items-baseline pt-3 border-t border-border-warm">
                        <span className="text-eyebrow font-semibold text-ink uppercase">Grand total</span>
                        <span className="text-lg font-mono tabular-nums font-semibold text-terracotta">{fmt(grand)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
