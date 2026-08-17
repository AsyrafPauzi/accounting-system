import React from 'react';
import { Link } from '@inertiajs/react';

export const docBtn = 'inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream text-ink';
export const docPrimary = 'inline-flex items-center justify-center gap-1.5 w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50';
export const docGhost = 'inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-xl text-sm font-medium text-ink-muted hover:text-ink hover:bg-cream';
export const headerBtn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200';
export const headerPrimary = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';
export const field = 'w-full border border-border-warm rounded-xl px-3 py-2 text-sm bg-surface focus:ring-2 focus:ring-terracotta focus:border-terracotta';
export const sectionTitle = 'text-[10px] font-semibold uppercase tracking-wider text-ink-muted';

const ChevronLeft = () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
    </svg>
);

function linesFrom(parts) {
    return parts.filter(Boolean);
}

export function statusTone(status) {
    const map = {
        draft: 'bg-amber-50 text-amber-800',
        confirmed: 'bg-blue-50 text-blue-800',
        sent: 'bg-blue-50 text-blue-800',
        accepted: 'bg-forest/10 text-forest',
        delivered: 'bg-forest/10 text-forest',
        received: 'bg-forest/10 text-forest',
        invoiced: 'bg-forest/10 text-forest',
        billed: 'bg-forest/10 text-forest',
        paid: 'bg-forest/10 text-forest',
        posted: 'bg-forest/10 text-forest',
        unpaid: 'bg-terracotta/10 text-terracotta',
        'partially paid': 'bg-terracotta/10 text-terracotta',
        open: 'bg-terracotta/10 text-terracotta',
        applied: 'bg-forest/10 text-forest',
        closed: 'bg-stone-100 text-stone-600',
        refunded: 'bg-amber-50 text-amber-800',
        active: 'bg-forest/10 text-forest',
        paused: 'bg-amber-50 text-amber-800',
        converted: 'bg-violet-100 text-violet-800',
        partially_delivered: 'bg-terracotta/10 text-terracotta',
        partially_received: 'bg-terracotta/10 text-terracotta',
        cancelled: 'bg-stone-100 text-stone-600',
        void: 'bg-stone-100 text-stone-600',
        rejected: 'bg-terracotta/10 text-terracotta',
        expired: 'bg-amber-50 text-amber-800',
    };
    return map[status] || 'bg-cream text-ink-muted';
}

export function companyAddress(company) {
    if (!company) return [];
    return linesFrom([
        company.address,
        [company.city, company.state, company.zip].filter(Boolean).join(' '),
        company.country,
        company.phone ? `Tel ${company.phone}` : null,
        company.email,
    ]);
}

export function partyAddress(party) {
    if (!party) return [];
    return linesFrom([
        party.billing_street,
        [party.billing_city, party.billing_state, party.billing_zip].filter(Boolean).join(' '),
        party.billing_country,
        party.phone ? `Tel ${party.phone}` : null,
        party.email,
    ]);
}

export function partyShipping(party) {
    if (!party) return [];
    return linesFrom([
        party.shipping_street,
        [party.shipping_city, party.shipping_state, party.shipping_zip].filter(Boolean).join(' '),
        party.shipping_country,
    ]);
}

