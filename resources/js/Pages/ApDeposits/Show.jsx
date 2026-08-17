import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

export default function Show({ auth, deposit, openBills = [] }) {
    const open = Number(deposit.open_amount ?? (deposit.amount - deposit.applied_amount));
    const apply = useForm({ bill_id: openBills[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={deposit.reference || `DEP-${deposit.id}`} />
            <div className="space-y-4 min-w-0">
                <Link href={route('ap-deposits.index')} className="text-xs text-ink-muted">← Supplier deposits</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{deposit.reference || `DEP-${deposit.id}`}</h1>
                        <p className="text-sm text-ink-muted">
                            {deposit.supplier?.name} · {formatDate(deposit.payment_date)} · {deposit.status} · unapplied {formatCurrency(open, 'MYR')}
                        </p>
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border p-5 space-y-2 text-sm">
                    <div className="flex justify-between"><span>Paid</span><span className="font-mono">{formatCurrency(deposit.amount, 'MYR')}</span></div>
                    <div className="flex justify-between"><span>Applied</span><span className="font-mono">{formatCurrency(deposit.applied_amount, 'MYR')}</span></div>
                    <div className="flex justify-between font-semibold"><span>Open prepaid (1300)</span><span className="font-mono">{formatCurrency(open, 'MYR')}</span></div>
                    <p className="text-ink-muted">{deposit.bank_account_code}{deposit.notes ? ` · ${deposit.notes}` : ''}</p>
                </div>
                {(deposit.applications || []).length > 0 && (
                    <div className="bg-surface rounded-2xl border p-5 space-y-2">
                        <h3 className="text-sm font-semibold">Knocked off</h3>
                        {deposit.applications.map((a) => (
                            <div key={a.id} className="flex justify-between text-sm">
                                <Link className="text-terracotta" href={a.bill_id ? route('bills.show', a.bill_id) : '#'}>{a.bill?.bill_number || `Bill #${a.bill_id}`}</Link>
                                <span className="font-mono">{formatCurrency(a.amount, 'MYR')}</span>
                            </div>
                        ))}
                    </div>
                )}
                {open > 0 && openBills.length > 0 && (
                    <form
                        className="bg-surface rounded-2xl border p-5 space-y-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply.post(route('ap-deposits.apply', deposit.id), { preserveScroll: true });
                        }}
                    >
                        <h3 className="text-sm font-semibold">Apply to bill</h3>
                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Bill</label>
                        <select className="w-full border rounded-xl px-3 py-2 text-sm" value={apply.data.bill_id} onChange={(e) => apply.setData('bill_id', e.target.value)}>
                            {openBills.map((b) => <option key={b.id} value={b.id}>{b.bill_number} · open {formatCurrency(b.balance, 'MYR')}</option>)}
                        </select>
                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Amount</label>
                        <input type="number" step="0.01" className="w-full border rounded-xl px-3 py-2 text-sm" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                        <button className="px-4 py-2 rounded-xl bg-terracotta text-white text-sm font-semibold" disabled={apply.processing}>Apply</button>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
