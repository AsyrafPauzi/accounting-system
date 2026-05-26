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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 className="text-base font-bold text-slate-900">Deactivate plan?</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-5 space-y-3">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                            <svg className="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-slate-800">{plan.name}</p>
                            <p className="text-xs text-slate-400 font-mono">{plan.slug}</p>
                        </div>
                    </div>

                    <p className="text-sm text-slate-600">
                        This plan will be hidden from the subscription page and cannot be assigned to new tenants.
                    </p>

                    {plan.subscriptions_count > 0 && (
                        <div className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                            <span className="font-bold">{plan.subscriptions_count} tenant{plan.subscriptions_count !== 1 ? 's' : ''}</span> are currently on this plan. Their subscriptions will remain active until they naturally expire — no immediate disruption.
                        </div>
                    )}
                </div>

                <div className="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={processing}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-60 transition-colors"
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
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Plan Catalog</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Manage subscription tiers, pricing, and feature permissions.
                        </p>
                    </div>
                    <Link
                        href={route('admin.plans.create')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
                    >
                        + New Plan
                    </Link>
                </div>
            }
        >
            <Head title="Plan Catalog" />

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Plans</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50/80">
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
                                <tr key={plan.id} className="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="font-semibold text-slate-800">{plan.name}</span>
                                        <span className="block font-mono text-[10px] text-slate-400">{plan.slug}</span>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-slate-700">
                                        {plan.price_monthly > 0 ? `RM ${Number(plan.price_monthly).toFixed(2)}` : 'Free'}
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-slate-700">
                                        {plan.price_yearly > 0 ? `RM ${Number(plan.price_yearly).toFixed(2)}` : 'Free'}
                                    </td>
                                    <td className="px-6 py-4 text-xs text-slate-700">
                                        {plan.users_included}
                                        {plan.extra_user_price > 0 && (
                                            <span className="block text-slate-400">+RM {Number(plan.extra_user_price).toFixed(2)}/extra</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                            {plan.permissions?.length ?? 0} permissions
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-slate-700">{plan.subscriptions_count ?? 0}</td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                            plan.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                        }`}>
                                            {plan.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-1.5">
                                            <Link
                                                href={route('admin.plans.edit', plan.id)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                                            >
                                                Edit
                                            </Link>
                                            {plan.is_active ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setDeactivatingPlan(plan)}
                                                    className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                                                >
                                                    Deactivate
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => handleActivate(plan)}
                                                    disabled={activatingId === plan.id}
                                                    className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 disabled:opacity-60 transition-colors"
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
