import React from 'react';
import { lineNet } from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Product: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>,
};

const lineControlClass = 'w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium text-ink bg-surface focus:ring-1 focus:ring-terracotta disabled:bg-cream disabled:text-ink-muted';
const lineDescClass = 'block w-full min-w-0 h-8 border border-border-warm rounded-lg py-1.5 px-1.5 text-xs leading-4 font-medium text-ink bg-surface placeholder-ink-muted/60 focus:ring-1 focus:ring-terracotta resize-y disabled:bg-cream';
const lineNumberClass = `${lineControlClass} font-mono tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;
const lineTaxClass = `${lineControlClass} px-0.5 pr-5 text-center tabular-nums`;
const linePickIconClass = 'relative shrink-0 h-8 w-8 rounded-lg border border-border-warm bg-cream/50 text-ink-muted hover:bg-cream hover:text-terracotta transition-colors';

export function blankPurchaseLine(accountCode = '5000') {
    return { description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount_amount: 0, account_code: accountCode, product_id: null };
}

export function purchaseLineAmount(item) {
    const net = lineNet(item);
    return net + Math.max(0, net) * (Number(item.tax_rate) || 0) / 100;
}

export default function PurchasesDocLines({ items, onChange, products = [], expenseAccounts = [], disabled = false, showTax = true }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';

    const update = (index, patch) => {
        onChange(items.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const applyProduct = (index, id) => {
        const p = products.find((x) => String(x.id) === String(id));
        if (!p) return;
        update(index, {
            product_id: p.id,
            description: p.description ? `${p.name} — ${p.description}` : p.name,
            unit_price: p.unit_price,
            tax_rate: p.tax_rate ?? 0,
            account_code: p.account_code || items[index].account_code || defaultAccount,
        });
    };

    const addItem = () => onChange([...items, blankPurchaseLine(defaultAccount)]);
    const removeItem = (index) => {
        if (items.length <= 1) return;
        onChange(items.filter((_, i) => i !== index));
    };

    return (
        <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 min-w-0">
            <div className="overflow-x-auto overscroll-x-contain rounded-2xl">
                <table className="w-full min-w-[44rem] text-left border-collapse">
                    <colgroup>
                        <col />
                        <col className="w-24" />
                        <col className="w-16" />
                        <col className="w-[4.75rem]" />
                        <col className="w-[4.5rem]" />
                        {showTax && <col className="w-16" />}
                        <col className="w-[5.25rem]" />
                        <col className="w-9" />
                    </colgroup>
                    <thead>
                        <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                            <th className="px-2 py-2">Description</th>
                            <th className="px-1 py-2">Account</th>
                            <th className="px-1 py-2 text-center">Qty</th>
                            <th className="px-1 py-2 text-right">Price</th>
                            <th className="px-1 py-2 text-right">Disc</th>
                            {showTax && <th className="px-1 py-2 text-center">Tax</th>}
                            <th className="px-2 py-2 text-right">Total</th>
                            <th className="px-1 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border-warm">
                        {items.map((item, index) => (
                            <tr key={index} className="group hover:bg-surface-alt/20 transition-all duration-200">
                                <td className="px-2 py-2 align-middle">
                                    <div className="flex items-center gap-1.5 min-w-0">
                                        <textarea
                                            value={item.description}
                                            onChange={(e) => update(index, { description: e.target.value })}
                                            placeholder="What is this line for?"
                                            rows={1}
                                            required
                                            disabled={disabled}
                                            className={`${lineDescClass} flex-1`}
                                        />
                                        {products.length > 0 && !disabled && (
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
                                </td>
                                <td className="px-1 py-2 align-middle">
                                    <select
                                        value={item.account_code || defaultAccount}
                                        onChange={(e) => update(index, { account_code: e.target.value })}
                                        className={`${lineControlClass} block truncate`}
                                        required
                                        disabled={disabled}
                                        title={expenseAccounts.find((a) => a.code === (item.account_code || defaultAccount))?.name}
                                    >
                                        {expenseAccounts.map((a) => (
                                            <option key={a.code} value={a.code}>{a.code}</option>
                                        ))}
                                    </select>
                                </td>
                                <td className="px-1 py-2 align-middle">
                                    <input type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => update(index, { quantity: e.target.value })} disabled={disabled} className={`${lineNumberClass} block text-center font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-middle">
                                    <input type="number" step="0.01" value={item.unit_price} onChange={(e) => update(index, { unit_price: e.target.value })} disabled={disabled} className={`${lineNumberClass} block text-right font-semibold`} />
                                </td>
                                <td className="px-1 py-2 align-middle">
                                    <input type="number" step="0.01" value={item.discount_amount || 0} onChange={(e) => update(index, { discount_amount: e.target.value })} disabled={disabled} className={`${lineNumberClass} block text-right text-terracotta font-semibold`} />
                                </td>
                                {showTax && (
                                    <td className="px-1 py-2 align-middle">
                                        <select value={item.tax_rate} onChange={(e) => update(index, { tax_rate: e.target.value })} disabled={disabled} className={`${lineTaxClass} block`}>
                                            <option value="0">0%</option>
                                            <option value="6">6%</option>
                                            <option value="8">8%</option>
                                            <option value="16">16%</option>
                                        </select>
                                    </td>
                                )}
                                <td className="px-2 py-2 align-middle">
                                    <div className="h-8 flex items-center justify-end text-xs font-semibold text-ink font-mono tabular-nums whitespace-nowrap">
                                        {lineNet(item).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                    </div>
                                </td>
                                <td className="px-1 py-2 align-middle text-center">
                                    {!disabled && (
                                        <button
                                            type="button"
                                            onClick={() => removeItem(index)}
                                            disabled={items.length <= 1}
                                            className="inline-flex items-center justify-center h-8 w-8 text-ink-muted hover:text-terracotta transition-colors opacity-0 group-hover:opacity-100 disabled:opacity-30"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!disabled && (
                <div className="p-4 bg-cream/80 border-t border-border-warm">
                    <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors">
                        <Icons.Plus /> Add Line Item
                    </button>
                </div>
            )}
        </div>
    );
}
