import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Statement: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M3 7h2m0 0h2M5 7v2m0-2V5m9 4a2 2 0 11-4 0 2 2 0 014 0zM7 13H4a1 1 0 00-1 1v6a1 1 0 001 1h3" /></svg>,
    Download: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>,
    Eye: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>,
    Mail: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>,
    Back: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>,
};

const eventBadge = {
    invoice:     { label: 'Invoice',     classes: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' },
    payment:     { label: 'Payment',     classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
    credit_note: { label: 'Credit note', classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
};

function formatDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Show({ auth, customer, statement, base_currency = 'MYR' }) {
    const [from, setFrom] = useState(statement.from);
    const [to, setTo] = useState(statement.to);
    const [emailing, setEmailing] = useState(false);

    const apply = () => {
        router.get(route('customer-statements.show', customer.id), { from, to }, { preserveState: false });
    };

    const previewUrl = `${route('customer-statements.preview', customer.id)}?from=${from}&to=${to}`;
    const downloadUrl = `${route('customer-statements.pdf', customer.id)}?from=${from}&to=${to}`;

    const sendEmail = () => {
        if (! confirm(`Email this statement to ${customer.name}?`)) return;
        setEmailing(true);
        router.post(route('customer-statements.email', customer.id), { from, to }, {
            preserveScroll: true,
            onFinish: () => setEmailing(false),
        });
    };

    const closingClass = statement.closing_balance > 0 ? 'text-terracotta' : statement.closing_balance < 0 ? 'text-emerald-600' : 'text-ink';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('customer-statements.index')}
                            className="p-2 rounded-xl bg-surface-alt text-ink-muted hover:text-ink"
                            aria-label="Back to customer list"
                        >
                            <Icons.Back />
                        </Link>
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Statement />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">
                                Statement · {customer.name}
                            </h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">
                                {formatDate(statement.from)} &mdash; {formatDate(statement.to)}
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`Statement — ${customer.name}`} />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4 sm:p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-ink-muted mb-1">From</label>
                            <input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="px-3 py-2 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-ink-muted mb-1">To</label>
                            <input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="px-3 py-2 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={apply}
                            className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark"
                        >
                            Apply
                        </button>

                        <div className="flex-1" />

                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={previewUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold text-ink bg-surface-alt hover:bg-cream"
                            >
                                <Icons.Eye /> Preview PDF
                            </a>
                            <a
                                href={downloadUrl}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold text-ink bg-surface-alt hover:bg-cream"
                            >
                                <Icons.Download /> Download
                            </a>
                            <button
                                type="button"
                                onClick={sendEmail}
                                disabled={emailing}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                <Icons.Mail /> {emailing ? 'Sending…' : 'Email to customer'}
                            </button>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Opening balance</div>
                        <div className="mt-2 text-xl font-bold tabular-nums">{formatCurrency(statement.opening_balance, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Charges in period</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">+ {formatCurrency(statement.total_charges, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Payments &amp; credits</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-emerald-600">- {formatCurrency(statement.total_payments + statement.total_credits, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Closing balance</div>
                        <div className={`mt-2 text-xl font-bold tabular-nums ${closingClass}`}>{formatCurrency(statement.closing_balance, base_currency)}</div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Activity</h3>
                            <p className="text-xs text-ink-muted mt-0.5">Chronological list of invoices, payments and credit notes</p>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Date</th>
                                    <th className="px-6 py-3">Type</th>
                                    <th className="px-6 py-3">Reference / Description</th>
                                    <th className="px-6 py-3 text-right">Charge</th>
                                    <th className="px-6 py-3 text-right">Payment</th>
                                    <th className="px-6 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                <tr className="bg-cream/40">
                                    <td className="px-6 py-3 text-xs font-semibold text-ink">{formatDate(statement.from)}</td>
                                    <td className="px-6 py-3 text-xs text-ink-muted italic" colSpan={3}>Opening balance brought forward</td>
                                    <td className="px-6 py-3 text-right text-ink-muted text-xs">—</td>
                                    <td className="px-6 py-3 text-right font-mono tabular-nums text-sm font-semibold">
                                        {formatCurrency(statement.opening_balance, base_currency)}
                                    </td>
                                </tr>

                                {statement.activity.length > 0 ? statement.activity.map((event, idx) => {
                                    const badge = eventBadge[event.type] || { label: event.type, classes: 'bg-surface-alt text-ink' };
                                    return (
                                        <tr key={idx} className="hover:bg-cream/30">
                                            <td className="px-6 py-3 text-xs text-ink">{formatDate(event.date)}</td>
                                            <td className="px-6 py-3">
                                                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${badge.classes}`}>
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3">
                                                {event.invoice_id && event.type === 'invoice' ? (
                                                    <Link
                                                        href={route('invoices.preview', event.invoice_id)}
                                                        className="text-sm font-semibold text-terracotta hover:underline"
                                                    >
                                                        {event.reference}
                                                    </Link>
                                                ) : (
                                                    <span className="text-sm font-semibold text-ink">{event.reference}</span>
                                                )}
                                                <p className="text-xs text-ink-muted mt-0.5">{event.description}</p>
                                            </td>
                                            <td className="px-6 py-3 text-right font-mono tabular-nums text-sm">
                                                {event.charge > 0 ? formatCurrency(event.charge, base_currency) : <span className="text-ink-muted">—</span>}
                                            </td>
                                            <td className="px-6 py-3 text-right font-mono tabular-nums text-sm text-emerald-600">
                                                {event.payment > 0 ? formatCurrency(event.payment, base_currency)
                                                    : event.credit > 0 ? <>{formatCurrency(event.credit, base_currency)} <span className="text-[10px] text-ink-muted">(CN)</span></>
                                                    : <span className="text-ink-muted">—</span>}
                                            </td>
                                            <td className="px-6 py-3 text-right font-mono tabular-nums text-sm font-semibold">
                                                {formatCurrency(event.running_balance, base_currency)}
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-sm text-ink-muted italic">
                                            No activity in this period.
                                        </td>
                                    </tr>
                                )}

                                <tr className="bg-ink/5 dark:bg-ink/20">
                                    <td className="px-6 py-3 text-xs font-bold text-ink">{formatDate(statement.to)}</td>
                                    <td className="px-6 py-3 text-xs font-bold text-ink" colSpan={3}>Closing balance carried forward</td>
                                    <td className="px-6 py-3 text-right text-ink-muted text-xs">—</td>
                                    <td className={`px-6 py-3 text-right font-mono tabular-nums text-base font-bold ${closingClass}`}>
                                        {formatCurrency(statement.closing_balance, base_currency)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
