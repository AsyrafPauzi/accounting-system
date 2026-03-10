import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TenantAdminIndex({ auth, tenants = [] }) {
    const { post } = useForm({});

    const handleImpersonate = (userId) => {
        if (!userId) return;
        post(route('admin.tenants.impersonate', userId));
    };

    const handleStopImpersonating = () => {
        post(route('admin.tenants.stop-impersonating'));
    };

    const isImpersonating = Boolean(auth && auth.impersonator_id);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Tenant Admin</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            List of tenants, their databases, and primary users to impersonate.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={route('dashboard')}
                            className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            Back to Dashboard
                        </Link>
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
            <Head title="Tenant Admin" />

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Tenants</h3>
                </div>
                <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50/80">
                            <th className="px-6 py-4">Tenant ID</th>
                            <th className="px-6 py-4">Database</th>
                            <th className="px-6 py-4">Owner</th>
                            <th className="px-6 py-4 w-40">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tenants.map((tenant) => (
                            <tr key={tenant.id} className="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                <td className="px-6 py-4 font-mono text-xs text-slate-700">{tenant.id}</td>
                                <td className="px-6 py-4 font-mono text-xs text-slate-500">
                                    {tenant.database || '—'}
                                </td>
                                <td className="px-6 py-4">
                                    {tenant.owner ? (
                                        <div className="flex flex-col">
                                            <span className="font-medium text-slate-800 text-xs">
                                                {tenant.owner.name}
                                            </span>
                                            <span className="text-slate-500 text-xs">
                                                {tenant.owner.email}
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-slate-400 text-xs">No user</span>
                                    )}
                                </td>
                                <td className="px-6 py-4">
                                    {tenant.owner ? (
                                        <button
                                            type="button"
                                            onClick={() => handleImpersonate(tenant.owner.id)}
                                            className="px-3 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                                        >
                                            Impersonate
                                        </button>
                                    ) : (
                                        <span className="text-slate-400 text-xs">—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

