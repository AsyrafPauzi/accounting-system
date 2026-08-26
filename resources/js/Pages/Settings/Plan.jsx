import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import BillingHistory from '@/Components/BillingHistory';

export default function PlanSettings({ auth, subscription, userCount, history = [], copilotCredits = null, renewal = null }) {
    const plan = subscription?.plan;
    const isCorporate = plan?.slug === 'corporate';
    const isTrialing = subscription?.status === 'trialing';
    const isPastDue = subscription?.status === 'past_due';
    const pendingPlan = subscription?.pending_plan;
    const trialEndsAt = subscription?.current_period_ends_at;
    // Inclusive day count: a trial that ends today reads "0 days left",
    // a trial ending tomorrow reads "1 day". We use end-of-day on the
    // ends_at boundary so a fresh signup at 11pm doesn't show "13 days
    // left" instead of 14.
    const trialDaysLeft = (() => {
        if (!isTrialing || !trialEndsAt) return null;
        const ends = new Date(trialEndsAt);
        if (Number.isNaN(ends.getTime())) return null;
        const ms = ends.setHours(23, 59, 59, 999) - Date.now();
        return Math.max(0, Math.ceil(ms / (1000 * 60 * 60 * 24)));
    })();
    // Real seat count = included + paid extras. Without this the progress bar
    // would max out at the included count even after the tenant has paid for
    // additional seats — so a 3-included plan with 2 paid extras would show
    // 5/3 (overflow red) instead of 5/5 (full but legitimate).
    const includedSeats = Number(plan?.users_included || 1);
    const extraSeats = Number(subscription?.extra_seats || 0);
    const totalSeats = includedSeats + extraSeats;
    const overTotal = userCount > totalSeats; // shouldn't happen — defensive only
    
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Settings</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Plan & usage
                        </h1>
                        <p className="text-ink-muted text-sm">
                            Subscription details and what your team is using right now.
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
            <Head title="Plan & Usage" />

            <div className="max-w-5xl space-y-8">
                {(isPastDue || renewal?.payment_url) && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-4 text-sm text-ink flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold text-terracotta">
                                {isPastDue
                                    ? 'Payment overdue — renew to keep your plan.'
                                    : 'Your renewal payment is due.'}
                            </p>
                            <p className="mt-0.5 text-ink-muted">
                                {renewal?.grace_ends_at
                                    ? `Pay by ${new Date(renewal.grace_ends_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })} to keep ${plan?.name || 'your subscription'}.`
                                    : `Complete payment to keep ${plan?.name || 'your subscription'} active.`}
                                {renewal?.amount != null ? ` Amount: RM ${Number(renewal.amount).toFixed(2)}.` : ''}
                            </p>
                        </div>
                        {renewal?.payment_url && (
                            <a
                                href={renewal.payment_url}
                                target="_blank"
                                rel="noreferrer"
                                className="px-4 py-1.5 rounded-lg bg-terracotta text-white text-eyebrow font-semibold uppercase hover:bg-terracotta-dark transition-colors"
                            >
                                Pay now
                            </a>
                        )}
                    </div>
                )}

                {/* Trial banner — visible whenever the subscription is in
                    `trialing` status. It's the same shape as the existing
                    "scheduled change" banner on Subscription/Index, but
                    sized down for the settings layout. The CTA goes to
                    pricing because that's where they convert. */}
                {isTrialing && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-4 text-sm text-ink flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold text-terracotta">
                                {trialDaysLeft === 0
                                    ? `Your ${plan?.name} trial ends today.`
                                    : `${trialDaysLeft} day${trialDaysLeft === 1 ? '' : 's'} left in your ${plan?.name} free trial.`}
                            </p>
                            <p className="mt-0.5 text-ink-muted">
                                {trialEndsAt
                                    ? `Pick a plan to keep your paid features — otherwise we'll switch you to ${pendingPlan?.name || 'Startup (Free)'} on ${new Date(trialEndsAt).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })}.`
                                    : `Pick a plan to keep your paid features after the trial ends.`}
                            </p>
                        </div>
                        <Link
                            href={route('subscription.index')}
                            className="px-4 py-1.5 rounded-lg bg-terracotta text-white text-eyebrow font-semibold uppercase hover:bg-terracotta-dark transition-colors"
                        >
                            Choose a plan
                        </Link>
                    </div>
                )}

                {/* Plan Overview Card */}
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-start justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider mb-2">
                                Current Plan
                            </h3>
                            <div className="flex items-center gap-3">
                                <h4 className="text-2xl font-display font-medium text-ink">
                                    {plan ? plan.name : 'No Active Plan'}
                                </h4>
                                {subscription?.status === 'active' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-forest/10 text-forest uppercase">
                                        Active
                                    </span>
                                )}
                                {isTrialing && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-terracotta/15 text-terracotta uppercase">
                                        Trial
                                    </span>
                                )}
                            </div>
                            <p className="text-ink-muted text-sm mt-1 font-medium">
                                {subscription?.interval === 'lifetime' ? (
                                    <>
                                        <span className="text-forest font-bold">Lifetime Access</span>
                                        <span className="text-ink-muted"> • Expires: Never</span>
                                    </>
                                ) : isTrialing ? (
                                    <>
                                        <span className="text-terracotta font-semibold">Free trial</span>
                                        {trialEndsAt && (
                                            <span className="text-ink-muted">
                                                {' • '}Auto-switches on {new Date(trialEndsAt).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        {subscription?.interval === 'yearly' ? 'Yearly Subscription' : 'Monthly Subscription'}
                                        {subscription?.current_period_ends_at && (
                                            <span className="text-ink-muted">
                                                {' • '}
                                                {subscription?.gateway === 'system' ? 'Expires' : 'Renews'} on {new Date(subscription.current_period_ends_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            </span>
                                        )}
                                    </>
                                )}
                            </p>
                        </div>
                        <Link
                            href={route('subscription.index')}
                            className="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta shadow-sm transition-colors"
                        >
                            {isTrialing ? 'Upgrade' : 'Change Plan'}
                        </Link>
                    </div>

                    <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t border-border-warm">
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Monthly Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink">
                                RM{plan?.price_monthly || '0'}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Yearly Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink">
                                RM{plan?.price_yearly || '0'}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">
                                Extra User Price
                            </span>
                            <p className="text-lg font-display font-medium text-ink text-forest">
                                {plan?.extra_user_price > 0 ? `RM${Number(plan.extra_user_price).toFixed(2)}/user` : 'N/A'}
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
                        {/* Users Usage */}
                        <div>
                            <div className="flex justify-between items-end mb-2">
                                <div>
                                    <h4 className="text-base font-display font-medium text-ink">Users</h4>
                                    <p className="text-ink-muted text-sm">
                                        {userCount} of {totalSeats} seats used
                                        {extraSeats > 0 && (
                                            <span className="text-ink-muted">
                                                {' '}({includedSeats} included + {extraSeats} paid extra{extraSeats === 1 ? '' : 's'})
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="text-2xl font-display font-medium text-ink">{userCount}</span>
                                    <span className="text-ink-muted font-medium"> / {plan?.users_included || 1}</span>
                                </div>
                            </div>
                            <div className="h-2 w-full bg-surface-alt rounded-full overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        overTotal ? 'bg-terracotta' : 'bg-forest'
                                    }`}
                                    style={{ width: `${Math.min(100, (userCount / totalSeats) * 100)}%` }}
                                />
                            </div>
                            {extraSeats > 0 && (
                                <p className="mt-2 text-xs font-semibold text-mustard bg-mustard/15 p-2 rounded-lg inline-block">
                                    {extraSeats} paid extra seat{extraSeats === 1 ? '' : 's'} on this plan · RM{Number(plan?.extra_user_price || 0).toFixed(2)}/seat/month
                                </p>
                            )}
                            {userCount >= totalSeats && Number(plan?.extra_user_price || 0) > 0 && !overTotal && (
                                <p className="mt-2 text-xs font-semibold text-ink-muted bg-cream p-2 rounded-lg inline-block">
                                    All seats used — adding the next user will buy an extra seat at RM{Number(plan.extra_user_price).toFixed(2)}/month.
                                </p>
                            )}
                            {userCount >= totalSeats && Number(plan?.extra_user_price || 0) === 0 && (
                                <p className="mt-2 text-xs font-semibold text-mustard bg-mustard/15 p-2 rounded-lg inline-block">
                                    You have reached your user limit. Upgrade to Corporate to add more members.
                                </p>
                            )}
                        </div>

                        {/* Note on billing */}
                        <div className="p-4 bg-cream rounded-xl border border-border-warm/50">
                            <h5 className="text-xs font-display font-medium text-ink uppercase tracking-wider mb-1">
                                Billing Note
                            </h5>
                            <p className="text-ink text-xs leading-relaxed">
                                Extra user charges are applied automatically when a new team member is added beyond your plan's included limit. 
                                For Corporate plans, you will be prompted to pay the extra user fee upon creation.
                            </p>
                        </div>
                    </div>
                </div>

                {copilotCredits?.metering && (
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider mb-2">Accountant copilot</h3>
                        <p className="text-sm text-ink-muted mb-6">
                            1 credit = 1 message. Included resets monthly. Purchased credits never expire.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            <div className="rounded-xl border border-border-warm p-4">
                                <p className="text-[10px] uppercase tracking-widest text-ink-muted font-semibold">Remaining</p>
                                <p className="text-2xl font-display font-medium text-ink mt-1">{copilotCredits.remaining}</p>
                            </div>
                            <div className="rounded-xl border border-border-warm p-4">
                                <p className="text-[10px] uppercase tracking-widest text-ink-muted font-semibold">Included left</p>
                                <p className="text-2xl font-display font-medium text-ink mt-1">
                                    {copilotCredits.included}{' '}
                                    <span className="text-sm text-ink-muted">/ {copilotCredits.quota}</span>
                                </p>
                            </div>
                            <div className="rounded-xl border border-border-warm p-4">
                                <p className="text-[10px] uppercase tracking-widest text-ink-muted font-semibold">Purchased</p>
                                <p className="text-2xl font-display font-medium text-ink mt-1">{copilotCredits.purchased}</p>
                            </div>
                        </div>
                        <p className="text-xs text-ink-muted mb-4">
                            Included resets on {copilotCredits.resets_on}. Used this month: {copilotCredits.used_this_month}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {(copilotCredits.packs || []).map((pack) => (
                                <button
                                    key={pack.slug}
                                    type="button"
                                    onClick={() => router.post(route('settings.plan.copilot_credits'), { pack: pack.slug })}
                                    className="px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-cream hover:bg-surface-alt"
                                >
                                    Buy {pack.credits} · RM{Number(pack.amount).toFixed(0)}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Features List */}
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                            Plan Features
                        </h3>
                    </div>
                    <div className="p-6">
                        <ul className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {(plan?.features || []).map((feature, idx) => (
                                <li key={idx} className="flex items-center gap-3 text-sm text-ink">
                                    <svg className="w-5 h-5 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {feature}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <BillingHistory events={history} />
            </div>
        </AuthenticatedLayout>
    );
}
