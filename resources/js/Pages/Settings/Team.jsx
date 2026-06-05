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

export default function Team({ auth, users = [], assignableRoles = [], seatStatus = null }) {
    const { teamPermissions = {} } = auth || {};
    const page = usePage();
    const errors = page.props.errors || {};

    const { data, setData, post, processing, errors: formErrors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: assignableRoles.includes('accountant') ? 'accountant' : assignableRoles[0] || 'viewer',
        authorize_extra_seat_charge: false,
    });

    const formattedPrice =
        seatStatus && seatStatus.extra_user_price > 0
            ? `${seatStatus.currency || 'RM'} ${Number(seatStatus.extra_user_price).toFixed(2)}`
            : null;

    const willCharge = !!seatStatus?.next_user_charges;
    const noSubscription = seatStatus && !seatStatus.has_subscription;
    const planSellsExtras = (seatStatus?.extra_user_price || 0) > 0;
    const atHardLimit =
        seatStatus &&
        seatStatus.has_subscription &&
        seatStatus.used >= seatStatus.total_seats &&
        !planSellsExtras;

    const submit = (e) => {
        e.preventDefault();
        // The extra-seat consent box must be ticked when the next add will
        // trigger a charge. The server re-checks, but failing fast in the
        // browser saves a round-trip.
        if (willCharge && !data.authorize_extra_seat_charge) {
            setData('authorize_extra_seat_charge', false);
            return;
        }
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
                {seatStatus && seatStatus.has_subscription && (
                    <SeatStatusBanner status={seatStatus} />
                )}

                {teamPermissions.create && (
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-2 mb-4">
                            <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                                Add team member
                            </h3>
                            {willCharge && formattedPrice && (
                                <span className="text-xs font-semibold text-mustard bg-mustard/15 px-2.5 py-1 rounded-full border border-mustard/30">
                                    Adds an extra seat at {formattedPrice}/month
                                </span>
                            )}
                        </div>

                        {atHardLimit && (
                            <div className="mb-4 p-4 rounded-xl border border-terracotta/40 bg-terracotta/10 text-sm text-terracotta">
                                <p className="font-semibold">You've used all {seatStatus.total_seats} seats on the {seatStatus.plan_name} plan.</p>
                                <p className="mt-1 text-ink-muted">
                                    This plan doesn't allow extra seats. <Link href={route('subscription.index')} className="font-semibold text-terracotta underline hover:no-underline">Upgrade your plan</Link> to invite more people.
                                </p>
                            </div>
                        )}

                        {noSubscription && (
                            <div className="mb-4 p-4 rounded-xl border border-terracotta/40 bg-terracotta/10 text-sm text-terracotta">
                                <p className="font-semibold">No active subscription</p>
                                <p className="mt-1 text-ink-muted">
                                    <Link href={route('subscription.index')} className="font-semibold text-terracotta underline hover:no-underline">Pick a plan</Link> before adding team members.
                                </p>
                            </div>
                        )}

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
                            {willCharge && formattedPrice && (
                                <div className="md:col-span-2">
                                    <label className="flex items-start gap-3 p-3 rounded-xl border border-mustard/40 bg-mustard/10 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.authorize_extra_seat_charge}
                                            onChange={(e) => setData('authorize_extra_seat_charge', e.target.checked)}
                                            className="mt-0.5 rounded border-border-warm text-terracotta focus:ring-terracotta"
                                        />
                                        <span className="text-sm text-ink leading-snug">
                                            I authorise charging <strong>{formattedPrice}/month</strong> for this extra seat. Payment is collected via Toyyibpay before the user is created.
                                        </span>
                                    </label>
                                    {formErrors.authorize_extra_seat_charge && (
                                        <p className="text-terracotta text-xs mt-1">{formErrors.authorize_extra_seat_charge}</p>
                                    )}
                                </div>
                            )}

                            <div className="md:col-span-2">
                                <button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        atHardLimit ||
                                        noSubscription ||
                                        (willCharge && !data.authorize_extra_seat_charge)
                                    }
                                    className="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing
                                        ? (willCharge ? 'Redirecting to payment…' : 'Adding…')
                                        : (willCharge ? `Pay & add user (${formattedPrice}/mo)` : 'Add user')}
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

/**
 * Compact summary at the top of the Team page so the admin always knows
 * "I'm using N of M seats, and the next one will cost RM X".
 */
function SeatStatusBanner({ status }) {
    const used = Number(status.used || 0);
    const total = Number(status.total_seats || 0);
    const included = Number(status.users_included || 0);
    const extras = Number(status.extra_seats || 0);
    const price = Number(status.extra_user_price || 0);
    const currency = status.currency || 'RM';
    const planSellsExtras = price > 0;

    const overUsed = used > total; // shouldn't happen, but render gracefully if it does
    const atLimit = used >= total;

    const tone = overUsed
        ? 'border-terracotta/40 bg-terracotta/10'
        : atLimit
            ? 'border-mustard/40 bg-mustard/10'
            : 'border-forest/30 bg-forest/5';

    const accent = overUsed ? 'text-terracotta' : atLimit ? 'text-mustard' : 'text-forest';

    return (
        <div className={`p-4 sm:p-5 rounded-2xl border ${tone}`}>
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <div>
                    <p className="text-eyebrow font-semibold uppercase text-ink-muted">Plan: {status.plan_name}</p>
                    <p className="mt-1 text-ink">
                        Using <strong className={accent}>{used}</strong> of <strong>{total}</strong> seats
                        {extras > 0 && (
                            <span className="text-ink-muted text-sm">
                                {' '}({included} included + {extras} paid extra{extras === 1 ? '' : 's'})
                            </span>
                        )}
                    </p>
                </div>
                {planSellsExtras ? (
                    <p className="text-sm text-ink-muted">
                        Extra seats: <strong className="text-ink">{currency} {price.toFixed(2)}</strong>
                        <span className="text-ink-muted"> / month each</span>
                    </p>
                ) : (
                    <Link href={route('subscription.index')} className="text-sm font-semibold text-terracotta underline-offset-2 hover:underline">
                        Upgrade plan →
                    </Link>
                )}
            </div>
        </div>
    );
}
