import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const Icons = {
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    Calendar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>,
    CheckCircle: () => <svg className="w-5 h-5 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    AlertTriangle: () => <svg className="w-5 h-5 text-terracotta" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.268 17c-.77 1.333.192 3 1.732 3z" /></svg>,
};

export default function TrialBalance({ auth, trialBalance, totals, filters }) {
    const handleDateChange = (date) => {
        router.get(route('trial-balance.index'), { as_of_date: date }, { preserveState: true });
    };

    const isBalanced = totals.difference < 0.01;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Scale />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Trial Balance</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">Verification of double-entry mathematical accuracy</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 bg-surface p-2 rounded-xl border border-border-warm shadow-sm">
                        <Icons.Calendar />
                        <input 
                            type="date" 
                            value={filters.as_of_date} 
                            onChange={(e) => handleDateChange(e.target.value)}
                            className="border-none focus:ring-0 text-sm font-display font-medium text-ink bg-transparent"
                        />
                    </div>
                </div>
            }
        >
            <Head title="Trial Balance" />

            <div className="space-y-6">
                {/* Status Card */}
                <div className={`p-6 rounded-2xl border flex items-center justify-between ${isBalanced ? 'bg-forest/10 border-forest/30' : 'bg-terracotta/10 border-terracotta/30'}`}>
                    <div className="flex items-center gap-4">
                        <span className={`p-3 rounded-xl ${isBalanced ? 'bg-forest/10' : 'bg-terracotta/10'}`}>
                            {isBalanced ? <Icons.CheckCircle /> : <Icons.AlertTriangle />}
                        </span>
                        <div>
                            <h3 className={`text-lg font-bold ${isBalanced ? 'text-forest-dark' : 'text-terracotta'}`}>
                                {isBalanced ? 'System is Balanced' : 'Out of Balance!'}
                            </h3>
                            <p className={`text-sm ${isBalanced ? 'text-forest' : 'text-terracotta'}`}>
                                {isBalanced 
                                    ? 'Total debits equal total credits. Mathematical accuracy is verified.' 
                                    : `There is a discrepancy of RM ${totals.difference.toLocaleString('en-MY', { minimumFractionDigits: 2 })}. Please check your manual journal entries.`}
                            </p>
                        </div>
                    </div>
                    <div className="text-right hidden sm:block">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-ink-muted mb-1">Total of each side</p>
                        <p className="text-xl font-mono font-display font-medium text-ink">RM {totals.debit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                <th className="p-6">Account Code</th>
                                <th className="p-6">Account Name</th>
                                <th className="p-6 text-right">Debit (RM)</th>
                                <th className="p-6 text-right">Credit (RM)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {trialBalance.length > 0 ? (
                                trialBalance.map((item) => (
                                    <tr key={item.id} className="group hover:bg-cream/50 transition-colors duration-200">
                                        <td className="p-6">
                                            <span className="font-mono text-sm font-display font-medium text-ink bg-surface-alt px-2 py-1 rounded">
                                                {item.code}
                                            </span>
                                        </td>
                                        <td className="p-6">
                                            <div className="text-sm font-medium text-ink">{item.name}</div>
                                            <div className="text-[10px] text-ink-muted mt-0.5 uppercase font-bold tracking-tighter">
                                                {item.type}
                                            </div>
                                        </td>
                                        <td className="p-6 text-right font-mono font-display font-medium text-ink">
                                            {item.debit > 0 ? item.debit.toLocaleString('en-MY', { minimumFractionDigits: 2 }) : '—'}
                                        </td>
                                        <td className="p-6 text-right font-mono font-display font-medium text-ink">
                                            {item.credit > 0 ? item.credit.toLocaleString('en-MY', { minimumFractionDigits: 2 }) : '—'}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="4" className="p-12 text-center text-ink-muted font-medium">
                                        No transactions found for the selected date.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot className="bg-cream border-t-2 border-border-warm">
                            <tr className="font-display font-medium text-ink">
                                <td colSpan="2" className="p-6 text-right text-xs uppercase tracking-widest">Grand Totals</td>
                                <td className="p-6 text-right font-mono text-lg underline decoration-double">
                                    {totals.debit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </td>
                                <td className="p-6 text-right font-mono text-lg underline decoration-double">
                                    {totals.credit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div className="bg-surface-alt border border-border-warm p-6 rounded-2xl">
                    <h4 className="text-sm font-display font-medium text-ink mb-2 uppercase tracking-wide">About this report</h4>
                    <p className="text-sm text-ink leading-relaxed">
                        Each account shows its <strong>net balance</strong> on the side it normally lives on — assets and expenses on the left (Debit), liabilities, equity, and income on the right (Credit). Accounts that have been fully cleared (e.g. invoices paid off, statutory remittances settled) are hidden because their balance is zero. In a balanced ledger, the grand total of debits always equals the grand total of credits.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
