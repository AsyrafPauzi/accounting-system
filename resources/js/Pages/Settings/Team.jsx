import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-border-warm text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta';
const labelClass =
    'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider';

const ROLE_LABELS = {
    admin: 'Administrator',
    accountant: 'Accountant',
    sales: 'Sales',
    viewer: 'Viewer',
};

export default function Team({ auth, users = [], assignableRoles = [] }) {
    const { teamPermissions = {} } = auth || {};
    const page = usePage();
    const errors = page.props.errors || {};

    const { data, setData, post, processing, errors: formErrors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: assignableRoles.includes('accountant') ? 'accountant' : assignableRoles[0] || 'viewer',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('settings.team.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const updateRole = (userId, role) => {
        router.patch(
            route('settings.team.update', userId),
            { role },
            { preserveScroll: true }
        );
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Settings</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Team & roles
                        </h1>
                        <p className="text-ink-muted text-sm">
                            Each person signs in with their own account. Roles set what they can touch.
                        </p>
                    </div>
                    <Link
                        href={route('settings.company')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta"
                    >
                        ← Company settings
                    </Link>
                </div>
            }
        >
            <Head title="Team & Roles" />

            <div className="max-w-5xl space-y-8">
                {teamPermissions.create && (
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider mb-4">
                            Add team member
                        </h3>
                        <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Name</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                {formErrors.name && (
                                    <p className="text-terracotta text-xs mt-1">{formErrors.name}</p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>Email</label>
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                {formErrors.email && (
                                    <p className="text-terracotta text-xs mt-1">{formErrors.email}</p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>Password</label>
                                <input
                                    type="password"
                                    className={inputClass}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                />
                                {formErrors.password && (
                                    <p className="text-terracotta text-xs mt-1">{formErrors.password}</p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>Confirm password</label>
                                <input
                                    type="password"
                                    className={inputClass}
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label className={labelClass}>Role</label>
                                <select
                                    className={inputClass}
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                >
                                    {assignableRoles.map((r) => (
                                        <option key={r} value={r}>
                                            {ROLE_LABELS[r] || r}
                                        </option>
                                    ))}
                                </select>
                                {formErrors.role && (
                                    <p className="text-terracotta text-xs mt-1">{formErrors.role}</p>
                                )}
                            </div>
                            <div className="md:col-span-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50"
                                >
                                    {processing ? 'Adding…' : 'Add user'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm">
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                            People in this organization
                        </h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="bg-cream text-left text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Email</th>
                                    <th className="px-6 py-3">Role</th>
                                    <th className="px-6 py-3 w-28" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {users.map((u) => {
                                    const current =
                                        (u.roles || []).find((r) => assignableRoles.includes(r)) ||
                                        assignableRoles[0];
                                    return (
                                        <tr key={u.id} className="text-ink">
                                            <td className="px-6 py-3 font-medium">
                                                {u.name}
                                                {u.is_self && (
                                                    <span className="ml-2 text-[10px] font-semibold text-ink-muted uppercase">
                                                        (you)
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3 text-ink">{u.email}</td>
                                            <td className="px-6 py-3">
                                                {teamPermissions.edit && assignableRoles.length ? (
                                                    <select
                                                        className="rounded-lg border border-border-warm py-1.5 pl-2 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta disabled:opacity-50 disabled:bg-cream min-w-[140px]"
                                                        value={current}
                                                        onChange={(e) => updateRole(u.id, e.target.value)}
                                                        disabled={u.is_self}
                                                    >
                                                        {assignableRoles.map((r) => (
                                                            <option key={r} value={r}>
                                                                {ROLE_LABELS[r] || r}
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <span className="font-medium text-ink">
                                                        {ROLE_LABELS[current] || current || '—'}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {teamPermissions.delete && !u.is_self && (
                                                    <Link
                                                        href={route('settings.team.destroy', u.id)}
                                                        method="delete"
                                                        as="button"
                                                        className="text-xs font-semibold text-terracotta hover:text-terracotta"
                                                        onClick={(e) => {
                                                            if (
                                                                !confirm(
                                                                    `Remove ${u.name} from this organization? They will no longer be able to sign in.`
                                                                )
                                                            ) {
                                                                e.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        Remove
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {users.length === 0 && (
                        <p className="px-6 py-8 text-center text-ink-muted text-sm">No users found.</p>
                    )}
                </div>

                {errors.role && (
                    <p className="text-terracotta text-sm">{errors.role}</p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
