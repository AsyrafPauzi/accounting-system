import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import FAQRow from '@/Components/Brand/FAQRow';

// Email the "Talk to sales" button on the Enterprise card opens. Wire this to
// a real contact form whenever there's appetite — for now mailto: is the
// fastest path that won't black-hole leads.
const SALES_EMAIL = 'sales@bukucloud.com';
const SALES_SUBJECT = 'BukuCloud Enterprise enquiry';
const SALES_BODY =
    "Hi BukuCloud team,\n\nI'd like to discuss an Enterprise / self-hosted deployment.\n\n" +
    "Company:\nNumber of users:\nPreferred deployment (cloud / on-prem):\nTimeline:\n\nThanks!";

const FEATURED_SLUG = 'growth'; // tier we want to nudge most signups towards

const formatRM = (raw) => {
    const n = Number(raw);
    if (!Number.isFinite(n)) return '0.00';
    return n.toFixed(2);
};

const formatDate = (raw) => {
    if (!raw) return '';
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleDateString();
};

export default function SubscriptionIndex({ auth, plans = [], currentSubscription = null }) {
    const { data, setData, processing } = useForm({
        plan_id: currentSubscription?.plan_id || plans[0]?.id || '',
        interval: currentSubscription?.interval || 'monthly',
    });

    const handleSubmit = (e, planId = null) => {
        if (e) e.preventDefault();
        const targetPlanId = planId || data.plan_id;
        router.post(route('subscription.checkout'), {
            plan_id: targetPlanId,
            interval: data.interval,
        });
    };

    const handleCancelPending = () => {
        if (!window.confirm('Cancel the scheduled plan change and stay on your current plan?')) return;
        router.post(route('subscription.cancel_pending'));
    };

    const currentPlanId = currentSubscription?.plan_id;
    const currentEndsAt = currentSubscription?.current_period_ends_at;
    const pendingPlan = currentSubscription?.pending_plan;
    const hasPending = !!currentSubscription?.pending_plan_id && !!pendingPlan;
    const isTrialing = currentSubscription?.status === 'trialing';
    const trialDaysLeft = (() => {
        if (!isTrialing || !currentEndsAt) return null;
        const ends = new Date(currentEndsAt);
        if (Number.isNaN(ends.getTime())) return null;
        const ms = ends.setHours(23, 59, 59, 999) - Date.now();
        return Math.max(0, Math.ceil(ms / (1000 * 60 * 60 * 24)));
    })();

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Subscription</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Plans & pricing</h1>
                    <p className="text-ink-muted text-sm">Books made for the way you actually work — pay for the seat you sit in.</p>
                </div>
            }
        >
            <Head title="Pricing" />

            <div className="space-y-10 max-w-7xl">
                {isTrialing && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-4 text-sm text-ink flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold text-terracotta">
                                {trialDaysLeft === 0
                                    ? `Your ${currentSubscription.plan?.name} trial ends today.`
                                    : `${trialDaysLeft} day${trialDaysLeft === 1 ? '' : 's'} left in your ${currentSubscription.plan?.name} free trial.`}
                            </p>
                            <p className="mt-0.5 text-ink-muted">
                                {currentEndsAt
                                    ? `Pick a plan below to keep all the paid features. Otherwise we'll switch you to ${pendingPlan?.name || 'Startup (Free)'} on ${formatDate(currentEndsAt)}.`
                                    : `Pick a plan below to keep all the paid features after your trial ends.`}
                            </p>
                        </div>
                        <span className="px-3 py-1 bg-terracotta/20 text-terracotta rounded-full text-eyebrow font-semibold uppercase">
                            Trial
                        </span>
                    </div>
                )}

                {currentSubscription && !isTrialing && (
                    <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-4 text-sm text-forest-dark dark:text-forest-light flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold">
                                You’re on the {currentSubscription.plan?.name} plan.
                            </p>
                            {currentEndsAt && (
                                <p className="mt-0.5 text-forest dark:text-forest-light">
                                    Renews {formatDate(currentEndsAt)}
                                </p>
                            )}
                        </div>
                        <span className="px-3 py-1 bg-forest/15 text-forest-dark dark:text-forest-light rounded-full text-eyebrow font-semibold uppercase">
                            Current
                        </span>
                    </div>
                )}

                {hasPending && !isTrialing && (
                    <div className="bg-mustard/15 border border-mustard/40 rounded-2xl px-6 py-4 text-sm text-ink flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold">
                                Scheduled change: switching to {pendingPlan.name}
                                {currentEndsAt ? ` on ${formatDate(currentEndsAt)}` : ''}.
                            </p>
                            <p className="mt-0.5 text-ink-muted">
                                You’ll keep your current plan until then. No charges happen now.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={handleCancelPending}
                            className="px-4 py-1.5 rounded-lg bg-ink text-cream text-eyebrow font-semibold uppercase hover:bg-ink-muted transition-colors"
                        >
                            Cancel scheduled change
                        </button>
                    </div>
                )}

                <div className="flex justify-center">
                    <div className="bg-surface p-1 rounded-xl border border-border-warm inline-flex">
                        <button
                            type="button"
                            onClick={() => setData('interval', 'monthly')}
                            className={`px-6 py-2 rounded-lg text-sm font-semibold transition-colors ${
                                data.interval === 'monthly'
                                    ? 'bg-ink text-cream'
                                    : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            Monthly
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('interval', 'yearly')}
                            className={`px-6 py-2 rounded-lg text-sm font-semibold transition-colors ${
                                data.interval === 'yearly'
                                    ? 'bg-ink text-cream'
                                    : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            Yearly · save ~17 %
                        </button>
                    </div>
                </div>

                {/*
                    Three-column layout: row 1 gets Startup / Solo / Growth,
                    row 2 gets Corporate / Enterprise (left-aligned). Cards
                    are wider here than the old 5-col layout, so prices and
                    feature copy have plenty of breathing room.
                */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                    {plans.map((plan) => (
                        <PlanCard
                            key={plan.id}
                            plan={plan}
                            interval={data.interval}
                            isActive={currentPlanId === plan.id}
                            isSelected={data.plan_id === plan.id}
                            isFeatured={plan.slug === FEATURED_SLUG}
                            processing={processing}
                            onSelect={() => setData('plan_id', plan.id)}
                            onCheckout={(e) => handleSubmit(e, plan.id)}
                        />
                    ))}
                </div>

                <section className="bg-surface border border-border-warm rounded-3xl p-8 sm:p-10">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Common questions</p>
                    <h2 className="font-display text-2xl font-medium text-ink mt-2">Things people ask before signing up</h2>
                    <div className="mt-6 divide-y divide-border-warm">
                        <FAQRow
                            question="Can I switch plans later?"
                            answer="Yes — upgrade anytime by talking to us, and downgrade anytime from the pricing page. Downgrades take effect on your next renewal date so you keep the features you've already paid for."
                        />
                        <FAQRow
                            question="What happens if I add more users than my plan covers?"
                            answer="Growth and Corporate plans let you add extra seats at a flat per-user monthly fee. The new user is created as soon as the payment goes through."
                        />
                        <FAQRow
                            question="What's different about Enterprise?"
                            answer="Enterprise is for companies that need self-hosted deployments, custom contracts, SSO, white-labelling or data-residency guarantees. Pricing is based on your scope, so we always quote it after a short call."
                        />
                        <FAQRow
                            question="Is my data safe?"
                            answer="Daily encrypted backups, audit logs on every change, role-based access, and tenant-isolated storage. Your books are yours, always exportable."
                        />
                        <FAQRow
                            question="Do you support SST and LHDN e-Invoice?"
                            answer="SST is built into invoicing and reports across every paid plan. LHDN MyInvois e-Invoicing is included on Growth and above."
                        />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

/**
 * One pricing card — handles three modes:
 *   1. Free plan (RM 0)  → "Choose plan" button, no recurring charge text.
 *   2. Paid plan         → big price, "Choose plan" button, optional "Most
 *      popular" badge, optional extra-user line.
 *   3. Contact sales     → no price (shows "Talk to us"), opens mailto:
 *      instead of POSTing to /subscription/checkout.
 */
function PlanCard({ plan, interval, isActive, isSelected, isFeatured, processing, onSelect, onCheckout }) {
    const isContact = !!plan.is_contact_sales;
    const isFree = !isContact && Number(plan.price_monthly) === 0 && Number(plan.price_yearly) === 0;
    const rawPrice = interval === 'yearly' ? plan.price_yearly : plan.price_monthly;

    const borderTone = isFeatured
        ? 'border-terracotta'
        : isContact
            ? 'border-ink'
            : isSelected
                ? 'border-ink'
                : 'border-border-warm hover:border-ink-muted/40';

    const bgTone = isContact ? 'bg-ink text-cream' : 'bg-surface';

    return (
        <div
            className={`relative flex flex-col p-7 rounded-3xl border transition-colors h-full ${borderTone} ${bgTone}`}
            onClick={() => !isActive && !isContact && onSelect?.()}
            style={{ cursor: isActive || isContact ? 'default' : 'pointer' }}
        >
            {isFeatured && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-terracotta text-white text-eyebrow font-semibold uppercase rounded-full whitespace-nowrap">
                    Most popular
                </span>
            )}
            {isContact && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-mustard text-ink text-eyebrow font-semibold uppercase rounded-full whitespace-nowrap">
                    Custom
                </span>
            )}

            <div className="mb-6">
                <p className={`text-eyebrow font-semibold uppercase ${isContact ? 'text-cream/70' : 'text-ink-muted'}`}>{plan.name}</p>
                <div className="mt-3 min-h-[3.5rem] flex flex-col">
                    {isContact ? (
                        <span className="font-display text-4xl font-medium tracking-tight whitespace-nowrap">Talk to us</span>
                    ) : isFree ? (
                        <span className="font-display text-4xl sm:text-5xl font-medium text-ink tracking-tight font-tabular whitespace-nowrap">
                            Free
                        </span>
                    ) : (
                        <div className="flex items-baseline gap-1.5 flex-wrap">
                            <span className="font-display text-4xl sm:text-5xl font-medium text-ink tracking-tight font-tabular whitespace-nowrap">
                                RM{formatRM(rawPrice)}
                            </span>
                            <span className="text-ink-muted text-sm whitespace-nowrap">
                                /{interval === 'yearly' ? 'year' : 'month'}
                            </span>
                        </div>
                    )}
                </div>
                {!isContact && plan.users_included > 0 && plan.users_included < 1000 && (
                    <p className={`text-xs mt-2 ${isContact ? 'text-cream/70' : 'text-ink-muted'}`}>
                        {plan.users_included} user{plan.users_included === 1 ? '' : 's'} included
                    </p>
                )}
                {isContact && (
                    <p className="text-xs mt-2 text-cream/70">Pricing scoped to your needs</p>
                )}
            </div>

            <ul className="mb-8 space-y-2.5 flex-1">
                {(plan.features || []).map((feature, i) => (
                    <li key={i} className={`flex items-start gap-2.5 text-sm leading-snug ${isContact ? 'text-cream' : 'text-ink'}`}>
                        <svg
                            className={`w-4 h-4 flex-shrink-0 mt-0.5 ${
                                isContact ? 'text-mustard' : 'text-forest dark:text-forest-light'
                            }`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{feature}</span>
                    </li>
                ))}
                {plan.extra_user_price > 0 && !isContact && (
                    <li className="flex items-baseline gap-2 text-sm text-terracotta font-medium leading-snug pt-3 border-t border-border-warm">
                        <span className="font-mono font-tabular whitespace-nowrap">RM{formatRM(plan.extra_user_price)}</span>
                        <span className="text-ink-muted">per extra user/month</span>
                    </li>
                )}
            </ul>

            <div className="mt-auto">
                {isContact ? (
                    <a
                        href={`mailto:${SALES_EMAIL}?subject=${encodeURIComponent(SALES_SUBJECT)}&body=${encodeURIComponent(SALES_BODY)}`}
                        className="block w-full text-center py-3 px-4 rounded-2xl font-semibold text-sm bg-mustard text-ink hover:bg-mustard/80 transition-colors whitespace-nowrap"
                    >
                        Talk to sales →
                    </a>
                ) : (
                    <button
                        type="button"
                        disabled={isActive || processing}
                        onClick={(e) => {
                            e.stopPropagation();
                            if (!isActive) onCheckout?.(e);
                        }}
                        className={`w-full py-3 px-4 rounded-2xl font-semibold text-sm transition-colors whitespace-nowrap ${
                            isActive
                                ? 'bg-forest/10 text-forest dark:text-forest-light border border-forest/30 cursor-not-allowed'
                                : isFeatured
                                    ? 'bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light'
                                    : 'bg-ink text-cream hover:bg-ink-muted'
                        }`}
                    >
                        {isActive
                            ? 'Current plan'
                            : isSelected && processing
                                ? 'Redirecting…'
                                : isFree
                                    ? 'Start free'
                                    : 'Choose plan'}
                    </button>
                )}
            </div>
        </div>
    );
}
