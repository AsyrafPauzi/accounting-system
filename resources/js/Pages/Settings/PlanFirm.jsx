import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const formatDate = (iso) => {
    if (!iso) return null;
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('en-MY', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
};

const formatRM = (raw) => {
    const n = Number(raw);
    if (!Number.isFinite(n)) return '0.00';
    return n.toFixed(2);
};

/**
 * Plan & usage page for accountancy firm-owners. Mirrors the SME
 * `Settings/Plan.jsx` layout — Current Plan card with pricing grid,
 * Usage Limits with progress bars, and a Plan Features grid — but
 * speaks the firm's language: client cap (the headline metric for
 * firms), firm-staff seats, renewal cadence.
 *
 * The "Change plan" CTA points at `/practice/plan` where the actual
 * plan picker lives — separating "see what I have" from "switch to
 * something else" keeps the cognitive load on this page lower.
 */
export default function PlanFirm({ auth, firm, subscription, usage }) {
    const plan = subscription;

    const clientCap = usage.client_cap;
    const clientsUsed = usage.client_count;
    const clientsRemaining = usage.remaining;
    const clientsUnlimited = clientCap === null;

    const includedSeats = Number(plan?.users_included || 1);
    const extraSeats = Number(plan?.extra_seats || 0);
    const totalSeats = includedSeats + extraSeats;
    const staffCount = Number(usage.staff_count || 0);
    const overSeats = staffCount > totalSeats;

    const clientRatio = clientsUnlimited ? 0 : Math.min(100, (clientsUsed / Math.max(clientCap, 1)) * 100);
    const seatRatio = Math.min(100, (staffCount / Math.max(totalSeats, 1)) * 100);

    const renewalLabel = (() => {
        if (!plan) return '';
        if (plan.is_free) return 'Free tier · Expires: Never';
        const ends = formatDate(plan.current_period_ends_at);
        const verb = plan.gateway === 'system' ? 'Expires' : 'Renews';
        if (ends) {
            return `${plan.interval === 'yearly' ? 'Yearly' : 'Monthly'} subscription · ${verb} on ${ends}`;
        }
        return plan.interval === 'yearly' ? 'Yearly subscription' : 'Monthly subscription';
    })();

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Practice</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Plan & usage
                        </h1>
                        <p className="text-ink-muted text-sm">
                            {firm.name} — subscription details and what your firm is using right now.
                        </p>
                    </div>
                    <Link
                        href={route('practice.dashboard')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta"
                    >
                        ← Practice console
                    </Link>
                </div>
            }
        >
            <Head title="Plan & Usage" />

            <div className="max-w-5xl space-y-8">
                {/* Plan Overview Card */}
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-start justify-between flex-wrap gap-4">
                        <div>
                            <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider mb-2">
                                Current Plan
                            </h3>
                            <div className="flex items-center gap-3 flex-wrap">
                                <h4 className="text-2xl font-display font-medium text-ink">
                                    {plan?.plan_name ?? 'No active plan'}
                                </h4>
                                {plan?.status === 'active' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-forest/10 text-forest uppercase">
                                        Active
                                    </span>
                                )}
                                {plan?.status === 'pending' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-mustard/15 text-mustard uppercase">
                                        Pending payment
                                    </span>
                                )}
                            </div>
                            <p className="text-ink-muted text-sm mt-1 font-medium">
                                {renewalLabel}
                            </p>
                        </div>
                        <Link
                            href={route('practice.plan')}
                            className="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark shadow-sm transition-colors"
                        >
                            Change plan
                        </Link>
                    </div>

                    <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t border-border-warm">
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Monthly Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink">
                                RM{formatRM(plan?.price_monthly)}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Yearly Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink">
                                RM{formatRM(plan?.price_yearly)}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Extra Seat Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink">
                                {Number(plan?.extra_user_price) > 0
                                    ? `RM${formatRM(plan.extra_user_price)}/seat`
                                    : 'N/A'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Usage Limits Card */}
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider mb-6">
                        Usage Limits
                    </h3>

                    <div className="space-y-8">
                        {/* Clients — the headline metric for a firm */}
                        <div>
                            <div className="flex justify-between items-end mb-2">
                                <div>
                                    <h4 className="text-base font-display font-medium text-ink">Clients</h4>
                                    <p className="text-ink-muted text-sm">
                                        {clientsUnlimited
                                            ? `${clientsUsed} client${clientsUsed === 1 ? '' : 's'} managed · unlimited`
                                            : `${clientsUsed} of ${clientCap} client books in use`}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="text-2xl font-display font-medium text-ink">
                                        {clientsUsed}
                                    </span>
                                    <span className="text-ink-muted font-medium">
                                        {' / '}{clientsUnlimited ? '∞' : clientCap}
                                    </span>
                                </div>
                            </div>
                            <div className="h-2 w-full bg-surface-alt rounded-full overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        clientsUnlimited
                                            ? 'bg-forest'
                                            : clientsRemaining === 0
                                              ? 'bg-terracotta'
                                              : 'bg-forest'
                                    }`}
                                    style={{ width: clientsUnlimited ? '15%' : `${clientRatio}%` }}
                                />
                            </div>
                            {!clientsUnlimited && clientsRemaining === 0 && (
                                <p className="mt-2 text-xs font-semibold text-mustard bg-mustard/15 p-2 rounded-lg inline-block">
                                    You&apos;ve hit your plan&apos;s client cap. Upgrade to add more.
                                </p>
                            )}
                            {!clientsUnlimited && clientsRemaining > 0 && clientsRemaining <= 2 && (
                                <p className="mt-2 text-xs font-semibold text-ink-muted bg-cream p-2 rounded-lg inline-block">
                                    {clientsRemaining} more client slot{clientsRemaining === 1 ? '' : 's'} remaining on this plan.
                                </p>
                            )}
                        </div>

                        {/* Firm staff seats */}
                        <div>
                            <div className="flex justify-between items-end mb-2">
                                <div>
                                    <h4 className="text-base font-display font-medium text-ink">Firm seats</h4>
                                    <p className="text-ink-muted text-sm">
                                        {staffCount} of {totalSeats} firm-staff seat{totalSeats === 1 ? '' : 's'} used
                                        {extraSeats > 0 && (
                                            <span className="text-ink-muted">
                                                {' '}({includedSeats} included + {extraSeats} paid extra{extraSeats === 1 ? '' : 's'})
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="text-2xl font-display font-medium text-ink">{staffCount}</span>
                                    <span className="text-ink-muted font-medium"> / {totalSeats}</span>
                                </div>
                            </div>
                            <div className="h-2 w-full bg-surface-alt rounded-full overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        overSeats ? 'bg-terracotta' : 'bg-forest'
                                    }`}
                                    style={{ width: `${seatRatio}%` }}
                                />
                            </div>
                            {staffCount >= totalSeats && Number(plan?.extra_user_price || 0) > 0 && !overSeats && (
                                <p className="mt-2 text-xs font-semibold text-ink-muted bg-cream p-2 rounded-lg inline-block">
                                    All seats used — adding another firm member will buy an extra seat at
                                    RM{formatRM(plan.extra_user_price)}/month.
                                </p>
                            )}
                            {staffCount >= totalSeats && Number(plan?.extra_user_price || 0) === 0 && (
                                <p className="mt-2 text-xs font-semibold text-mustard bg-mustard/15 p-2 rounded-lg inline-block">
                                    You&apos;ve reached the firm-staff seat limit. Upgrade to add more.
                                </p>
                            )}
                        </div>

                        <div className="p-4 bg-cream rounded-xl border border-border-warm/50">
                            <h5 className="text-xs font-display font-medium text-ink uppercase tracking-wider mb-1">
                                Billing note
                            </h5>
                            <p className="text-ink text-xs leading-relaxed">
                                Client books and firm seats are independent caps. Linking an existing SME with
                                their consent does not consume a seat — only firm staff (people you employ) do.
                                If you need to add more clients, change plan above; for more firm seats, contact
                                support and we&apos;ll prorate the extra seat onto your next invoice.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Plan Features list — same grid layout as the SME page */}
                {plan?.features && plan.features.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                            <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                                Plan features
                            </h3>
                        </div>
                        <div className="p-6">
                            <ul className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {plan.features.map((feature, idx) => (
                                    <li key={idx} className="flex items-center gap-3 text-sm text-ink">
                                        <svg className="w-5 h-5 text-forest shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* No subscription = mid-onboarding edge case (Practice
                    Free auto-creates one, but a hand-crafted firm row
                    might land here). Keep the page useful by pointing
                    them at the picker. */}
                {!plan && (
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                        <p className="text-sm text-ink-muted">
                            No active practice plan on this firm. {' '}
                            <Link href={route('practice.plan')} className="text-terracotta font-semibold">
                                Pick a plan →
                            </Link>
                        </p>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
