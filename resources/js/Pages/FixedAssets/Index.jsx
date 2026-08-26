import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

export default function Index({ auth, assets = [] }) {
    const { flash } = usePage().props;
    const [disposeId, setDisposeId] = useState(null);
    const [disposeForm, setDisposeForm] = useState({ disposal_date: new Date().toISOString().slice(0, 10), disposal_proceeds: '' });

    const submitDepreciate = (asset) => {
        const month = new Date().toISOString().slice(0, 10);
        router.post(route('fixed-assets.depreciate', asset.id), { month }, { preserveScroll: true });
    };

    const submitDispose = (e) => {
        e.preventDefault();
        router.post(route('fixed-assets.dispose', disposeId), disposeForm, {
            onSuccess: () => setDisposeId(null),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Fixed assets</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Register PPE, run monthly depreciation, dispose with gain/loss.</p>
                    </div>
                    <Link href={route('fixed-assets.create')} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-forest hover:bg-forest/90">
                        Register asset
                    </Link>
                </div>
            }
        >
            <Head title="Fixed assets" />

            {flash?.success && <div className="mb-4 rounded-xl bg-forest/10 text-forest px-4 py-3 text-sm">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-xl bg-terracotta/10 text-terracotta px-4 py-3 text-sm">{flash.error}</div>}

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted border-b border-border-warm bg-cream/50">
                                <th className="px-4 py-3 text-left">Asset</th>
                                <th className="px-4 py-3 text-right">Cost</th>
                                <th className="px-4 py-3 text-right">Accum. dep.</th>
                                <th className="px-4 py-3 text-right">NBV</th>
                                <th className="px-4 py-3 text-right">Monthly</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assets.length === 0 ? (
                                <tr><td colSpan={7} className="px-4 py-10 text-center text-ink-muted">No fixed assets yet.</td></tr>
                            ) : assets.map((asset) => (
                                <tr key={asset.id} className="border-b border-border-warm last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-ink">{asset.name}</div>
                                        <div className="text-xs text-ink-muted font-mono">{asset.asset_number}</div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatCurrency(asset.cost)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatCurrency(asset.accumulated_depreciation)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums font-semibold">{formatCurrency(asset.net_book_value)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatCurrency(asset.monthly_depreciation)}</td>
                                    <td className="px-4 py-3 capitalize">{asset.status}</td>
                                    <td className="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        {asset.status === 'active' && (
                                            <>
                                                <Link href={route('fixed-assets.edit', asset.id)} className="text-terracotta hover:underline text-xs font-semibold">Edit</Link>
                                                <button type="button" onClick={() => submitDepreciate(asset)} className="text-forest hover:underline text-xs font-semibold">Depreciate</button>
                                                <button type="button" onClick={() => setDisposeId(asset.id)} className="text-ink-muted hover:underline text-xs font-semibold">Dispose</button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {disposeId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4">
                    <form onSubmit={submitDispose} className="bg-surface rounded-2xl border border-border-warm p-6 w-full max-w-md space-y-4">
                        <h3 className="font-semibold text-ink">Dispose asset</h3>
                        <label className="block text-sm">
                            <span className="text-ink-muted">Disposal date</span>
                            <input type="date" required className="mt-1 w-full rounded-lg border border-border-warm px-3 py-2" value={disposeForm.disposal_date} onChange={(e) => setDisposeForm({ ...disposeForm, disposal_date: e.target.value })} />
                        </label>
                        <label className="block text-sm">
                            <span className="text-ink-muted">Proceeds (MYR)</span>
                            <input type="number" min="0" step="0.01" className="mt-1 w-full rounded-lg border border-border-warm px-3 py-2" value={disposeForm.disposal_proceeds} onChange={(e) => setDisposeForm({ ...disposeForm, disposal_proceeds: e.target.value })} />
                        </label>
                        <div className="flex gap-2 justify-end">
                            <button type="button" onClick={() => setDisposeId(null)} className="px-4 py-2 rounded-lg border border-border-warm text-sm">Cancel</button>
                            <button type="submit" className="px-4 py-2 rounded-lg bg-terracotta text-white text-sm font-semibold">Confirm disposal</button>
                        </div>
                    </form>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
