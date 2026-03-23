import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    BookOpen: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Show({ auth, entry = {}, items = [], accountsMap = {} }) {
    const getSourceLabel = () => {
        if (entry.reference_type === 'Invoice' || entry.reference_type === 'Invoice Payment') return 'View Invoice';
        if (entry.reference_type === 'Credit Note') return 'View Credit Note';
        if (entry.reference_type === 'Bill' || entry.reference_type === 'Bill Payment') return 'View Bill';
        return null;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Journal entry</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            {entry.date} — {entry.description}
                        </p>
                    </div>
                    <Link
                        href={route('general-ledger.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                    >
                        Back to General Ledger
                    </Link>
                </div>
            }
        >
            <Head title={`Ledger entry ${entry.date}`} />

            <div className="space-y-6">
                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-4">Summary</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Date</p>
                            <p className="font-mono text-slate-800">{entry.date}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Reference</p>
                            <p className="text-slate-800">{entry.reference_type}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Balanced</p>
                            <p className={entry.balanced ? 'text-emerald-600 font-semibold' : 'text-slate-600'}>
                                {entry.balanced ? (
                                    <span className="inline-flex items-center gap-1"><Icons.Check /> Yes</span>
                                ) : (
                                    'No'
                                )}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Source document</p>
                            {entry.source_route ? (
                                <a href={entry.source_route} className="text-blue-600 hover:text-blue-700 font-semibold text-sm">
                                    {getSourceLabel()}
                                </a>
                            ) : (
                                <span className="text-slate-400">—</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Line items</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Account code</th>
                                    <th className="px-6 py-4">Account name</th>
                                    <th className="px-6 py-4 text-right">Debit</th>
                                    <th className="px-6 py-4 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((line) => (
                                    <tr key={line.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                        <td className="px-6 py-4 font-mono font-semibold text-slate-800">{line.account_code}</td>
                                        <td className="px-6 py-4 text-slate-600">{accountsMap[line.account_code] || '—'}</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">
                                            {line.debit > 0 ? `RM ${formatMoney(line.debit)}` : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">
                                            {line.credit > 0 ? `RM ${formatMoney(line.credit)}` : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-slate-200 bg-slate-50/80 font-semibold">
                                    <td className="px-6 py-4" colSpan={2}>Total</td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">RM {formatMoney(entry.total_debit)}</td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">RM {formatMoney(entry.total_credit)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