export function DocumentLines({ items = [], currency = 'MYR', formatCurrency }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className={`${sectionTitle} border-y border-ink/15 bg-cream/40`}>
                        <th className="px-6 sm:px-10 py-3 text-left font-semibold">Description</th>
                        <th className="px-3 py-3 text-right font-semibold w-16">Qty</th>
                        <th className="px-3 py-3 text-right font-semibold w-24">Price</th>
                        <th className="px-6 sm:px-10 py-3 text-right font-semibold w-28">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.id || item.description} className="border-b border-border-warm/60 last:border-0">
                            <td className="px-6 sm:px-10 py-3.5 whitespace-pre-wrap text-ink align-top">{item.description}</td>
                            <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{item.quantity}</td>
                            <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{formatCurrency(item.unit_price ?? item.unit_amount, currency)}</td>
                            <td className="px-6 sm:px-10 py-3.5 text-right font-mono text-ink tabular-nums align-top">{formatCurrency(item.amount, currency)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function DocumentTotals({ rows = [], totalLabel = 'Total', totalValue }) {
    return (
        <dl className="space-y-2 text-sm">
            {rows.map((row) => {
                const isDue = row.tone === 'due';
                const isTotal = row.tone === 'total';
                return (
                    <div
                        key={row.label}
                        className={`flex justify-between gap-6 ${isDue ? 'mt-2 px-3 py-2.5 rounded-xl bg-ink text-white' : isTotal ? 'pt-2 border-t border-ink/15 text-base font-semibold text-ink' : 'text-ink-muted'}`}
                    >
                        <dt className={isDue ? 'text-xs font-semibold uppercase tracking-wider text-white/70 self-center' : ''}>{row.label}</dt>
                        <dd className={`font-mono tabular-nums ${isDue ? 'text-lg font-semibold text-white' : row.tone === 'negative' ? 'text-terracotta' : 'text-ink'}`}>{row.value}</dd>
                    </div>
                );
            })}
            {totalValue != null && (
                <div className="flex justify-between gap-6 pt-2 border-t border-ink/15 text-base font-semibold text-ink">
                    <dt>{totalLabel}</dt>
                    <dd className="font-mono tabular-nums">{totalValue}</dd>
                </div>
            )}
        </dl>
    );
}

export function SidebarCard({ title, children }) {
    return (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5 space-y-3">
            {title && <h3 className="text-sm font-semibold text-ink">{title}</h3>}
            {children}
        </div>
    );
}

export function DocumentShowHeader({ backHref, title, status, subtitle, badges, children }) {
    return (
        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
            <div className="flex items-center gap-2 min-w-0">
                <Link href={backHref} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200 shrink-0">
                    <ChevronLeft />
                </Link>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">{title}</h2>
                        {status && (
                            <span className={`text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md ${statusTone(status)}`}>
                                {String(status).replace(/_/g, ' ')}
                            </span>
                        )}
                        {badges}
                    </div>
                    {subtitle && <p className="text-sm text-ink-muted mt-0.5 truncate">{subtitle}</p>}
                </div>
            </div>
            {children && <div className="flex flex-wrap gap-2 shrink-0">{children}</div>}
        </div>
    );
}

export default function DocumentShowLayout({
    company = {},
    docLabel,
    docNumber,
    meta = [],
    partyTitle = 'Bill to',
    partyName,
    partyLines = [],
    secondaryParty = null,
    children,
    notes,
    footer,
    totals,
    sidebar,
}) {
    const fromLines = companyAddress(company);
    const brand = company.brand_color && company.brand_color !== '#0f172a' ? company.brand_color : null;

    return (
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem] gap-6 items-start pb-8">
            <article className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                <div className={brand ? 'h-1.5' : 'h-1.5 bg-terracotta'} style={brand ? { backgroundColor: brand } : undefined} />

                <div className="px-6 sm:px-10 pt-8 pb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                    <div className="min-w-0">
                        {company.logo_url && (
                            <img src={company.logo_url} alt="" className="h-10 w-auto max-w-[180px] object-contain mb-3" />
                        )}
                        <p className="text-lg font-display font-medium text-ink tracking-tight">{company.name || docLabel}</p>
                        {fromLines.map((line) => (
                            <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                        ))}
                        {(company.tin || company.brn) && (
                            <p className="text-xs text-ink-muted mt-1">
                                {company.brn ? `Reg ${company.brn}` : ''}
                                {company.brn && company.tin ? ' · ' : ''}
                                {company.tin ? `TIN ${company.tin}` : ''}
                            </p>
                        )}
                    </div>
                    <div className="text-left sm:text-right shrink-0">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-ink-muted">{docLabel}</p>
                        <p className="mt-1 text-2xl font-display font-medium text-ink tracking-tight">{docNumber}</p>
                        {meta.length > 0 && (
                            <dl className="mt-4 space-y-1.5 text-sm">
                                {meta.map((row) => (
                                    <div key={row.label} className="flex sm:justify-end gap-3">
                                        <dt className="text-ink-muted">{row.label}</dt>
                                        <dd className="font-medium text-ink tabular-nums">{row.value || '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>
                </div>

                <div className="px-6 sm:px-10 pb-8 grid sm:grid-cols-2 gap-8">
                    <div>
                        <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>{partyTitle}</p>
                        <p className="font-semibold text-ink">{partyName || '—'}</p>
                        {partyLines.map((line) => (
                            <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                        ))}
                    </div>
                    {secondaryParty && (
                        <div>
                            <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>{secondaryParty.title}</p>
                            {secondaryParty.name && <p className="font-semibold text-ink">{secondaryParty.name}</p>}
                            {(secondaryParty.lines || []).map((line) => (
                                <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                            ))}
                        </div>
                    )}
                </div>

                {children}

                {(notes || totals || footer) && (
                    <div className="px-6 sm:px-10 py-8 grid sm:grid-cols-[minmax(0,1fr)_16rem] gap-8 items-start">
                        <div className="space-y-5">
                            {notes && (
                                <div>
                                    <p className={sectionTitle}>Notes</p>
                                    <p className="mt-1.5 text-sm text-ink-muted whitespace-pre-line leading-relaxed">{notes}</p>
                                </div>
                            )}
                            {footer}
                        </div>
                        {totals}
                    </div>
                )}
            </article>

            <aside className="lg:sticky lg:top-4 space-y-4">
                {sidebar}
            </aside>
        </div>
    );
}
