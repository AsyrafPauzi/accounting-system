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
        draft: 'bg-slate-100 text-slate-600',
        unpaid: 'bg-amber-100 text-amber-700',
        'partially paid': 'bg-blue-100 text-blue-700',
        paid: 'bg-emerald-100 text-emerald-700',
        void: 'bg-rose-100 text-rose-700',
    };
    const c = map[status] || 'bg-slate-100 text-slate-600';
    return <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-semibold ${c}`}>{status}</span>;
}

export default function Show({ auth, supplier, bills = [], balance = 0 }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white text-xl font-bold">
                            {(supplier.name || '?').charAt(0)}
                        </div>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">{supplier.name}</h2>
                            <p className="text-slate-500 font-mono text-sm mt-0.5">{supplier.code}</p>
                            {!supplier.is_active && (
                                <span className="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-200 text-slate-600 mt-1">Suspended</span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('suppliers.edit', supplier.id)}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            <Icons.Pencil /> Edit
                        </Link>
                        <Link
                            href={route('bills.create', { supplier_id: supplier.id })}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all"
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
                    <div className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.BuildingOffice /></span>
                            <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Details</h3>
                        </div>
                        <dl className="space-y-2 text-sm">
                            <div><dt className="text-slate-500 font-medium">Contact</dt><dd className="text-slate-800">{supplier.contact_person || supplier.email || '—'}</dd></div>
                            <div><dt className="text-slate-500 font-medium">Email</dt><dd className="text-slate-800">{supplier.email || '—'}</dd></div>
                            <div><dt className="text-slate-500 font-medium">Phone</dt><dd className="text-slate-800">{supplier.phone || '—'}</dd></div>
                            <div><dt className="text-slate-500 font-medium">Payment terms</dt><dd className="text-slate-800">Net {supplier.payment_terms} days</dd></div>
                            <div><dt className="text-slate-500 font-medium">TIN / BRN</dt><dd className="text-slate-800">{supplier.tin || '—'} / {supplier.brn || '—'}</dd></div>
                            {(supplier.billing_street || supplier.billing_city) && (
                                <div>
                                    <dt className="text-slate-500 font-medium">Address</dt>
                                    <dd className="text-slate-800">
                                        {[supplier.billing_street, supplier.billing_city, supplier.billing_state, supplier.billing_zip, supplier.billing_country].filter(Boolean).join(', ')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Document /></span>
                            <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Outstanding</h3>
                        </div>
                        <p className="text-2xl font-bold text-rose-600 font-mono tabular-nums">RM {formatMoney(balance)}</p>
                        <p className="text-xs text-slate-500 mt-1">Balance due from bills</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Bills</h3>
                        <Link href={route('bills.create', { supplier_id: supplier.id })} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100">
                            <Icons.Plus /> New bill
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
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
                                        <tr key={bill.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                            <td className="px-6 py-4 font-mono font-semibold text-slate-800">{bill.bill_number}</td>
                                            <td className="px-6 py-4 text-slate-600">{bill.bill_date}</td>
                                            <td className="px-6 py-4 text-slate-600">{bill.due_date || '—'}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(total)}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(paid)}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-rose-600">RM {formatMoney(bal)}</td>
                                            <td className="px-6 py-4">{statusBadge(bill.status)}</td>
                                            <td className="px-6 py-4 text-right">
                                                <Link href={route('bills.edit', bill.id)} className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700">
                                                    View <Icons.ChevronRight />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-12 text-center text-slate-400 text-sm">
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
