import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency, currencyDecimals } from '@/utils/currency';

const Icons = {
    ChevronLeft: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    PaperAirplane: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    X: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>,
    ArrowsRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>,
    Refresh: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h5M20 20v-5h-5M4 20l16-16" /></svg>,
};

const STATUS_STYLES = {
    draft:     'bg-surface-alt text-ink-muted',
    sent:      'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    accepted:  'bg-forest/10 text-forest',
    rejected:  'bg-terracotta/10 text-terracotta',
    expired:   'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    converted: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
};

/** Buttons offered for each status in the order they make sense in the workflow. */
const TRANSITIONS = {
    draft:    [{ to: 'sent',     label: 'Mark as sent',    Icon: Icons.PaperAirplane, tone: 'primary' }],
    sent:     [{ to: 'accepted', label: 'Mark accepted',   Icon: Icons.Check,         tone: 'success' },
               { to: 'rejected', label: 'Mark rejected',   Icon: Icons.X,             tone: 'danger' }],
    accepted: [{ to: 'rejected', label: 'Mark rejected',   Icon: Icons.X,             tone: 'danger' }],
    rejected: [{ to: 'draft',    label: 'Re-open as draft',Icon: Icons.Refresh,       tone: 'neutral' }],
    expired:  [{ to: 'draft',    label: 'Re-open as draft',Icon: Icons.Refresh,       tone: 'neutral' },
               { to: 'sent',     label: 'Send again',      Icon: Icons.PaperAirplane, tone: 'primary' }],
};

const TONE_CLASSES = {
    primary: 'bg-terracotta text-white hover:bg-terracotta-dark',
    success: 'bg-forest text-white hover:bg-forest/90',
    danger:  'bg-surface text-terracotta border border-terracotta hover:bg-terracotta/10',
    neutral: 'bg-surface text-ink border border-border-warm hover:bg-cream',
};

