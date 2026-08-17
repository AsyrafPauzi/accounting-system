import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentShowLayout, {
    DocumentShowHeader,
    DocumentLines,
    DocumentTotals,
    docBtn,
    docPrimary,
    field,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, deposit, openBills = [], company = {} }) {
    const currency = 'MYR';
    const open = Number(deposit.open_amount ?? (deposit.amount - deposit.applied_amount));
    const apply = useForm({ bill_id: openBills[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });
    const number = deposit.reference || `DEP-${deposit.id}`;
    const lines = [{
        id: 'payment',
        description: 'Supplier deposit / prepaid',
        quantity: 1,
        unit_price: deposit.amount,
        amount: deposit.amount,
    }];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('ap-deposits.index')}
                    title={number}
                    status={deposit.status}
                    subtitle={deposit.supplier?.name || 'No supplier'}
                />
            }
        >
            <Head title={number} />
            <DocumentShowLayout
                company={company}
                docLabel="Supplier payment"
                docNumber={number}
                meta={[
                    { label: 'Paid', value: formatDate(deposit.payment_date) },
                    deposit.bank_account_code ? { label: 'Bank', value: deposit.bank_account_code } : null,
                ].filter(Boolean)}
                partyTitle="Paid to"
                partyName={deposit.supplier?.name}
                partyLines={partyAddress(deposit.supplier)}
                notes={deposit.notes}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Paid', value: formatCurrency(deposit.amount, currency), tone: 'total' },
                            Number(deposit.applied_amount) > 0 ? { label: 'Applied', value: `−${formatCurrency(deposit.applied_amount, currency)}`, tone: 'negative' } : null,
                            { label: 'Open prepaid', value: formatCurrency(open, currency), tone: 'due' },
                        ].filter(Boolean)}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Open prepaid</p>
                            <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${open > 0 ? 'text-terracotta' : 'text-forest'}`}>
                                {formatCurrency(open, currency)}
                            </p>
                            <p className="mt-1 text-xs text-ink-muted">of {formatCurrency(deposit.amount, currency)} paid · account 1300</p>
                        </div>
                        {deposit.supplier_id && (
                            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                                <Link href={route('suppliers.show', deposit.supplier_id)} className={docBtn}>
                                    Open {deposit.supplier?.name || 'supplier'}
                                </Link>
                            </div>
                        )}
                        {(deposit.applications || []).length > 0 && (
                            <SidebarCard title="Knocked off">
                                {deposit.applications.map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm gap-2">
                                        {a.bill_id ? (
                                            <Link href={route('bills.show', a.bill_id)} className="text-terracotta hover:underline truncate">
                                                {a.bill?.bill_number || `Bill #${a.bill_id}`}
                                            </Link>
                                        ) : (
                                            <span>{a.bill?.bill_number || `Bill #${a.bill_id}`}</span>
                                        )}
                                        <span className="font-mono tabular-nums shrink-0">{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                            </SidebarCard>
                        )}
                        {open > 0 && (
                            <SidebarCard title="Apply leftover">
                                {openBills.length === 0 ? (
                                    <p className="text-sm text-ink-muted">No open bills for this supplier.</p>
                                ) : (
                                    <form
                                        className="space-y-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            apply.post(route('ap-deposits.apply', deposit.id), { preserveScroll: true });
                                        }}
                                    >
                                        <select className={field} value={apply.data.bill_id} onChange={(e) => apply.setData('bill_id', e.target.value)}>
                                            {openBills.map((b) => (
                                                <option key={b.id} value={b.id}>{b.bill_number} · open {formatCurrency(b.balance, currency)}</option>
                                            ))}
                                        </select>
                                        <input className={field} type="number" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                        <button className={docPrimary} disabled={apply.processing}>Apply</button>
                                    </form>
                                )}
                            </SidebarCard>
                        )}
                    </>
                }
            >
                <DocumentLines items={lines} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
