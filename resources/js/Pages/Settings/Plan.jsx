import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function PlanSettings({ auth, subscription, userCount }) {
    const plan = subscription?.plan;
    const isCorporate = plan?.slug === 'corporate';
    
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
                            </div>
                            <p className="text-ink-muted text-sm mt-1 font-medium">
                                {subscription?.interval === 'lifetime' ? (
                                    <>
                                        <span className="text-forest font-bold">Lifetime Access</span>
                                        <span className="text-ink-muted"> • Expires: Never</span>
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
                            Change Plan
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
                                        {userCount} of {plan?.users_included || 1} users included
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
                                        userCount > (plan?.users_included || 1) ? 'bg-terracotta' : 'bg-terracotta'
                                    }`}
                                    style={{ width: `${Math.min(100, (userCount / (plan?.users_included || 1)) * 100)}%` }}
                                />
                            </div>
                            {userCount > (plan?.users_included || 1) && isCorporate && (
                                <p className="mt-2 text-xs font-semibold text-terracotta bg-terracotta/10 p-2 rounded-lg inline-block">
                                    You are using {userCount - plan.users_included} extra users. Each extra user costs RM{Number(plan.extra_user_price).toFixed(2)}/month.
                                </p>
                            )}
                            {userCount >= (plan?.users_included || 1) && !isCorporate && (
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
            </div>
        </AuthenticatedLayout>
    );
}