export default function Show({ auth, estimate, base_currency = 'MYR' }) {
    const decimals = currencyDecimals(estimate.currency || base_currency);
    const transitions = TRANSITIONS[estimate.status] || [];
    const canEdit = auth.permissions.includes('estimates.edit') && estimate.status !== 'converted';
    const canConvert = auth.permissions.includes('estimates.convert')
        && ['draft', 'sent', 'accepted'].includes(estimate.status);

    const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

    const handleTransition = (to) => router.post(route('estimates.transition', estimate.id), { status: to }, {
        preserveScroll: true,
    });

    const handleConvert = async () => {
        const ok = await confirm({
            title: 'Convert to Invoice?',
            text: `A new draft invoice will be created from estimate ${estimate.estimate_number}. The estimate will be locked.`,
            confirmText: 'Yes, convert',
            confirmColor: '#7c3aed',
            icon: 'question',
        });
        if (ok) router.post(route('estimates.convert', estimate.id));
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`${estimate.estimate_number} · ${estimate.customer?.name || 'Estimate'}`} />

            <div className="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4">
                    <Link href={route('estimates.index')} className="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink">
                        <Icons.ChevronLeft /> Back to estimates
                    </Link>
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-3 flex-wrap">
                                <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">{estimate.estimate_number}</h1>
                                <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-semibold uppercase tracking-wider ${STATUS_STYLES[estimate.status]}`}>
                                    {estimate.status}
                                </span>
                            </div>
                            <p className="text-sm text-ink-muted mt-1">For {estimate.customer?.name || '—'}</p>
                        </div>

                        {/* Action buttons */}
                        <div className="flex flex-wrap items-center gap-2">
                            {canEdit && (
                                <Link href={route('estimates.edit', estimate.id)} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                                    <Icons.Pencil /> Edit
                                </Link>
                            )}
                            {transitions.map(t => (
                                <button
                                    key={t.to}
                                    type="button"
                                    onClick={() => handleTransition(t.to)}
                                    className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors ${TONE_CLASSES[t.tone]}`}
                                >
                                    <t.Icon /> {t.label}
                                </button>
                            ))}
                            {canConvert && (
                                <button
                                    type="button"
                                    onClick={handleConvert}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 shadow-lg transition-colors"
                                >
                                    <Icons.ArrowsRight /> Convert to Invoice
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Conversion banner */}
                {estimate.status === 'converted' && estimate.converted_invoice && (
                    <div className="bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 rounded-2xl p-4 flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-semibold text-violet-900 dark:text-violet-200">Converted to invoice</p>
                            <p className="text-xs text-violet-700 dark:text-violet-300 mt-0.5">
                                This estimate became invoice <span className="font-mono">{estimate.converted_invoice.invoice_number}</span> ({estimate.converted_invoice.status}). Editing is locked.
                            </p>
                        </div>
                        <Link href={route('invoices.edit', estimate.converted_invoice.id)} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-violet-600 hover:bg-violet-700 shrink-0">
                            Open invoice <Icons.ArrowsRight />
                        </Link>
                    </div>
                )}

                {/* Document body */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 p-6 border-b border-border-warm">
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Issue date</p>
                            <p className="mt-1 text-sm text-ink font-medium">{fmtDate(estimate.issue_date)}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Valid until</p>
                            <p className="mt-1 text-sm text-ink font-medium">{fmtDate(estimate.expiry_date)}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Currency</p>
                            <p className="mt-1 text-sm text-ink font-medium font-mono">{estimate.currency}</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-cream/50 text-[10px] font-display text-ink-muted uppercase tracking-widest">
                                <tr>
                                    <th className="px-6 py-3 text-left">Description</th>
                                    <th className="px-3 py-3 text-center">Qty</th>
                                    <th className="px-3 py-3 text-right">Unit price</th>
                                    <th className="px-3 py-3 text-right">Discount</th>
                                    <th className="px-3 py-3 text-center">Tax %</th>
                                    <th className="px-6 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {(estimate.items || []).map((item, index) => (
                                    <tr key={index}>
                                        <td className="px-6 py-3 text-ink">{item.description}</td>
                                        <td className="px-3 py-3 text-center font-mono">{Number(item.quantity).toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-3 text-right font-mono">{Number(item.unit_price).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}</td>
                                        <td className="px-3 py-3 text-right font-mono text-terracotta">{Number(item.discount_amount || 0) > 0 ? `- ${Number(item.discount_amount).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}` : '—'}</td>
                                        <td className="px-3 py-3 text-center font-mono">{Number(item.tax_rate) > 0 ? `${Number(item.tax_rate).toFixed(2)}%` : '—'}</td>
                                        <td className="px-6 py-3 text-right font-mono font-semibold text-ink">{Number(item.amount).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6 border-t border-border-warm">
                        <div className="lg:col-span-2 space-y-4">
                            {estimate.customer_notes && (
                                <div>
                                    <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Customer-facing notes</p>
                                    <p className="mt-1 text-sm text-ink whitespace-pre-line">{estimate.customer_notes}</p>
                                </div>
                            )}
                            {estimate.private_notes && (
                                <div>
                                    <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Private notes</p>
                                    <p className="mt-1 text-sm text-ink-muted whitespace-pre-line italic">{estimate.private_notes}</p>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between"><span className="text-ink-muted">Subtotal</span><span className="font-mono text-ink">{formatCurrency(estimate.amount_before_tax, estimate.currency)}</span></div>
                            {Number(estimate.discount_total) > 0 && (
                                <div className="flex items-center justify-between"><span className="text-ink-muted">Discount</span><span className="font-mono text-terracotta">- {formatCurrency(estimate.discount_total, estimate.currency)}</span></div>
                            )}
                            <div className="flex items-center justify-between"><span className="text-ink-muted">Tax</span><span className="font-mono text-ink">{formatCurrency(estimate.tax_amount, estimate.currency)}</span></div>
                            {Number(estimate.shipping_amount) > 0 && (
                                <div className="flex items-center justify-between"><span className="text-ink-muted">Shipping</span><span className="font-mono text-ink">{formatCurrency(estimate.shipping_amount, estimate.currency)}</span></div>
                            )}
                            {Math.abs(Number(estimate.rounding_adjustment || 0)) > 0.001 && (
                                <div className="flex items-center justify-between text-xs text-ink-muted/70"><span>Rounding</span><span className="font-mono">{Number(estimate.rounding_adjustment) >= 0 ? '+' : ''}{Number(estimate.rounding_adjustment).toFixed(decimals)}</span></div>
                            )}
                            <div className="border-t border-border-warm pt-2 flex items-center justify-between">
                                <span className="text-sm font-display font-medium text-ink">Total</span>
                                <span className="text-xl font-display font-semibold text-terracotta font-mono tabular-nums">{formatCurrency(estimate.total_amount, estimate.currency)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
