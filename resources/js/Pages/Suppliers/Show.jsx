import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
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

export default function Show({
    auth,
    supplier,
    bills = [],
    balance = 0,
    stats = {},
    myinvois_gaps = [],
}) {
    const currency = supplier.currency || 'MYR';
    const outstanding = Number(stats.balance ?? balance) || 0;
    const billing = partyAddress(supplier).filter((line) => !String(line).startsWith('Tel ') && line !== supplier.email);
    const canBill = auth.permissions.includes('bills.create');
    const canEdit = auth.permissions.includes('suppliers.edit');
    const canPay = auth.permissions.includes('bills.record-payment');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('suppliers.index')}
                    title={supplier.name}
                    status={supplier.is_active ? 'active' : null}
                    subtitle={supplier.code}
                    badges={
                        <>
                            {!supplier.is_active && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-stone-100 text-stone-600">
                                    Suspended
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
                        <Link href={route('suppliers.edit', supplier.id)} className={headerBtn}>Edit</Link>
                    )}
                    {canBill && (
                        <Link href={route('bills.create', { supplier_id: supplier.id })} className={headerPrimary}>
                            New bill
                        </Link>
                    )}
                    <RowActionsMenu
                        items={[
                            { label: 'Statement', href: route('supplier-statements.show', supplier.id), icon: <ActionIcons.Pdf /> },
                            canPay ? { label: 'Make payment', href: route('ap-deposits.create', { supplier_id: supplier.id }), icon: <ActionIcons.Currency /> } : null,
                            { label: 'All bills', href: route('bills.index', { supplier_id: supplier.id }), icon: <ActionIcons.Bill /> },
                        ]}
                    />
                </DocumentShowHeader>
            }
        >
            <Head title={supplier.name} />

            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem] gap-6 items-start pb-8">
                <article className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                    <div className="h-1.5 bg-terracotta" />

                    <div className="px-6 sm:px-10 pt-8 pb-6 grid sm:grid-cols-2 gap-8">
                        <div className="space-y-4">
                            <p className={sectionTitle}>Contact</p>
                            <Fact label="Person">{supplier.contact_person}</Fact>
                            {supplier.email && (
                                <Fact label="Email">
                                    <a href={`mailto:${supplier.email}`} className="text-terracotta hover:underline break-all">{supplier.email}</a>
                                </Fact>
                            )}
                            {supplier.phone && (
                                <Fact label="Phone">
                                    <a href={`tel:${supplier.phone}`} className="hover:text-terracotta">{supplier.phone}</a>
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
                                    <dd className="font-mono tabular-nums text-ink">{supplier.tin || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">{idNumberLabel(supplier.identification_type)}</dt>
                                    <dd className="font-mono tabular-nums text-ink">{supplier.brn || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">SST</dt>
                                    <dd className="font-mono tabular-nums text-ink">{supplier.sst_number || '—'}</dd>
                                </div>
                            </dl>
                            {myinvois_gaps.length > 0 && canEdit && (
                                <p className="text-xs text-ink-muted">
                                    Missing {myinvois_gaps.join(', ').toLowerCase()}.{' '}
                                    <Link href={route('suppliers.edit', supplier.id)} className="text-terracotta font-medium hover:underline">Complete profile</Link>
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="px-6 sm:px-10 pb-8">
                        <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>Billing</p>
                        {billing.length > 0
                            ? billing.map((line) => <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>)
                            : <p className="text-sm text-ink-muted">No billing address</p>}
                    </div>

                    {supplier.internal_notes && (
                        <div className="px-6 sm:px-10 pb-8">
                            <p className={sectionTitle}>Notes</p>
                            <p className="mt-1.5 text-sm text-ink-muted whitespace-pre-line leading-relaxed">{supplier.internal_notes}</p>
                        </div>
                    )}

                    <div className="border-t border-border-warm">
                        <div className="px-6 sm:px-10 py-4 flex items-center justify-between">
                            <p className="text-sm font-semibold text-ink">Bills</p>
                            <span className="text-xs text-ink-muted">{bills.length} shown</span>
                        </div>
                        {bills.length === 0 ? (
                            <div className="px-6 sm:px-10 pb-10 text-sm text-ink-muted">
                                No bills yet.
                                {canBill && (
                                    <>
                                        {' '}
                                        <Link href={route('bills.create', { supplier_id: supplier.id })} className="text-terracotta font-medium hover:underline">
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
                                            <th className="px-6 sm:px-10 py-3 text-left font-semibold">Bill</th>
                                            <th className="px-3 py-3 text-left font-semibold">Date</th>
                                            <th className="px-3 py-3 text-left font-semibold">Status</th>
                                            <th className="px-6 sm:px-10 py-3 text-right font-semibold">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bills.map((bill) => {
                                            const total = Number(bill.total_amount) || 0;
                                            const paid = Number(bill.amount_paid) || 0;
                                            const due = Math.max(0, total - paid);
                                            return (
                                                <tr key={bill.id} className="border-b border-border-warm/60 last:border-0 hover:bg-cream/40">
                                                    <td className="px-6 sm:px-10 py-3">
                                                        <Link href={route('bills.show', bill.id)} className="font-medium text-ink hover:text-terracotta">
                                                            {bill.bill_number}
                                                        </Link>
                                                    </td>
                                                    <td className="px-3 py-3 text-ink-muted whitespace-nowrap">{formatDate(bill.bill_date)}</td>
                                                    <td className="px-3 py-3">
                                                        <span className={`text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md ${statusTone(bill.status)}`}>
                                                            {String(bill.status).replace(/_/g, ' ')}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 sm:px-10 py-3 text-right font-mono tabular-nums text-ink">
                                                        {formatCurrency(due, bill.currency || currency)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </article>

                <aside className="lg:sticky lg:top-4 space-y-4">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                        <p className={sectionTitle}>Outstanding</p>
                        <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${outstanding > 0 ? 'text-terracotta' : 'text-forest'}`}>
                            {formatCurrency(outstanding, currency)}
                        </p>
                        <p className="mt-1 text-xs text-ink-muted">{termsLabel(supplier.payment_terms)}</p>
                    </div>

                    <SidebarCard title="Account">
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Billed</dt>
                                <dd className="font-mono tabular-nums">{formatCurrency(stats.total_billed, currency)}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Paid</dt>
                                <dd className="font-mono tabular-nums text-forest">{formatCurrency(stats.total_paid, currency)}</dd>
                            </div>
                            {Number(supplier.credit_limit) > 0 && stats.remaining_limit != null && (
                                <div className="flex justify-between gap-3 pt-2 border-t border-border-warm">
                                    <dt className="text-ink-muted">Credit left</dt>
                                    <dd className="font-mono tabular-nums">{formatCurrency(stats.remaining_limit, currency)}</dd>
                                </div>
                            )}
                        </dl>
                    </SidebarCard>

                    <SidebarCard>
                        <Link href={route('supplier-statements.show', supplier.id)} className={docBtn}>Statement</Link>
                        {canPay && (
                            <Link href={route('ap-deposits.create', { supplier_id: supplier.id })} className={docBtn}>Make payment</Link>
                        )}
                    </SidebarCard>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
