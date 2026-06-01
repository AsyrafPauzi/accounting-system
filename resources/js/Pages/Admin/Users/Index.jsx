import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

const ROLES = ['super-admin', 'admin', 'accountant', 'sales', 'viewer'];

const roleColors = {
    'super-admin': 'bg-surface-alt text-terracotta',
    admin:         'bg-surface-alt text-terracotta',
    accountant:    'bg-surface-alt text-terracotta',
    sales:         'bg-forest/10 text-forest',
    viewer:        'bg-surface-alt text-ink',
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-surface shadow-2xl">
                <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                    <h3 className="text-base font-display font-medium text-ink">{title}</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-surface-alt text-ink-muted">
                        <CloseIcon />
                    </button>
                </div>
                <div className="px-6 py-4">
                    <p className="text-sm text-ink">{description}</p>
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl bg-surface shadow-2xl">
                <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                    <h3 className="text-base font-display font-medium text-ink">Create Platform User</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-surface-alt text-ink-muted">
                        <CloseIcon />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">Name</label>
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta" />
                        {errors.name && <p className="text-xs text-terracotta mt-1">{errors.name}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">Email</label>
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta" />
                        {errors.email && <p className="text-xs text-terracotta mt-1">{errors.email}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">Password</label>
                        <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta" />
                        {errors.password && <p className="text-xs text-terracotta mt-1">{errors.password}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">Confirm Password</label>
                        <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta" />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">Role</label>
                        <select value={data.role} onChange={(e) => setData('role', e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta">
                            <option value="admin">Admin (tenant)</option>
                            <option value="super-admin">Super Admin (platform)</option>
                        </select>
                        {errors.role && <p className="text-xs text-terracotta mt-1">{errors.role}</p>}
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface-alt hover:bg-surface-alt">
                            Cancel
                        </button>
                        <button type="submit" disabled={processing} className="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-60">
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-sm rounded-2xl bg-surface shadow-2xl">
                <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                    <h3 className="text-base font-display font-medium text-ink">Change Role</h3>
                    <button type="button" onClick={onClose} className="p-1.5 rounded-lg hover:bg-surface-alt text-ink-muted">
                        <CloseIcon />
                    </button>
                </div>
                <div className="px-6 py-4 space-y-4">
                    <div>
                        <p className="text-sm text-ink mb-1">
                            User: <span className="font-semibold text-ink">{user.name}</span>
                        </p>
                        <p className="text-xs text-ink-muted">{user.email}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-ink mb-1">New Role</label>
                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                        >
                            {ROLES.map((r) => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                    </div>
                    {isDemotion && (
                        <div className="rounded-xl bg-mustard/15 border border-mustard/40 px-4 py-3 text-xs text-ink">
                            You are removing super-admin privileges from this user. They will lose all platform management access.
                        </div>
                    )}
                </div>
                <div className="px-6 py-4 border-t border-border-warm flex justify-end gap-3">
                    <button type="button" onClick={onClose} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface-alt hover:bg-surface-alt">
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={processing || selectedRole === user.role}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 transition-colors"
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
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Admin</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Platform users</h1>
                        <p className="text-ink-muted text-sm">Roles, passwords and access for everyone running the platform.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowCreate(true)}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta transition-colors"
                    >
                        + New User
                    </button>
                </div>
            }
        >
            <Head title="Platform Users" />

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">
                        Users <span className="ml-2 text-ink-muted font-normal normal-case">({users.total})</span>
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest bg-cream/80">
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
                                <tr key={user.id} className="border-b border-border-warm hover:bg-cream/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="font-semibold text-ink text-sm block">{user.name}</span>
                                        <span className="text-ink-muted text-xs">{user.email}</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <RoleBadge role={user.role} />
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="font-mono text-xs text-ink-muted">
                                            {user.tenant_id ?? <span className="italic text-ink-muted">platform</span>}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                            user.is_active ? 'bg-forest/10 text-forest' : 'bg-terracotta/10 text-terracotta'
                                        }`}>
                                            {user.is_active ? 'Active' : 'Suspended'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-ink-muted font-mono">{user.created_at}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => setRoleModalUser(user)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors"
                                            >
                                                Role
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setResetModalUser(user)}
                                                className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-ink bg-surface-alt hover:bg-surface-alt transition-colors"
                                            >
                                                Reset PW
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setSuspendModalUser(user)}
                                                className={`px-2.5 py-1.5 rounded-xl text-xs font-semibold transition-colors ${
                                                    user.is_active
                                                        ? 'text-terracotta bg-terracotta/10 hover:bg-terracotta/10 border border-terracotta/30'
                                                        : 'text-forest bg-forest/10 hover:bg-forest/10 border border-forest/30'
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
                    <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                        <span>Page {users.current_page} of {users.last_page}</span>
                        <div className="flex gap-2">
                            {users.prev_page_url && (
                                <a href={users.prev_page_url} className="px-3 py-1.5 rounded-lg bg-surface-alt hover:bg-surface-alt font-semibold">Previous</a>
                            )}
                            {users.next_page_url && (
                                <a href={users.next_page_url} className="px-3 py-1.5 rounded-lg bg-surface-alt hover:bg-surface-alt font-semibold">Next</a>
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
                    confirmClass={suspendModalUser.is_active ? 'bg-terracotta hover:bg-terracotta' : 'bg-forest hover:bg-forest'}
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
                    confirmClass="bg-terracotta hover:bg-terracotta"
                    processing={actionProcessing}
                    onConfirm={handlePasswordReset}
                    onClose={() => setResetModalUser(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
