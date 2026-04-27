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
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">
                            Plan & Usage
                        </h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Manage your organization's subscription and monitor usage limits.
                        </p>
                    </div>
                    <Link
                        href={route('settings.company')}
                        className="text-sm font-semibold text-blue-600 hover:text-blue-700"
                    >
                        ← Company settings
                    </Link>
                </div>
            }
        >
            <Head title="Plan & Usage" />

            <div className="max-w-5xl space-y-8">
                {/* Plan Overview Card */}
                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-start justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                Current Plan
                            </h3>
                            <div className="flex items-center gap-3">
                                <h4 className="text-2xl font-bold text-slate-900">
                                    {plan ? plan.name : 'No Active Plan'}
                                </h4>
                                {subscription?.status === 'active' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 uppercase">
                                        Active
                                    </span>
                                )}
                            </div>
                            <p className="text-slate-500 text-sm mt-1">
                                {subscription?.interval === 'yearly' ? 'Billed yearly' : 'Billed monthly'} 
                                {subscription?.current_period_ends_at && ` • Renews on ${new Date(subscription.current_period_ends_at).toLocaleDateString()}`}
                            </p>
                        </div>
                        <Link
                            href={route('subscription.index')}
                            className="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-colors"
                        >
                            Change Plan
                        </Link>
                    </div>

                    <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t border-slate-100">
                        <div>
                            <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                                Monthly Price
                            </span>
                            <p className="text-lg font-bold text-slate-900">
                                RM{plan?.price_monthly || '0'}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                                Yearly Price
                            </span>
                            <p className="text-lg font-bold text-slate-900">
                                RM{plan?.price_yearly || '0'}
                            </p>
                        </div>
                        <div>
                            <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                                Extra User Price
                            </span>
                            <p className="text-lg font-bold text-slate-900 text-emerald-600">
                                {plan?.extra_user_price > 0 ? `RM${Number(plan.extra_user_price).toFixed(2)}/user` : 'N/A'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Usage Limits Card */}
                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6">
                        Usage Limits
                    </h3>

                    <div className="space-y-8">
                        {/* Users Usage */}
                        <div>
                            <div className="flex justify-between items-end mb-2">
                                <div>
                                    <h4 className="text-base font-bold text-slate-900">Users</h4>
                                    <p className="text-slate-500 text-sm">
                                        {userCount} of {plan?.users_included || 1} users included
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="text-2xl font-bold text-slate-900">{userCount}</span>
                                    <span className="text-slate-400 font-medium"> / {plan?.users_included || 1}</span>
                                </div>
                            </div>
                            <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div 
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        userCount > (plan?.users_included || 1) ? 'bg-rose-500' : 'bg-blue-600'
                                    }`}
                                    style={{ width: `${Math.min(100, (userCount / (plan?.users_included || 1)) * 100)}%` }}
                                />
                            </div>
                            {userCount > (plan?.users_included || 1) && isCorporate && (
                                <p className="mt-2 text-xs font-semibold text-rose-600 bg-rose-50 p-2 rounded-lg inline-block">
                                    You are using {userCount - plan.users_included} extra users. Each extra user costs RM{Number(plan.extra_user_price).toFixed(2)}/month.
                                </p>
                            )}
                            {userCount >= (plan?.users_included || 1) && !isCorporate && (
                                <p className="mt-2 text-xs font-semibold text-amber-600 bg-amber-50 p-2 rounded-lg inline-block">
                                    You have reached your user limit. Upgrade to Corporate to add more members.
                                </p>
                            )}
                        </div>

                        {/* Note on billing */}
                        <div className="p-4 bg-slate-50 rounded-xl border border-slate-200/50">
                            <h5 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Billing Note
                            </h5>
                            <p className="text-slate-600 text-xs leading-relaxed">
                                Extra user charges are applied automatically when a new team member is added beyond your plan's included limit. 
                                For Corporate plans, you will be prompted to pay the extra user fee upon creation.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Features List */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">
                            Plan Features
                        </h3>
                    </div>
                    <div className="p-6">
                        <ul className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {(plan?.features || []).map((feature, idx) => (
                                <li key={idx} className="flex items-center gap-3 text-sm text-slate-700">
                                    <svg className="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
