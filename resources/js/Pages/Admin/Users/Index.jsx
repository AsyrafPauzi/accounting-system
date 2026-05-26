import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

const ROLES = ['super-admin', 'admin', 'accountant', 'sales', 'viewer'];

const roleColors = {
    'super-admin': 'bg-violet-100 text-violet-700',
    admin:         'bg-indigo-100 text-indigo-700',
    accountant:    'bg-blue-100 text-blue-700',
    sales:         'bg-emerald-100 text-emerald-700',
    viewer:        'bg-slate-100 text-slate-600',
};

function CloseIcon() {
    return (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function RoleBadge({ role }) {
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${roleColors[role] ?? roleColors.viewer}`}>
            {role?.replace('-', ' ') ?? 'user'}
        </span>
    );
}

/** Generic two-button confirmation modal */
function ConfirmModal({ title, description, confirmLabel, confirmClass, onConfirm, onClose, processing = false }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 className="text-base font-bold text-slate-900">{title}</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <CloseIcon />
                    </button>
                </div>
                <div className="px-6 py-4">
                    <p className="text-sm text-slate-600">{description}</p>
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
                        onClick={onConfirm}
                        disabled={processing}
                        className={`px-4 py-2 rounded-xl text-sm font-semibold text-white transition-colors disabled:opacity-60 ${confirmClass}`}
                    >
                        {processing ? 'Processing…' : confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

function CreateUserModal({ onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', email: '', password: '', password_confirmation: '', role: 'admin',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'), {
            onSuccess: () => { reset(); onClose(); },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 className="text-base font-bold text-slate-900">Create Platform User</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <CloseIcon />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                        {errors.name && <p className="text-xs text-rose-600 mt-1">{errors.name}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                        {errors.email && <p className="text-xs text-rose-600 mt-1">{errors.email}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Password</label>
                        <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                        {errors.password && <p className="text-xs text-rose-600 mt-1">{errors.password}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Confirm Password</label>
                        <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Role</label>
                        <select value={data.role} onChange={(e) => setData('role', e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="admin">Admin (tenant)</option>
                            <option value="super-admin">Super Admin (platform)</option>
                        </select>
                        {errors.role && <p className="text-xs text-rose-600 mt-1">{errors.role}</p>}
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200">
                            Cancel
                        </button>
                        <button type="submit" disabled={processing} className="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60">
                            {processing ? 'Creating…' : 'Create User'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ChangeRoleModal({ user, onClose }) {
    const [selectedRole, setSelectedRole] = useState(user.role);
    const [processing, setProcessing] = useState(false);

    const isDemotion = user.role === 'super-admin' && selectedRole !== 'super-admin';

    const handleConfirm = () => {
        if (selectedRole === user.role) { onClose(); return; }
        setProcessing(true);
        router.patch(route('admin.users.role', user.id), { role: selectedRole }, {
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 className="text-base font-bold text-slate-900">Change Role</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <CloseIcon />
                    </button>
                </div>
                <div className="px-6 py-4 space-y-4">
                    <div>
                        <p className="text-sm text-slate-600 mb-1">
                            User: <span className="font-semibold text-slate-800">{user.name}</span>
                        </p>
                        <p className="text-xs text-slate-400">{user.email}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">New Role</label>
                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        >
                            {ROLES.map((r) => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                    </div>
                    {isDemotion && (
                        <div className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                            You are removing super-admin privileges from this user. They will lose all platform management access.
                        </div>
                    )}
                </div>
                <div className="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                    <button type="button" onClick={onClose} className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200">
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={processing || selectedRole === user.role}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Saving…' : 'Save Role'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function AdminUsersIndex({ auth, users }) {
    const [showCreate, setShowCreate] = useState(false);
    const [roleModalUser, setRoleModalUser] = useState(null);
    const [suspendModalUser, setSuspendModalUser] = useState(null);
    const [resetModalUser, setResetModalUser] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const handleToggleActive = () => {
        if (!suspendModalUser) return;
        setActionProcessing(true);
        router.patch(route('admin.users.toggle-active', suspendModalUser.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSuspendModalUser(null),
            onFinish: () => setActionProcessing(false),
        });
    };

    const handlePasswordReset = () => {
        if (!resetModalUser) return;
        setActionProcessing(true);
        router.post(route('admin.users.password-reset', resetModalUser.id), {}, {
            preserveScroll: true,
            onSuccess: () => setResetModalUser(null),
            onFinish: () => setActionProcessing(false),
        });
    };

    const { data: pageData } = users;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Platform Users</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Manage roles, passwords, and access for all central users.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowCreate(true)}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
                    >
                        + New User
                    </button>
                </div>
            }
        >
            <Head title="Platform Users" />

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                        Users <span className="ml-2 text-slate-400 font-normal normal-case">({users.total})</span>
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50/80">
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4">Role</th>
                                <th className="px-6 py-4">Tenant</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Joined</th>
                                <th className="px-6 py-4 w-56">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pageData.map((user) => (
                                <tr key={user.id} className="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="font-semibold text-slate-800 text-sm block">{user.name}</span>
                                        <span className="text-slate-500 text-xs">{user.email}</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <RoleBadge role={user.role} />
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="font-mono text-xs text-slate-500">
                                            {user.tenant_id ?? <span className="italic text-slate-300">platform</span>}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                            user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                                        }`}>
                                            {user.is_active ? 'Active' : 'Suspended'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-slate-500 font-mono">{user.created_at}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => setRoleModalUser(user)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                                            >
                                                Role
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setResetModalUser(user)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors"
                                            >
                                                Reset PW
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setSuspendModalUser(user)}
                                                className={`px-2.5 py-1.5 rounded-xl text-xs font-semibold transition-colors ${
                                                    user.is_active
                                                        ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200'
                                                        : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200'
                                                }`}
                                            >
                                                {user.is_active ? 'Suspend' : 'Restore'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {users.last_page > 1 && (
                    <div className="px-6 py-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                        <span>Page {users.current_page} of {users.last_page}</span>
                        <div className="flex gap-2">
                            {users.prev_page_url && (
                                <a href={users.prev_page_url} className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">Previous</a>
                            )}
                            {users.next_page_url && (
                                <a href={users.next_page_url} className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">Next</a>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Modals */}
            {showCreate && (
                <CreateUserModal onClose={() => setShowCreate(false)} />
            )}

            {roleModalUser && (
                <ChangeRoleModal
                    user={roleModalUser}
                    onClose={() => setRoleModalUser(null)}
                />
            )}

            {suspendModalUser && (
                <ConfirmModal
                    title={suspendModalUser.is_active ? 'Suspend user?' : 'Restore user?'}
                    description={
                        suspendModalUser.is_active
                            ? `This will prevent "${suspendModalUser.name}" from logging in. You can restore them at any time.`
                            : `This will re-enable access for "${suspendModalUser.name}".`
                    }
                    confirmLabel={suspendModalUser.is_active ? 'Suspend' : 'Restore'}
                    confirmClass={suspendModalUser.is_active ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'}
                    processing={actionProcessing}
                    onConfirm={handleToggleActive}
                    onClose={() => setSuspendModalUser(null)}
                />
            )}

            {resetModalUser && (
                <ConfirmModal
                    title="Send password reset?"
                    description={`A password reset link will be sent to ${resetModalUser.email}. The link expires after 60 minutes.`}
                    confirmLabel="Send Reset Link"
                    confirmClass="bg-indigo-600 hover:bg-indigo-700"
                    processing={actionProcessing}
                    onConfirm={handlePasswordReset}
                    onClose={() => setResetModalUser(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
