import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import { PAYMENT_TERM_PRESETS, idNumberLabel } from '@/constants/customerFormOptions';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import {
    DocumentShowHeader,
    SidebarCard,
    docBtn,
    headerBtn,
    headerPrimary,
    partyAddress,
    partyShipping,
    sectionTitle,
    statusTone,
} from '@/Components/DocumentShowLayout';

function termsLabel(days) {
    const preset = PAYMENT_TERM_PRESETS.find((p) => p.value === Number(days));
    return preset ? preset.label : `Net ${days} days`;
}

function Fact({ label, children }) {
    if (!children) return null;
    return (
        <div>
            <p className={sectionTitle}>{label}</p>
            <div className="mt-1 text-sm text-ink leading-relaxed">{children}</div>
        </div>
    );
}

function AgingBar({ aging = {}, currency }) {
    const buckets = [
        { key: '0_30', label: 'Current', tone: 'bg-forest' },
        { key: '31_60', label: '31–60', tone: 'bg-mustard' },
        { key: '61_90', label: '61–90', tone: 'bg-terracotta/70' },
        { key: '90_plus', label: '90+', tone: 'bg-terracotta' },
    ];
    const total = buckets.reduce((sum, b) => sum + (Number(aging[b.key]) || 0), 0);
    if (total <= 0) return null;

    return (
        <div className="space-y-2">
            <p className={sectionTitle}>Aging</p>
            <div className="flex h-1.5 rounded-full overflow-hidden bg-cream">
                {buckets.map((b) => {
                    const amt = Number(aging[b.key]) || 0;
                    if (amt <= 0) return null;
                    return <div key={b.key} className={b.tone} style={{ width: `${(amt / total) * 100}%` }} />;
                })}
            </div>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1">
                {buckets.map((b) => (
                    <div key={b.key} className="flex items-baseline justify-between gap-2 text-xs">
                        <span className="text-ink-muted">{b.label}</span>
                        <span className="font-mono tabular-nums text-ink">{formatCurrency(aging[b.key] || 0, currency)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Show({
    auth,
    customer,
    invoices = [],
    stats = {},
    auditLogs = [],
    can_delete_customer = false,
    delete_blocked_reason = null,
    myinvois_gaps = [],
}) {
    const { flash } = usePage().props;
    const currency = customer.currency || 'MYR';
    const outstanding = Number(stats.balance) || 0;
    const websiteUrl = customer.website
        ? (customer.website.startsWith('http') ? customer.website : `https://${customer.website}`)
        : null;
    const billing = partyAddress(customer).filter((line) => !String(line).startsWith('Tel ') && line !== customer.email);
    const shipping = partyShipping(customer);
    const recent = invoices.slice(0, 12);
    const canInvoice = auth.permissions.includes('invoices.create');
    const canEdit = auth.permissions.includes('customers.edit');
    const canDelete = auth.permissions.includes('customers.delete');
    const canReceive = auth.permissions.includes('invoices.record-payment');

    const handleDelete = async () => {
        if (!can_delete_customer) return;
        const ok = await confirm({
            title: 'Delete this customer?',
            text: `Remove "${customer.name}" from your directory? This cannot be undone.`,
            confirmText: 'Delete customer',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('customers.destroy', customer.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('customers.index')}
                    title={customer.name}
                    status={customer.is_active ? 'active' : null}
                    subtitle={[customer.code, customer.industry].filter(Boolean).join(' · ')}
                    badges={
                        <>
                            {!customer.is_active && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-stone-100 text-stone-600">
                                    Suspended
                                </span>
                            )}
                            {customer.credit_hold && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-mustard/20 text-ink">
                                    Credit hold
                                </span>
                            )}
                            {myinvois_gaps.length > 0 && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-mustard/15 text-ink">
                                    MyInvois incomplete
                                </span>
                            )}
                        </>
                    }
                >
                    {canEdit && (
                        <Link href={route('customers.edit', customer.id)} className={headerBtn}>Edit</Link>
                    )}
                    {canInvoice && (
                        <Link href={route('invoices.create', { customer_id: customer.id })} className={headerPrimary}>
                            New invoice
                        </Link>
                    )}
                    <RowActionsMenu
                        items={[
                            { label: 'Statement', href: route('customer-statements.show', customer.id), icon: <ActionIcons.Pdf /> },
                            {
                                label: 'Portal link',
                                icon: <ActionIcons.Open />,
                                onClick: () => router.post(route('customers.portal-link', customer.id)),
                            },
                            canReceive ? { label: 'Receive payment', href: route('ar-deposits.create', { customer_id: customer.id }), icon: <ActionIcons.Currency /> } : null,
                            { label: 'All invoices', href: route('invoices.index'), icon: <ActionIcons.Invoice /> },
                            canDelete ? {
                                label: 'Delete',
                                icon: <ActionIcons.Trash />,
                                danger: true,
                                disabled: !can_delete_customer,
                                onClick: handleDelete,
                            } : null,
                        ]}
                    />
                </DocumentShowHeader>
            }
        >
            <Head title={customer.name} />

            {flash?.portal_url && (
                <div className="mb-4 rounded-xl border border-forest/30 bg-forest/5 px-4 py-3 text-sm text-ink">
                    <p className="font-semibold text-forest mb-1">Customer portal link</p>
                    <a href={flash.portal_url} className="break-all text-terracotta hover:underline" target="_blank" rel="noreferrer">{flash.portal_url}</a>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem] gap-6 items-start pb-8">
                <article className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                    <div className="h-1.5 bg-terracotta" />

                    <div className="px-6 sm:px-10 pt-8 pb-6 grid sm:grid-cols-2 gap-8">
                        <div className="space-y-4">
                            <p className={sectionTitle}>Contact</p>
                            <Fact label="Person">{customer.contact_person}</Fact>
                            {customer.email && (
                                <Fact label="Email">
                                    <a href={`mailto:${customer.email}`} className="text-terracotta hover:underline break-all">{customer.email}</a>
                                </Fact>
                            )}
                            {customer.phone && (
                                <Fact label="Phone">
                                    <a href={`tel:${customer.phone}`} className="hover:text-terracotta">{customer.phone}</a>
                                </Fact>
                            )}
                            {websiteUrl && (
                                <Fact label="Website">
                                    <a href={websiteUrl} target="_blank" rel="noreferrer" className="text-terracotta hover:underline break-all">
                                        {customer.website.replace(/^https?:\/\//, '')}
                                    </a>
                                </Fact>
                            )}
                            {(customer.contacts || []).length > 0 && (
                                <Fact label="Additional contacts">
                                    <ul className="space-y-1.5">
                                        {customer.contacts.map((c) => (
                                            <li key={c.id}>
                                                <span className="font-medium">{c.name || '—'}</span>
                                                {c.type && <span className="text-ink-muted text-xs ml-1">({c.type})</span>}
                                                {c.email && (
                                                    <a href={`mailto:${c.email}`} className="block text-xs text-terracotta hover:underline">{c.email}</a>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </Fact>
                            )}
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className={sectionTitle}>MyInvois</p>
                                {myinvois_gaps.length === 0 ? (
                                    <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-forest/10 text-forest">Ready</span>
                                ) : (
                                    <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-mustard/15 text-ink">
                                        {myinvois_gaps.length} missing
                                    </span>
                                )}
                            </div>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">TIN</dt>
                                    <dd className="font-mono tabular-nums text-ink">{customer.tin || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">{idNumberLabel(customer.identification_type)}</dt>
                                    <dd className="font-mono tabular-nums text-ink">{customer.brn || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">SST</dt>
                                    <dd className="font-mono tabular-nums text-ink">{customer.sst_number || '—'}</dd>
                                </div>
                            </dl>
                            {myinvois_gaps.length > 0 && canEdit && (
                                <p className="text-xs text-ink-muted">
                                    Missing {myinvois_gaps.join(', ').toLowerCase()}.{' '}
                                    <Link href={route('customers.edit', customer.id)} className="text-terracotta font-medium hover:underline">Complete profile</Link>
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="px-6 sm:px-10 pb-8 grid sm:grid-cols-2 gap-8">
                        <div>
                            <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>Billing</p>
                            {billing.length > 0
                                ? billing.map((line) => <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>)
                                : <p className="text-sm text-ink-muted">No billing address</p>}
                        </div>
                        <div>
                            <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>Shipping</p>
                            {shipping.length > 0
                                ? shipping.map((line) => <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>)
                                : <p className="text-sm text-ink-muted">No shipping address</p>}
                        </div>
                    </div>

                    {customer.internal_notes && (
                        <div className="px-6 sm:px-10 pb-8">
                            <p className={sectionTitle}>Notes</p>
                            <p className="mt-1.5 text-sm text-ink-muted whitespace-pre-line leading-relaxed">{customer.internal_notes}</p>
                        </div>
                    )}

                    <div className="border-t border-border-warm">
                        <div className="px-6 sm:px-10 py-4 flex items-center justify-between">
                            <p className="text-sm font-semibold text-ink">Invoices</p>
                            <span className="text-xs text-ink-muted">{invoices.length} total</span>
                        </div>
                        {recent.length === 0 ? (
                            <div className="px-6 sm:px-10 pb-10 text-sm text-ink-muted">
                                No invoices yet.
                                {canInvoice && (
                                    <>
                                        {' '}
                                        <Link href={route('invoices.create', { customer_id: customer.id })} className="text-terracotta font-medium hover:underline">
                                            Create one
                                        </Link>
                                    </>
                                )}
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className={`${sectionTitle} border-y border-ink/10 bg-cream/40`}>
                                            <th className="px-6 sm:px-10 py-3 text-left font-semibold">Invoice</th>
                                            <th className="px-3 py-3 text-left font-semibold">Date</th>
                                            <th className="px-3 py-3 text-left font-semibold">Status</th>
                                            <th className="px-6 sm:px-10 py-3 text-right font-semibold">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recent.map((inv) => (
                                            <tr key={inv.id} className="border-b border-border-warm/60 last:border-0 hover:bg-cream/40">
                                                <td className="px-6 sm:px-10 py-3">
                                                    <Link href={route('invoices.show', inv.id)} className="font-medium text-ink hover:text-terracotta">
                                                        {inv.invoice_number}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-3 text-ink-muted whitespace-nowrap">{formatDate(inv.issue_date)}</td>
                                                <td className="px-3 py-3">
                                                    <span className={`text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md ${statusTone(inv.status)}`}>
                                                        {String(inv.status).replace(/_/g, ' ')}
                                                    </span>
                                                </td>
                                                <td className="px-6 sm:px-10 py-3 text-right font-mono tabular-nums text-ink">
                                                    {formatCurrency(inv.total_amount, inv.currency || currency)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {invoices.length > recent.length && (
                            <div className="px-6 sm:px-10 py-4">
                                <Link href={route('customer-statements.show', customer.id)} className="text-sm font-medium text-terracotta hover:underline">
                                    View full statement
                                </Link>
                            </div>
                        )}
                    </div>

                    {auditLogs.length > 0 && (
                        <details className="border-t border-border-warm">
                            <summary className="px-6 sm:px-10 py-4 text-sm font-semibold text-ink cursor-pointer select-none">
                                History
                            </summary>
                            <ul className="px-6 sm:px-10 pb-8 space-y-3">
                                {auditLogs.slice(0, 20).map((log) => (
                                    <li key={log.id} className="text-sm text-ink-muted border-l-2 border-border-warm pl-3">
                                        <span className="text-ink">{String(log.field).replace(/_/g, ' ')}</span>
                                        {' '}from <span className="font-mono">{log.old_value || '—'}</span>
                                        {' '}to <span className="font-mono text-ink">{log.new_value || '—'}</span>
                                        {log.user && <span> · {log.user.name}</span>}
                                        <span> · {formatDate(log.created_at)}</span>
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}
                </article>

                <aside className="lg:sticky lg:top-4 space-y-4">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                        <p className={sectionTitle}>Outstanding</p>
                        <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${outstanding > 0 ? 'text-terracotta' : 'text-forest'}`}>
                            {formatCurrency(outstanding, currency)}
                        </p>
                        <p className="mt-1 text-xs text-ink-muted">{termsLabel(customer.payment_terms)}</p>
                        {outstanding > 0 && <div className="mt-4"><AgingBar aging={stats.aging} currency={currency} /></div>}
                    </div>

                    <SidebarCard title="Account">
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Invoiced</dt>
                                <dd className="font-mono tabular-nums">{formatCurrency(stats.total_invoiced, currency)}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Collected</dt>
                                <dd className="font-mono tabular-nums text-forest">{formatCurrency(stats.total_paid, currency)}</dd>
                            </div>
                            {Number(customer.credit_limit) > 0 && (
                                <div className="flex justify-between gap-3 pt-2 border-t border-border-warm">
                                    <dt className="text-ink-muted">Credit left</dt>
                                    <dd className="font-mono tabular-nums">{formatCurrency(stats.remaining_limit, currency)}</dd>
                                </div>
                            )}
                        </dl>
                    </SidebarCard>

                    <SidebarCard>
                        <Link href={route('customer-statements.show', customer.id)} className={docBtn}>Statement</Link>
                        {canReceive && (
                            <Link href={route('ar-deposits.create', { customer_id: customer.id })} className={docBtn}>Receive payment</Link>
                        )}
                        {canDelete && !can_delete_customer && delete_blocked_reason && (
                            <p className="text-xs text-ink-muted px-1">{delete_blocked_reason}</p>
                        )}
                    </SidebarCard>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
