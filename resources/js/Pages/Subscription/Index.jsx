import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function SubscriptionIndex({ auth, plans = [], currentSubscription = null }) {
    const { data, setData, post, processing } = useForm({
        plan_id: plans[0]?.id || '',
        interval: 'monthly',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('subscription.checkout'));
    };

    const currentPlanName = currentSubscription?.plan?.name;
    const currentInterval = currentSubscription?.interval;
    const currentEndsAt = currentSubscription?.current_period_ends_at;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Subscription</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Upgrade to unlock full dashboard, reports and modules.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Subscription" />

            <div className="space-y-6">
                {currentSubscription && (
                    <div className="bg-emerald-50 border border-emerald-200 rounded-2xl px-6 py-4 text-sm text-emerald-800">
                        <p className="font-semibold">
                            You&apos;re on <span className="underline">{currentPlanName}</span> ({currentInterval}) plan.
                        </p>
                        {currentEndsAt && (
                            <p className="mt-1">
                                Current period ends on{' '}
                                <span className="font-mono">
                                    {new Date(currentEndsAt).toLocaleDateString('en-MY', {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                                .
                            </p>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-4">
                            Choose your billing interval
                        </h3>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <button
                                    type="button"
                                    onClick={() => setData('interval', 'monthly')}
                                    className={`p-4 rounded-xl border text-left text-sm font-medium transition-all ${
                                        data.interval === 'monthly'
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                                            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700'
                                    }`}
                                >
                                    <div className="flex items-baseline justify-between mb-1">
                                        <span>Monthly</span>
                                        {plans[0] && (
                                            <span className="font-bold text-slate-900">
                                                RM {Number(plans[0].price_monthly).toFixed(2)}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-500">Best to start small and scale later.</p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setData('interval', 'yearly')}
                                    className={`p-4 rounded-xl border text-left text-sm font-medium transition-all ${
                                        data.interval === 'yearly'
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                                            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700'
                                    }`}
                                >
                                    <div className="flex items-baseline justify-between mb-1">
                                        <span>Yearly</span>
                                        {plans[0] && (
                                            <span className="font-bold text-slate-900">
                                                RM {Number(plans[0].price_yearly).toFixed(2)}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-500">Pay once a year. Fewer invoices.</p>
                                </button>
                            </div>

                            <input type="hidden" name="plan_id" value={data.plan_id} />

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={processing || !data.plan_id || currentSubscription}
                                    className="w-full inline-flex items-center justify-center py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25 border-0 text-sm"
                                >
                                    {processing ? 'Activating...' : (currentSubscription ? 'Subscription Active' : 'Activate subscription')}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="bg-slate-900 rounded-2xl p-6 text-slate-100 shadow-lg">
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-blue-300 mb-3">
                            Pro plan highlights
                        </h3>
                        <ul className="space-y-2 text-sm">
                            <li>• Full dashboard & financial reports</li>
                            <li>• Unlimited invoices, customers & credit notes</li>
                            <li>• Priority support</li>
                        </ul>
                        <p className="mt-4 text-xs text-slate-400">
                            Payment gateway is mocked in this environment – all checkouts are treated as successful.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

