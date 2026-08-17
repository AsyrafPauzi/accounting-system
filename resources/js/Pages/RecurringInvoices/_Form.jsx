import React, { useMemo } from 'react';
import SalesDocLines, { blankSalesLine } from '@/Components/SalesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export { blankSalesLine };

export default function RecurringInvoiceForm({
    formId = 'recurring-invoice-form',
    data,
    setData,
    errors = {},
    onSubmit,
    customers = [],
    products = [],
    base_currency = 'MYR',
}) {
    const scheduleSummary = useMemo(() => {
        if (!data.next_run_date && !data.start_date) return '';
        const nextStr = data.next_run_date || data.start_date;
        const date = new Date(nextStr);
        if (isNaN(date.getTime())) return '';
        const interval = Math.max(1, parseInt(data.interval || 1, 10));
        const unit = ({
            weekly: interval === 1 ? 'week' : 'weeks',
            monthly: interval === 1 ? 'month' : 'months',
            quarterly: interval === 1 ? 'quarter' : 'quarters',
            yearly: interval === 1 ? 'year' : 'years',
        })[data.cadence] || 'cycles';
        const label = interval === 1 ? `every ${unit}` : `every ${interval} ${unit}`;
        const dateLabel = date.toLocaleDateString('en-MY', { day: '2-digit', month: 'long', year: 'numeric' });
        return `First draft invoice will be created on ${dateLabel}, then ${label} after that.`;
    }, [data.next_run_date, data.start_date, data.cadence, data.interval]);

    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
            <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                    <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Template details</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>Internal label</label>
                        <input
                            type="text"
                            value={data.name || ''}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Acme Corp — monthly retainer"
                            className={inputClass}
                        />
                        <p className="mt-1 text-[10px] text-ink-muted">Only you see this — it won’t appear on the invoice.</p>
                        {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                    </div>
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>Customer</label>
                        <select
                            value={data.customer_id}
                            onChange={(e) => setData('customer_id', e.target.value)}
                            className={inputClass}
                            required
                        >
                            <option value="">Select customer...</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>
                            ))}
                        </select>
                        {errors.customer_id && <p className="mt-1 text-xs text-terracotta">{errors.customer_id}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Cadence</label>
                        <select value={data.cadence} onChange={(e) => setData('cadence', e.target.value)} className={inputClass}>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Repeat every</label>
                        <input type="number" min="1" max="36" value={data.interval} onChange={(e) => setData('interval', e.target.value)} className={`${inputClass} font-mono`} />
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Start date</label>
                        <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className={inputClass} required />
                        {errors.start_date && <p className="mt-1 text-xs text-terracotta">{errors.start_date}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Next run</label>
                        <input type="date" value={data.next_run_date || ''} onChange={(e) => setData('next_run_date', e.target.value)} className={inputClass} />
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>End date (optional)</label>
                        <input type="date" value={data.end_date || ''} onChange={(e) => setData('end_date', e.target.value)} className={inputClass} />
                        {errors.end_date && <p className="mt-1 text-xs text-terracotta">{errors.end_date}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Payment terms (days)</label>
                        <input type="number" min="0" max="365" value={data.payment_terms_days} onChange={(e) => setData('payment_terms_days', e.target.value)} className={`${inputClass} font-mono`} />
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Currency</label>
                        <select value={data.currency} onChange={(e) => setData('currency', e.target.value)} className={inputClass}>
                            {['MYR', 'IDR', 'SGD', 'USD', 'EUR', 'GBP', 'JPY'].map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                    </div>
                    {(data.currency || base_currency).toUpperCase() !== (base_currency || 'MYR').toUpperCase() && (
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Exchange rate ({(base_currency || 'MYR').toUpperCase()} per 1 {data.currency})</label>
                            <input type="number" step="0.000001" min="0.000001" value={data.exchange_rate || ''} onChange={(e) => setData('exchange_rate', e.target.value)} className={inputClass} placeholder="e.g. 4.72" />
                        </div>
                    )}
                    <div className="min-w-0">
                        <label className={labelClass}>MSIC code</label>
                        <input type="text" value={data.msic_code} onChange={(e) => setData('msic_code', e.target.value)} className={`${inputClass} font-mono`} placeholder="00000" />
                    </div>
                    <div className="md:col-span-4 flex flex-wrap gap-x-6 gap-y-3 pt-1">
                        <label className="inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
                            <input type="checkbox" checked={!!data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta" />
                            Active schedule
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
                            <input type="checkbox" checked={!!data.auto_email} onChange={(e) => setData('auto_email', e.target.checked)} className="w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta" />
                            Email the customer when generated
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
                            <input type="checkbox" checked={!!data.auto_post} onChange={(e) => setData('auto_post', e.target.checked)} className="w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta" />
                            Post to the ledger automatically
                        </label>
                    </div>
                </div>
            </div>

            <SalesDocLines
                items={data.items}
                onChange={(items) => setData('items', items)}
                products={products}
                descriptionPlaceholder="What are you billing every cycle?"
            />

            <DocumentFormNotesTotals
                bannerTitle="Draft each cycle"
                bannerText={scheduleSummary || 'Each cycle creates a fresh draft invoice. Review and post it when you are ready.'}
                notesValue={data.customer_notes || ''}
                onNotesChange={(value) => setData('customer_notes', value)}
                notesPlaceholder="Will appear on every generated invoice."
                items={data.items}
                shipping={data.shipping_amount}
                onShippingChange={(value) => setData('shipping_amount', value)}
                showShipping
                extraNotes={(
                    <div className="mt-4 pt-4 border-t border-border-warm">
                        <label className={labelClass}>Private notes (internal only)</label>
                        <textarea
                            value={data.private_notes || ''}
                            onChange={(e) => setData('private_notes', e.target.value)}
                            className={`${inputClass} resize-none h-20`}
                            placeholder="Never shown on the invoice."
                        />
                    </div>
                )}
            />
        </form>
    );
}
