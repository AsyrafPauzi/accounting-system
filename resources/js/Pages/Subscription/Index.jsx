import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

export default function SubscriptionIndex({ auth, plans = [], currentSubscription = null }) {
    const { data, setData, post, processing } = useForm({
        plan_id: currentSubscription?.plan_id || plans[0]?.id || '',
        interval: currentSubscription?.interval || 'monthly',
    });

    const handleSubmit = (e, planId = null) => {
        if (e) e.preventDefault();
        
        const targetPlanId = planId || data.plan_id;
        
        // Use router.post directly to ensure we send the latest values
        router.post(route('subscription.checkout'), {
            plan_id: targetPlanId,
            interval: data.interval
        });
    };

    const currentPlanId = currentSubscription?.plan_id;
    const currentEndsAt = currentSubscription?.current_period_ends_at;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Pricing Plans</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Choose the plan that fits your business needs.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Pricing" />

            <div className="space-y-8">
                {currentSubscription && (
                    <div className="bg-emerald-50 border border-emerald-200 rounded-2xl px-6 py-4 text-sm text-emerald-800 flex items-center justify-between">
                        <div>
                            <p className="font-bold">
                                Your account is currently active on the {currentSubscription.plan?.name} plan.
                            </p>
                            {currentEndsAt && (
                                <p className="mt-0.5 text-emerald-700">
                                    Next renewal on {new Date(currentEndsAt).toLocaleDateString()}
                                </p>
                            )}
                        </div>
                        <span className="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold uppercase">
                            Current Plan
                        </span>
                    </div>
                )}

                {/* Interval Selector */}
                <div className="flex justify-center">
                    <div className="bg-white p-1 rounded-xl border border-slate-200 inline-flex shadow-sm">
                        <button
                            type="button"
                            onClick={() => setData('interval', 'monthly')}
                            className={`px-6 py-2 rounded-lg text-sm font-bold transition-all ${
                                data.interval === 'monthly'
                                    ? 'bg-slate-900 text-white shadow-md'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                        >
                            Monthly
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('interval', 'yearly')}
                            className={`px-6 py-2 rounded-lg text-sm font-bold transition-all ${
                                data.interval === 'yearly'
                                    ? 'bg-slate-900 text-white shadow-md'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                        >
                            Yearly (Save 10%+)
                        </button>
                    </div>
                </div>

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                    {plans.map((plan) => {
                        const isActive = currentPlanId === plan.id;
                        const isSelected = data.plan_id === plan.id;
                        const price = data.interval === 'yearly' ? plan.price_yearly : plan.price_monthly;
                        
                        return (
                            <div 
                                key={plan.id}
                                className={`relative flex flex-col p-8 rounded-3xl border transition-all duration-300 h-full ${
                                    isSelected 
                                        ? 'border-blue-600 ring-4 ring-blue-50 bg-white shadow-xl' 
                                        : 'border-slate-200 bg-white/50 hover:bg-white hover:shadow-lg'
                                }`}
                                onClick={() => !isActive && setData('plan_id', plan.id)}
                                style={{ cursor: isActive ? 'default' : 'pointer' }}
                            >
                                {plan.slug === 'sme' && (
                                    <span className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-full shadow-lg">
                                        Most Popular
                                    </span>
                                )}
                                
                                <div className="mb-6">
                                    <h3 className="text-xl font-bold text-slate-900">{plan.name}</h3>
                                    <div className="mt-4 flex items-baseline">
                                        <span className="text-4xl font-black text-slate-900 tracking-tight">RM{price}</span>
                                        <span className="ml-1 text-slate-500 text-sm">/{data.interval === 'yearly' ? 'year' : 'mo'}</span>
                                    </div>
                                </div>

                                <ul className="mb-8 space-y-4 flex-1">
                                    {(plan.features || []).map((feature, i) => (
                                        <li key={i} className="flex items-start gap-3 text-sm text-slate-600 leading-tight">
                                            <svg className="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {feature}
                                        </li>
                                    ))}
                                    {plan.extra_user_price > 0 && (
                                        <li className="flex items-start gap-3 text-sm text-blue-600 font-semibold leading-tight">
                                            <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            RM{Number(plan.extra_user_price).toFixed(2)}/extra user
                                        </li>
                                    )}
                                </ul>

                                <div className="mt-auto">
                                    <button
                                        type="button"
                                        disabled={isActive || processing}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            if (!isActive) {
                                                handleSubmit(e, plan.id);
                                            }
                                        }}
                                        className={`w-full py-3 px-6 rounded-2xl font-bold text-sm transition-all ${
                                            isActive
                                                ? 'bg-emerald-50 text-emerald-700 border-2 border-emerald-200 cursor-not-allowed'
                                                : isSelected
                                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-200 hover:bg-blue-700'
                                                    : 'bg-slate-900 text-white hover:bg-slate-800'
                                        }`}
                                    >
                                        {isActive ? '✓ Current Plan' : (isSelected && processing ? 'Redirecting...' : 'Get Started')}
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>


            </div>
        </AuthenticatedLayout>
    );
}
