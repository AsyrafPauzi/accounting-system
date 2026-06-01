import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusBadge(status) {
    const map = {
        draft: 'bg-surface-alt text-ink',
        unpaid: 'bg-mustard/15 text-mustard',
        'partially paid': 'bg-surface-alt text-terracotta',
        paid: 'bg-forest/10 text-forest',
        void: 'bg-terracotta/10 text-terracotta',
    };
    const c = map[status] || 'bg-surface-alt text-ink';
    return <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-semibold ${c}`}>{status}</span>;
}

export default function Show({ auth, supplier, bills = [], balance = 0 }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                        <div className="w-11 h-11 rounded-xl bg-mustard flex items-center justify-center text-ink text-base font-semibold shrink-0">
                            {(supplier.name || '?').charAt(0)}
                        </div>
                        <div className="min-w-0">
                            <h1 className="font-display text-lg lg:text-xl font-medium text-ink tracking-tight leading-tight break-words">{supplier.name}</h1>
                            <p className="text-ink-muted font-mono font-tabular text-xs mt-0.5">{supplier.code}</p>
                            {!supplier.is_active && (
                                <span className="inline-flex px-2 py-0.5 rounded-md text-eyebrow font-semibold uppercase bg-surface-alt text-ink-muted mt-1">Suspended</span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 shrink-0">
                        <Link
                            href={route('suppliers.edit', supplier.id)}
                            className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-ink bg-surface border border-border-warm hover:bg-surface-alt transition-colors"
                        >
                            <Icons.Pencil /> Edit
                        </Link>
                        <Link
                            href={route('bills.create', { supplier_id: supplier.id })}
                            className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                        >
                            <Icons.Plus /> Create bill
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={supplier.name} />

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.BuildingOffice /></span>
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Details</h3>
                        </div>
                        <dl className="space-y-2 text-sm">
                            <div><dt className="text-ink-muted font-medium">Contact</dt><dd className="text-ink">{supplier.contact_person || supplier.email || '—'}</dd></div>
                            <div><dt className="text-ink-muted font-medium">Email</dt><dd className="text-ink">{supplier.email || '—'}</dd></div>
                            <div><dt className="text-ink-muted font-medium">Phone</dt><dd className="text-ink">{supplier.phone || '—'}</dd></div>
                            <div><dt className="text-ink-muted font-medium">Payment terms</dt><dd className="text-ink">Net {supplier.payment_terms} days</dd></div>
                            <div><dt className="text-ink-muted font-medium">TIN / BRN</dt><dd className="text-ink">{supplier.tin || '—'} / {supplier.brn || '—'}</dd></div>
                            {(supplier.billing_street || supplier.billing_city) && (
                                <div>
                                    <dt className="text-ink-muted font-medium">Address</dt>
                                    <dd className="text-ink">
                                        {[supplier.billing_street, supplier.billing_city, supplier.billing_state, supplier.billing_zip, supplier.billing_country].filter(Boolean).join(', ')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Document /></span>
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Outstanding</h3>
                        </div>
                        <p className="text-2xl font-bold text-terracotta font-mono tabular-nums">RM {formatMoney(balance)}</p>
                        <p className="text-xs text-ink-muted mt-1">Balance due from bills</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between bg-cream/50">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Bills</h3>
                        <Link href={route('bills.create', { supplier_id: supplier.id })} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt">
                            <Icons.Plus /> New bill
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Bill #</th>
                                    <th className="px-6 py-4">Date</th>
                                    <th className="px-6 py-4">Due date</th>
                                    <th className="px-6 py-4 text-right">Total</th>
                                    <th className="px-6 py-4 text-right">Paid</th>
                                    <th className="px-6 py-4 text-right">Balance</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bills.length > 0 ? bills.map(bill => {
                                    const total = parseFloat(bill.total_amount) || 0;
                                    const paid = parseFloat(bill.amount_paid) || 0;
                                    const bal = Math.max(0, total - paid);
                                    return (
                                        <tr key={bill.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-6 py-4 font-mono font-semibold text-ink">{bill.bill_number}</td>
                                            <td className="px-6 py-4 text-ink">{bill.bill_date}</td>
                                            <td className="px-6 py-4 text-ink">{bill.due_date || '—'}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(total)}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(paid)}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-terracotta">RM {formatMoney(bal)}</td>
                                            <td className="px-6 py-4">{statusBadge(bill.status)}</td>
                                            <td className="px-6 py-4 text-right">
                                                <Link href={route('bills.edit', bill.id)} className="inline-flex items-center gap-1 text-xs font-semibold text-terracotta hover:text-terracotta">
                                                    View <Icons.ChevronRight />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-12 text-center text-ink-muted text-sm">
                                            No bills yet. Create a bill for this supplier.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
