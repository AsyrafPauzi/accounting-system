import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import FAQRow from '@/Components/Brand/FAQRow';

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

    const currentPlanId = currentSubscription?.plan_id;
    const currentEndsAt = currentSubscription?.current_period_ends_at;

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

            <div className="space-y-10 max-w-6xl">
                {currentSubscription && (
                    <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-4 text-sm text-forest-dark dark:text-forest-light flex items-center justify-between">
                        <div>
                            <p className="font-semibold">
                                You’re on the {currentSubscription.plan?.name} plan.
                            </p>
                            {currentEndsAt && (
                                <p className="mt-0.5 text-forest dark:text-forest-light">
                                    Renews {new Date(currentEndsAt).toLocaleDateString()}
                                </p>
                            )}
                        </div>
                        <span className="px-3 py-1 bg-forest/15 text-forest-dark dark:text-forest-light rounded-full text-eyebrow font-semibold uppercase">
                            Current
                        </span>
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
                            Yearly · save 10%+
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                    {plans.map((plan) => {
                        const isActive = currentPlanId === plan.id;
                        const isSelected = data.plan_id === plan.id;
                        const isFeatured = plan.slug === 'sme';
                        const price = data.interval === 'yearly' ? plan.price_yearly : plan.price_monthly;

                        return (
                            <div
                                key={plan.id}
                                className={`relative flex flex-col p-8 rounded-3xl border transition-colors h-full ${
                                    isFeatured
                                        ? 'border-terracotta bg-surface'
                                        : isSelected
                                            ? 'border-ink bg-surface'
                                            : 'border-border-warm bg-surface hover:border-ink-muted/40'
                                }`}
                                onClick={() => !isActive && setData('plan_id', plan.id)}
                                style={{ cursor: isActive ? 'default' : 'pointer' }}
                            >
                                {isFeatured && (
                                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-terracotta text-white text-eyebrow font-semibold uppercase rounded-full">
                                        Most popular
                                    </span>
                                )}

                                <div className="mb-6">
                                    <p className="text-eyebrow font-semibold uppercase text-ink-muted">{plan.name}</p>
                                    <div className="mt-3 flex items-baseline gap-1">
                                        <span className="font-display text-5xl font-medium text-ink tracking-tight font-tabular">RM{price}</span>
                                        <span className="text-ink-muted text-sm">/{data.interval === 'yearly' ? 'year' : 'month'}</span>
                                    </div>
                                </div>

                                <ul className="mb-8 space-y-3 flex-1">
                                    {(plan.features || []).map((feature, i) => (
                                        <li key={i} className="flex items-start gap-3 text-sm text-ink leading-snug">
                                            <svg className="w-5 h-5 text-forest dark:text-forest-light flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                    {plan.extra_user_price > 0 && (
                                        <li className="flex items-start gap-3 text-sm text-terracotta font-medium leading-snug pt-2 border-t border-border-warm">
                                            <span className="font-mono font-tabular">RM{Number(plan.extra_user_price).toFixed(2)}</span>
                                            <span className="text-ink-muted">per extra user/month</span>
                                        </li>
                                    )}
                                </ul>

                                <div className="mt-auto">
                                    <button
                                        type="button"
                                        disabled={isActive || processing}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            if (!isActive) handleSubmit(e, plan.id);
                                        }}
                                        className={`w-full py-3 px-6 rounded-2xl font-semibold text-sm transition-colors ${
                                            isActive
                                                ? 'bg-forest/10 text-forest dark:text-forest-light border border-forest/30 cursor-not-allowed'
                                                : isFeatured
                                                    ? 'bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light'
                                                    : 'bg-ink text-cream hover:bg-ink-muted'
                                        }`}
                                    >
                                        {isActive ? 'Current plan' : (isSelected && processing ? 'Redirecting…' : 'Choose plan')}
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <section className="bg-surface border border-border-warm rounded-3xl p-8 sm:p-10">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Common questions</p>
                    <h2 className="font-display text-2xl font-medium text-ink mt-2">Things people ask before signing up</h2>
                    <div className="mt-6 divide-y divide-border-warm">
                        <FAQRow
                            question="Can I switch plans later?"
                            answer="Yes — upgrade or downgrade anytime. We pro-rate the difference so you only pay for what you use."
                        />
                        <FAQRow
                            question="What happens if I add more users than my plan covers?"
                            answer="You can add seats above the included count for a small monthly fee per user. Your books keep working — no hard limit."
                        />
                        <FAQRow
                            question="Is my data safe?"
                            answer="Daily encrypted backups, audit logs on every change, and access controlled by role. Your books are yours, always exportable."
                        />
                        <FAQRow
                            question="Do you support SST and LHDN e-Invoice?"
                            answer="Yes. SST is built into invoicing and reports. LHDN e-Invoice support is available on SME and Corporate plans."
                        />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
