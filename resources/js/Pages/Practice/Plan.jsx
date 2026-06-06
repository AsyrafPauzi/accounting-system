import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

// The toggle button only knows about monthly / yearly. If a firm has a
// "lifetime" subscription (granted via super-admin) the raw interval
// won't match either button, so neither lights up AND posting that
// value back to the checkout endpoint fails its `in:monthly,yearly`
// validator silently. Default to monthly when the value is anything
// other than yearly.
const normaliseInterval = (raw) => (raw === 'yearly' ? 'yearly' : 'monthly');

/**
 * Practice plan picker. Shown to firm-owners after signup so they can
 * upgrade from Practice Free → a paid tier whenever they're ready.
 *
 * Mirrors the SME pricing page visually but lives under PracticeLayout
 * because firm-owners don't have a tenant context. Free is included
 * deliberately so people can downgrade if they shed clients.
 */
export default function PracticePlan({ plans = [], currentSubscription = null }) {
    const { flash = {} } = usePage().props;
    const [interval, setInterval] = useState(normaliseInterval(currentSubscription?.interval));
    const { processing } = useForm();

    const currentPlanId = currentSubscription?.plan_id;
    const currentPlanSlug = currentSubscription?.plan?.slug;
    const currentPlanIsPaid = currentPlanSlug && currentPlanSlug !== 'practice-free';
    const pendingPlan = currentSubscription?.pending_plan;
    const hasPending = !!currentSubscription?.pending_plan_id && !!pendingPlan;
    const currentEndsAt = currentSubscription?.current_period_ends_at;
    const currentPriceMonthly = currentSubscription?.plan?.price_monthly;

    const handleChoose = (planId, plan) => {
        // Contact-sales tiers (Practice Self-hosted, etc.) never go
        // through Toyyibpay — pop the user into their email client so
        // they can request a quote. The server also refuses these in
        // PracticeBillingController::checkout() as defence in depth.
        if (plan.is_contact_sales) {
            const subject = encodeURIComponent(`BukuCloud ${plan.name} enquiry`);
            window.location.href = `mailto:sales@bukucloud.com?subject=${subject}`;
            return;
        }
        router.post(route('practice.plan.checkout'), { plan_id: planId, interval });
    };

    return (
        <PracticeLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Practice plans</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        Pick the plan that fits your firm
                    </h1>
                    <p className="text-ink-muted text-sm">
                        Free for your first client. Scale up as your book grows. Cancel or downgrade anytime.
                    </p>
                </div>
            }
        >
            <Head title="Practice plans" />

            <div className="space-y-8">
                {/* Flash banners — surface checkout errors and downgrade
                    confirmations from the controller. Without this the
                    page silently reloads after a paid→paid attempt. */}
                {flash.success && (
                    <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-4 text-sm text-forest-dark">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-4 text-sm text-terracotta-dark">
                        {flash.error}
                    </div>
                )}
                {flash.info && (
                    <div className="bg-mustard/10 border border-mustard/40 rounded-2xl px-6 py-4 text-sm text-ink">
                        {flash.info}
                    </div>
                )}

                {currentSubscription && (
                    <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-4 text-sm text-forest-dark dark:text-forest-light flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p className="font-semibold">
                                You&apos;re on the {currentSubscription.plan?.name} plan
                                {currentPriceMonthly !== undefined && Number(currentPriceMonthly) > 0 && (
                                    <span className="font-normal text-forest">
                                        {' '}— RM {formatRM(currentPriceMonthly)}/mo
                                    </span>
                                )}
                                .
                            </p>
                            {currentSubscription.interval && (
                                <p className="mt-0.5 text-forest dark:text-forest-light">
                                    Billed <b>{currentSubscription.interval}</b>
                                    {currentEndsAt && <> · renews {formatDate(currentEndsAt)}</>}
                                    {currentSubscription.plan?.client_cap !== null
                                     && currentSubscription.plan?.client_cap !== undefined
                                     && <> · {currentSubscription.plan.client_cap}-client cap</>}
                                    {currentSubscription.plan?.client_cap === null && <> · unlimited clients</>}
                                </p>
                            )}
                            {hasPending && (
                                <p className="mt-1 text-amber-700 dark:text-amber-300">
                                    Scheduled to switch to <b>{pendingPlan?.name}</b> on{' '}
                                    {formatDate(currentEndsAt)}.
                                </p>
                            )}
                        </div>
                        <span className="px-3 py-1 bg-forest/15 text-forest-dark dark:text-forest-light rounded-full text-eyebrow font-semibold uppercase">
                            Active
                        </span>
                    </div>
                )}

                <div className="flex items-center justify-center gap-2 bg-surface border border-border-warm rounded-full p-1 w-fit mx-auto">
                    {['monthly', 'yearly'].map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => setInterval(v)}
                            className={`px-4 py-1.5 rounded-full text-sm font-semibold transition-colors ${
                                interval === v
                                    ? 'bg-terracotta text-white'
                                    : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            {v === 'monthly' ? 'Monthly' : 'Yearly (save 2 months)'}
                        </button>
                    ))}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-5">
                    {plans.map((plan) => {
                        const isCurrent = plan.id === currentPlanId;
                        const isContactSales = !!plan.is_contact_sales;
                        const isFree = !isContactSales && Number(plan.price_monthly) === 0;
                        const monthly = Number(plan.price_monthly);
                        const yearly = Number(plan.price_yearly);
                        const displayPrice = interval === 'yearly' ? yearly : monthly;
                        const featured = plan.slug === 'practice-growth';
                        // Practice paid → paid upgrades flow through Toyyibpay
                        // (controller stashes pending_plan_id, webhook swaps
                        // on payment). The only "Talk to sales" path is
                        // contact-sales tiers like Practice Self-hosted.
                        const buttonLabel = isCurrent
                            ? 'Current plan'
                            : isContactSales
                              ? 'Talk to sales'
                              : isFree
                                ? 'Switch to free'
                                : 'Choose plan';

                        return (
                            <div
                                key={plan.id}
                                className={`relative bg-surface rounded-2xl border p-6 flex flex-col ${
                                    featured
                                        ? 'border-terracotta shadow-lg ring-2 ring-terracotta/20'
                                        : 'border-border-warm'
                                }`}
                            >
                                {featured && (
                                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 bg-terracotta text-white text-eyebrow font-semibold uppercase px-3 py-1 rounded-full">
                                        Most popular
                                    </span>
                                )}

                                <h3 className="font-display text-xl font-semibold text-ink">{plan.name}</h3>

                                <div className="mt-4">
                                    {isContactSales ? (
                                        <p className="text-3xl font-bold text-ink">
                                            Custom
                                            <span className="block text-sm font-normal text-ink-muted mt-1">
                                                License + setup quoted on demand
                                            </span>
                                        </p>
                                    ) : isFree ? (
                                        <p className="text-3xl font-bold text-ink">
                                            RM 0
                                            <span className="text-sm font-normal text-ink-muted">
                                                {' '}/{interval === 'yearly' ? 'yr' : 'mo'}
                                            </span>
                                        </p>
                                    ) : (
                                        <>
                                            <p className="text-3xl font-bold text-ink">
                                                RM {formatRM(displayPrice)}
                                                <span className="text-sm font-normal text-ink-muted">
                                                    {' '}/{interval === 'yearly' ? 'yr' : 'mo'}
                                                </span>
                                            </p>
                                            {interval === 'yearly' && (
                                                <p className="text-xs text-ink-muted mt-1">
                                                    Equivalent to RM {formatRM(yearly / 12)} / month
                                                </p>
                                            )}
                                        </>
                                    )}
                                </div>

                                {/* Plan vitals — explicit so users don't have to
                                    parse the bullets to figure out cap & seats. */}
                                <dl className="mt-3 grid grid-cols-2 gap-2 text-xs text-ink-muted border-y border-border-warm py-3">
                                    <div>
                                        <dt className="text-eyebrow font-semibold uppercase">Clients</dt>
                                        <dd className="text-ink font-medium">
                                            {plan.client_cap === null ? 'Unlimited' : `Up to ${plan.client_cap}`}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-eyebrow font-semibold uppercase">Seats</dt>
                                        <dd className="text-ink font-medium">
                                            {plan.users_included} included
                                            {Number(plan.extra_user_price) > 0 && (
                                                <span className="block text-xs text-ink-muted">
                                                    +RM {formatRM(plan.extra_user_price)}/seat
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                </dl>

                                <ul className="mt-4 space-y-2 text-sm text-ink flex-1">
                                    {(plan.features || []).map((feat, idx) => (
                                        <li key={idx} className="flex items-start gap-2">
                                            <span className="text-forest mt-0.5">✓</span>
                                            <span>{feat}</span>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() => handleChoose(plan.id, plan)}
                                    disabled={processing || isCurrent}
                                    className={`mt-6 w-full py-2.5 rounded-xl font-semibold text-sm transition-colors ${
                                        isCurrent
                                            ? 'bg-border-warm text-ink-muted cursor-not-allowed'
                                            : isContactSales
                                              ? 'bg-mustard/30 text-ink hover:bg-mustard/50'
                                              : featured
                                                ? 'bg-terracotta text-white hover:bg-terracotta-dark'
                                                : 'bg-ink text-cream hover:bg-ink-muted'
                                    }`}
                                >
                                    {buttonLabel}
                                </button>
                            </div>
                        );
                    })}
                </div>

                <div className="text-center text-xs text-ink-muted">
                    Need a custom plan or self-hosted deployment?{' '}
                    <a
                        href="mailto:sales@bukucloud.com?subject=BukuCloud%20Practice%20enquiry"
                        className="text-terracotta hover:text-terracotta-dark font-semibold"
                    >
                        Talk to us
                    </a>
                    .
                </div>
            </div>
        </PracticeLayout>
    );
}
