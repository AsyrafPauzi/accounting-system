import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

function DeactivateModal({ plan, onClose }) {
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        router.put(route('admin.plans.update', plan.id), { ...plan, is_active: false, permissions: plan.permissions }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-surface shadow-2xl">
                <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                    <h3 className="text-base font-display font-medium text-ink">Deactivate plan?</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-surface-alt text-ink-muted">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-5 space-y-3">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-mustard/15 flex items-center justify-center">
                            <svg className="w-5 h-5 text-mustard" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-ink">{plan.name}</p>
                            <p className="text-xs text-ink-muted font-mono">{plan.slug}</p>
                        </div>
                    </div>

                    <p className="text-sm text-ink">
                        This plan will be hidden from the subscription page and cannot be assigned to new tenants.
                    </p>

                    {plan.subscriptions_count > 0 && (
                        <div className="rounded-xl bg-mustard/15 border border-mustard/40 px-4 py-3 text-xs text-ink">
                            <span className="font-bold">{plan.subscriptions_count} tenant{plan.subscriptions_count !== 1 ? 's' : ''}</span> are currently on this plan. Their subscriptions will remain active until they naturally expire — no immediate disruption.
                        </div>
                    )}
                </div>

                <div className="px-6 py-4 border-t border-border-warm flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface-alt hover:bg-surface-alt transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={processing}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-mustard hover:bg-mustard disabled:opacity-60 transition-colors"
                    >
                        {processing ? 'Deactivating…' : 'Deactivate Plan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function AdminPlansIndex({ auth, plans = [] }) {
    const [deactivatingPlan, setDeactivatingPlan] = useState(null);
    const [activatingId, setActivatingId] = useState(null);

    const handleActivate = (plan) => {
        setActivatingId(plan.id);
        router.put(route('admin.plans.update', plan.id), { ...plan, is_active: true, permissions: plan.permissions }, {
            preserveScroll: true,
            onFinish: () => setActivatingId(null),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Admin</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Plan catalog</h1>
                        <p className="text-ink-muted text-sm">
                            Tiers, pricing, and feature permissions you offer to tenants.
                        </p>
                    </div>
                    <Link
                        href={route('admin.plans.create')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta transition-colors"
                    >
                        + New Plan
                    </Link>
                </div>
            }
        >
            <Head title="Plan Catalog" />

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Plans</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest bg-cream/80">
                                <th className="px-6 py-4">Name / Slug</th>
                                <th className="px-6 py-4">Monthly</th>
                                <th className="px-6 py-4">Yearly</th>
                                <th className="px-6 py-4">Users</th>
                                <th className="px-6 py-4">Permissions</th>
                                <th className="px-6 py-4">Subscribers</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4 w-36">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {plans.map((plan) => (
                                <tr key={plan.id} className="border-b border-border-warm hover:bg-cream/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="font-semibold text-ink">{plan.name}</span>
                                        <span className="block font-mono text-[10px] text-ink-muted">{plan.slug}</span>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-ink">
                                        {plan.price_monthly > 0 ? `RM ${Number(plan.price_monthly).toFixed(2)}` : 'Free'}
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-ink">
                                        {plan.price_yearly > 0 ? `RM ${Number(plan.price_yearly).toFixed(2)}` : 'Free'}
                                    </td>
                                    <td className="px-6 py-4 text-xs text-ink">
                                        {plan.users_included}
                                        {plan.extra_user_price > 0 && (
                                            <span className="block text-ink-muted">+RM {Number(plan.extra_user_price).toFixed(2)}/extra</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-alt text-ink">
                                            {plan.permissions?.length ?? 0} permissions
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-ink">{plan.subscriptions_count ?? 0}</td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                            plan.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'
                                        }`}>
                                            {plan.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-1.5">
                                            <Link
                                                href={route('admin.plans.edit', plan.id)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors"
                                            >
                                                Edit
                                            </Link>
                                            {plan.is_active ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setDeactivatingPlan(plan)}
                                                    className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-ink bg-surface-alt hover:bg-surface-alt transition-colors"
                                                >
                                                    Deactivate
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => handleActivate(plan)}
                                                    disabled={activatingId === plan.id}
                                                    className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-forest bg-forest/10 hover:bg-forest/10 border border-forest/30 disabled:opacity-60 transition-colors"
                                                >
                                                    {activatingId === plan.id ? '…' : 'Activate'}
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {deactivatingPlan && (
                <DeactivateModal
                    plan={deactivatingPlan}
                    onClose={() => setDeactivatingPlan(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
