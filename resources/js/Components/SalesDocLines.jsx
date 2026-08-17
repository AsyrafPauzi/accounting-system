import React from 'react';

const lineControl = 'w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta';
const lineNumber = `${lineControl} [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;

export function blankSalesLine() {
    return { description: '', quantity: 1, unit_price: 0, tax_rate: 8, product_id: null };
}

export function lineAmount(item) {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const tax = Number(item.tax_rate) || 0;
    const net = qty * price;
    return net + net * (tax / 100);
}

export default function SalesDocLines({ items, onChange, products = [] }) {
    const update = (index, patch) => {
        onChange(items.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const applyProduct = (index, id) => {
        const p = products.find((x) => String(x.id) === String(id));
        if (!p) return;
        update(index, {
            product_id: p.id,
            description: p.name,
            unit_price: p.unit_price,
            tax_rate: p.tax_rate ?? 0,
        });
    };

    return (
        <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 overflow-hidden min-w-0">
            <div className="overflow-x-auto">
                <table className="w-full table-fixed text-left border-collapse">
                    <colgroup>
                        <col />
                        <col className="w-16" />
                        <col className="w-[5.5rem]" />
                        <col className="w-16" />
                        <col className="w-[6rem]" />
                        <col className="w-9" />
                    </colgroup>
                    <thead>
                        <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                            <th className="px-2 py-2">Description</th>
                            <th className="px-1 py-2 text-center">Qty</th>
                            <th className="px-1 py-2 text-right">Price</th>
                            <th className="px-1 py-2 text-center">Tax</th>
                            <th className="px-2 py-2 text-right">Total</th>
                            <th className="px-1 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border-warm">
                        {items.map((item, index) => (
                            <tr key={index} className="hover:bg-surface-alt/20">
                                <td className="px-2 py-2 align-top">
                                    <div className="flex items-center gap-1.5 min-w-0">
                                        <textarea
                                            value={item.description}
                                            onChange={(e) => update(index, { description: e.target.value })}
                                            placeholder="What is this line for?"
                                            rows={1}
                                            required
                                            className="flex-1 min-w-0 h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta resize-y"
                                        />
                                        {products.length > 0 && (
                                            <select
                                                value=""
                                                onChange={(e) => { applyProduct(index, e.target.value); e.target.value = ''; }}
                                                className="shrink-0 w-[4.25rem] h-8 border border-border-warm rounded-lg text-[10px] font-medium text-ink-muted bg-cream/50 px-1"
                                            >
                                                <option value="">+ Pick</option>
                                                {products.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <input type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => update(index, { quantity: e.target.value })} className={`${lineNumber} text-center font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <input type="number" step="0.01" value={item.unit_price} onChange={(e) => update(index, { unit_price: e.target.value })} className={`${lineNumber} text-right font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-top">
                                    <select value={item.tax_rate} onChange={(e) => update(index, { tax_rate: e.target.value })} className={lineControl}>
                                        <option value="0">0%</option>
                                        <option value="6">6%</option>
                                        <option value="8">8%</option>
                                        <option value="16">16%</option>
                                    </select>
                                </td>
                                <td className="px-2 py-2 align-middle">
                                    <div className="h-8 flex items-center justify-end text-xs font-semibold font-mono tabular-nums">
                                        {lineAmount(item).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                    </div>
                                </td>
                                <td className="px-1 py-2 align-top">
                                    {items.length > 1 && (
                                        <button type="button" className="h-8 w-8 text-ink-muted hover:text-terracotta" onClick={() => onChange(items.filter((_, i) => i !== index))}>×</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="px-3 py-2 border-t border-border-warm bg-cream/40 flex justify-between items-center">
                <button
                    type="button"
                    className="text-xs font-semibold text-terracotta"
                    onClick={() => onChange([...items, blankSalesLine()])}
                >
                    + Add line
                </button>
                <p className="text-sm font-semibold font-mono tabular-nums">
                    {items.reduce((sum, row) => sum + lineAmount(row), 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                </p>
            </div>
        </div>
    );
}
