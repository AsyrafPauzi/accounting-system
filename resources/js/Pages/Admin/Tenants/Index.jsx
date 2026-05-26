import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

const statusColors = {
    active:   'bg-emerald-100 text-emerald-700',
    trialing: 'bg-blue-100 text-blue-700',
    expired:  'bg-rose-100 text-rose-700',
    canceled: 'bg-slate-100 text-slate-600',
    pending:  'bg-amber-100 text-amber-700',
};

const gatewayLabel = { admin: 'Admin', system: 'System', toyyibpay: 'ToyyibPay' };

function StatusBadge({ status, isActive }) {
    const label = isActive ? status : (status === 'active' ? 'expired' : status);
    const color = statusColors[isActive ? status : 'expired'] ?? statusColors.canceled;
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${color}`}>
            {label ?? '—'}
        </span>
    );
}

function ManagePlanModal({ tenant, plans, onClose }) {
    const assignForm = useForm({
        plan_id:  tenant.subscription?.plan_id ?? (plans[0]?.id ?? ''),
        duration: '1_month',
        ends_at:  '',
    });

    const handleAssign = (e) => {
        e.preventDefault();
        assignForm.put(route('admin.tenants.subscription.assign', tenant.id), {
            onSuccess: onClose,
        });
    };

    const handleExtend = (days) => {
        router.post(route('admin.tenants.subscription.extend', tenant.id), { days }, {
            onSuccess: onClose,
        });
    };

    const handleCancel = () => {
        if (!confirm(`Cancel subscription for ${tenant.id}?`)) return;
        router.post(route('admin.tenants.subscription.cancel', tenant.id), {}, {
            onSuccess: onClose,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 className="text-base font-bold text-slate-900">Manage Plan</h3>
                        <p className="text-xs text-slate-500 mt-0.5">Tenant: <span className="font-mono">{tenant.id}</span></p>
                    </div>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div className="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                    {/* Current status */}
                    {tenant.subscription && (
                        <div className="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm">
                            <p className="font-semibold text-slate-700 mb-2">Current Subscription</p>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600">
                                <span className="text-slate-400">Plan</span>
                                <span className="font-semibold">{tenant.subscription.plan_name}</span>
                                <span className="text-slate-400">Status</span>
                                <StatusBadge status={tenant.subscription.status} isActive={tenant.subscription.is_active} />
                                <span className="text-slate-400">Expires</span>
                                <span className="font-mono">{tenant.subscription.current_period_ends_at ?? 'Never (lifetime)'}</span>
                                <span className="text-slate-400">Gateway</span>
                                <span>{gatewayLabel[tenant.subscription.gateway] ?? tenant.subscription.gateway}</span>
                            </div>
                        </div>
                    )}

                    {/* Assign plan form */}
                    <form onSubmit={handleAssign} className="space-y-4">
                        <p className="text-sm font-semibold text-slate-800">Assign / Change Plan</p>

                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">Plan</label>
                            <select
                                className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                value={assignForm.data.plan_id}
                                onChange={(e) => assignForm.setData('plan_id', e.target.value)}
                            >
                                {plans.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name} (RM {p.price_monthly}/mo)</option>
                                ))}
                            </select>
                            {assignForm.errors.plan_id && <p className="text-xs text-rose-600 mt-1">{assignForm.errors.plan_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">Duration</label>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    { value: '1_month',  label: '1 Month' },
                                    { value: '1_year',   label: '1 Year' },
                                    { value: 'lifetime', label: 'Lifetime' },
                                    { value: 'custom',   label: 'Custom Date' },
                                ].map((opt) => (
                                    <label key={opt.value} className={`flex items-center gap-2 px-3 py-2 rounded-xl border cursor-pointer text-sm font-medium transition-colors ${
                                        assignForm.data.duration === opt.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                                    }`}>
                                        <input
                                            type="radio"
                                            className="accent-indigo-600"
                                            name="duration"
                                            value={opt.value}
                                            checked={assignForm.data.duration === opt.value}
                                            onChange={(e) => assignForm.setData('duration', e.target.value)}
                                        />
                                        {opt.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        {assignForm.data.duration === 'custom' && (
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">End Date</label>
                                <input
                                    type="date"
                                    className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                    value={assignForm.data.ends_at}
                                    onChange={(e) => assignForm.setData('ends_at', e.target.value)}
                                />
                                {assignForm.errors.ends_at && <p className="text-xs text-rose-600 mt-1">{assignForm.errors.ends_at}</p>}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={assignForm.processing}
                            className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors disabled:opacity-60"
                        >
                            {assignForm.processing ? 'Saving…' : 'Assign Plan'}
                        </button>
                    </form>

                    {/* Quick actions */}
                    <div className="border-t border-slate-200 pt-4 space-y-3">
                        <p className="text-sm font-semibold text-slate-800">Quick Actions</p>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => handleExtend(30)}
                                className="px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                            >
                                + 30 days
                            </button>
                            <button
                                type="button"
                                onClick={() => handleExtend(90)}
                                className="px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                            >
                                + 90 days
                            </button>
                            <button
                                type="button"
                                onClick={() => handleExtend(365)}
                                className="px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                            >
                                + 365 days
                            </button>
                        </div>
                        <button
                            type="button"
                            onClick={handleCancel}
                            className="w-full py-2 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors"
                        >
                            Cancel Subscription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function TenantAdminIndex({ auth, tenants = [], plans = [], flash = {} }) {
    const { post } = useForm({});
    const [deletingId, setDeletingId] = useState(null);
    const [managingTenant, setManagingTenant] = useState(null);

    const handleImpersonate = (userId) => {
        if (!userId) return;
        post(route('admin.tenants.impersonate', userId));
    };

    const handleStopImpersonating = () => {
        post(route('admin.tenants.stop-impersonating'));
    };

    const handleBackup = (tenantId) => {
        window.location.href = route('admin.tenants.backup', tenantId);
    };

    const handleDeleteClick = (tenantId) => setDeletingId(tenantId);
    const handleDeleteConfirm = () => {
        if (!deletingId) return;
        router.delete(route('admin.tenants.destroy', deletingId), {
            onSuccess: () => setDeletingId(null),
            onError: () => setDeletingId(null),
        });
    };
    const handleDeleteCancel = () => setDeletingId(null);

    const isImpersonating = Boolean(auth && auth.impersonator_id);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Tenants</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Manage tenants, subscriptions, databases, and impersonation.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        {isImpersonating && (
                            <button
                                type="button"
                                onClick={handleStopImpersonating}
                                className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 transition-colors"
                            >
                                Stop impersonating
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Tenants" />

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                        Tenants <span className="ml-2 text-slate-400 font-normal normal-case">({tenants.length})</span>
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50/80">
                                <th className="px-6 py-4">Tenant ID</th>
                                <th className="px-6 py-4">Owner</th>
                                <th className="px-6 py-4">Plan</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Expires</th>
                                <th className="px-6 py-4">Gateway</th>
                                <th className="px-6 py-4 w-56">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.length === 0 && (
                                <tr><td colSpan={7} className="px-6 py-8 text-center text-slate-400 text-sm">No tenants found.</td></tr>
                            )}
                            {tenants.map((tenant) => (
                                <tr key={tenant.id} className="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="font-mono text-xs text-slate-700">{tenant.id}</span>
                                        <span className="block font-mono text-[10px] text-slate-400">{tenant.database || '—'}</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        {tenant.owner ? (
                                            <div>
                                                <span className="font-medium text-slate-800 text-xs block">{tenant.owner.name}</span>
                                                <span className="text-slate-500 text-xs">{tenant.owner.email}</span>
                                            </div>
                                        ) : (
                                            <span className="text-slate-400 text-xs">No user</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs font-semibold text-slate-700">{tenant.subscription?.plan_name ?? '—'}</span>
                                        {tenant.subscription?.interval && (
                                            <span className="block text-[10px] text-slate-400 capitalize">{tenant.subscription.interval}</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        {tenant.subscription
                                            ? <StatusBadge status={tenant.subscription.status} isActive={tenant.subscription.is_active} />
                                            : <span className="text-slate-400 text-xs">—</span>
                                        }
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="font-mono text-xs text-slate-600">
                                            {tenant.subscription?.current_period_ends_at
                                                ? tenant.subscription.current_period_ends_at
                                                : tenant.subscription?.interval === 'lifetime'
                                                    ? 'Never'
                                                    : '—'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs text-slate-500 capitalize">
                                            {gatewayLabel[tenant.subscription?.gateway] ?? tenant.subscription?.gateway ?? '—'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => setManagingTenant(tenant)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                                            >
                                                Manage Plan
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleBackup(tenant.id)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors"
                                            >
                                                Backup
                                            </button>
                                            {tenant.owner && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleImpersonate(tenant.owner.id)}
                                                    className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                                                >
                                                    Impersonate
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => handleDeleteClick(tenant.id)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 transition-colors"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Delete confirmation */}
            {deletingId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-slate-900">Delete tenant and database?</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            This will remove the tenant and drop its database. This cannot be undone. Back up the tenant first if you need to keep data.
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={handleDeleteCancel} className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200">
                                Cancel
                            </button>
                            <button type="button" onClick={handleDeleteConfirm} className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700">
                                Delete tenant
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Manage Plan modal */}
            {managingTenant && (
                <ManagePlanModal
                    tenant={managingTenant}
                    plans={plans}
                    onClose={() => setManagingTenant(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
