import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    UserCheck: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Currency: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ArrowUp: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7 7 7M12 3v18" /></svg>,
    ArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7-7-7M12 3v18" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

function StageCard({ label, value, subtitle, tone = 'slate' }) {
    const tones = {
        slate: 'border-slate-100 bg-slate-50/70 text-slate-700',
        amber: 'border-amber-100 bg-amber-50/70 text-amber-700',
        blue: 'border-blue-100 bg-blue-50/70 text-blue-700',
        emerald: 'border-emerald-100 bg-emerald-50/70 text-emerald-700',
    };
    const cls = tones[tone] || tones.slate;
    return (
        <div className={`rounded-xl border p-4 ${cls}`}>
            <p className="text-[10px] font-semibold uppercase tracking-wider opacity-80 mb-1">{label}</p>
            <p className="text-xl font-bold tabular-nums">{value}</p>
            <p className="text-xs opacity-80 mt-1">{subtitle}</p>
        </div>
    );
}

function MetricRow({ label, value, description, accent }) {
    const accentCls = accent === 'emerald' ? 'text-emerald-600' : accent === 'rose' ? 'text-rose-600' : 'text-slate-900';
    return (
        <div className="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <div>
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{label}</p>
                <p className="text-xs text-slate-400 mt-0.5">{description}</p>
            </div>
            <p className={`text-sm font-bold tabular-nums ${accentCls}`}>{value}</p>
        </div>
    );
}

export default function Dashboard({ auth, stats = {} }) {
    const customers = stats.customers || { total: 0, active: 0, outstanding: 0 };
    const invoices = stats.invoices || {
        total: 0,
        draft: 0,
        unpaid: 0,
        partially_paid: 0,
        paid: 0,
        void: 0,
        total_invoiced: 0,
        total_collected: 0,
        total_outstanding: 0,
    };
    const creditNotes = stats.credit_notes || { count: 0, value: 0 };

    const collectionRate =
        invoices.total_invoiced > 0
            ? (invoices.total_collected / invoices.total_invoiced) * 100
            : 0;

    const riskCustomers =
        customers.total > 0
            ? Math.round(((customers.total - customers.active) / customers.total) * 100)
            : 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-2">
                    <h2 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
                        Welcome back, <span className="text-blue-600">{auth.user?.name}</span>
                    </h2>
                    <p className="text-slate-500 text-sm font-medium">
                        A real-time overview of your receivables, customer health, and credit notes.
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-8">
                {/* Top KPI row */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-80">
                                Total Invoiced
                            </span>
                            <span className="p-2 rounded-xl bg-white/10">
                                <Icons.Currency />
                            </span>
                        </div>
                        <p className="text-2xl font-bold font-mono tabular-nums">
                            RM {parseFloat(invoices.total_invoiced).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="mt-2 text-xs text-blue-100">
                            Collected:&nbsp;
                            <span className="font-semibold">
                                RM {parseFloat(invoices.total_collected).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                            </span>
                        </p>
                    </div>

                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col justify-between">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
                                AR Outstanding
                            </span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600">
                                <Icons.Exclamation />
                            </span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">
                            RM {parseFloat(invoices.total_outstanding).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="mt-2 text-xs text-slate-500">
                            {invoices.unpaid + invoices.partially_paid} invoices unpaid or partially paid
                        </p>
                    </div>

                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col justify-between">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
                                Collection Rate
                            </span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                                <Icons.Check />
                            </span>
                        </div>
                        <div className="flex items-end justify-between">
                            <p className="text-2xl font-bold text-emerald-600">
                                {collectionRate.toFixed(0)}
                                <span className="text-sm font-semibold">%</span>
                            </p>
                            <span className="inline-flex items-center gap-1 text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                                <Icons.ArrowUp /> Healthy
                            </span>
                        </div>
                        <p className="mt-2 text-xs text-slate-500">Of all issued invoices collected so far</p>
                    </div>

                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col justify-between">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
                                Customers
                            </span>
                            <span className="p-2 rounded-xl bg-slate-100 text-slate-600">
                                <Icons.Users />
                            </span>
                        </div>
                        <p className="text-xl font-bold text-slate-900">
                            {customers.total}
                            <span className="ml-2 text-xs text-slate-500 font-normal">
                                ({customers.active} active)
                            </span>
                        </p>
                        <p className="mt-2 text-xs text-slate-500">
                            {riskCustomers}% inactive or on hold
                        </p>
                    </div>
                </div>

                {/* Middle grid: Receivables + Customer analytics */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Receivables funnel */}
                    <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                                    Receivables pipeline
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    From draft to cash – where your invoices sit today.
                                </p>
                            </div>
                            <Link
                                href={route('invoices.index')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                View invoices <Icons.ChevronRight />
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                            <StageCard
                                label="Draft"
                                value={invoices.draft}
                                subtitle="Not yet sent"
                                tone="slate"
                            />
                            <StageCard
                                label="Unpaid"
                                value={invoices.unpaid}
                                subtitle="Awaiting payment"
                                tone="amber"
                            />
                            <StageCard
                                label="Partially paid"
                                value={invoices.partially_paid}
                                subtitle="Some cash collected"
                                tone="blue"
                            />
                            <StageCard
                                label="Paid"
                                value={invoices.paid}
                                subtitle="Fully settled"
                                tone="emerald"
                            />
                        </div>
                    </div>

                    {/* Customer analytics side card */}
                    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                                    Customer analytics
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    High-level view of your customer base.
                                </p>
                            </div>
                            <Link
                                href={route('customers.index')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                View customers <Icons.ChevronRight />
                            </Link>
                        </div>

                        <div className="space-y-3">
                            <MetricRow
                                label="Total customers"
                                value={customers.total}
                                description="Accounts created in this tenant"
                            />
                            <MetricRow
                                label="Active customers"
                                value={customers.active}
                                description="Eligible for new invoices"
                                accent="emerald"
                            />
                            <MetricRow
                                label="AR outstanding"
                                value={
                                    'RM ' +
                                    parseFloat(customers.outstanding).toLocaleString('en-MY', {
                                        minimumFractionDigits: 2,
                                    })
                                }
                                description="Still to be collected"
                                accent="rose"
                            />
                        </div>
                    </div>
                </div>

                {/* Bottom grid: Credit notes + activity placeholder */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Credit notes */}
                    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 lg:col-span-1">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                                    Credit notes
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    Adjustments and reversals issued to customers.
                                </p>
                            </div>
                            <Link
                                href={route('credit-notes.index')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                View credit notes <Icons.ChevronRight />
                            </Link>
                        </div>

                        <div className="grid grid-cols-2 gap-4 mt-2">
                            <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    Issued
                                </p>
                                <p className="text-xl font-bold text-slate-900">{creditNotes.count}</p>
                                <p className="mt-1 text-xs text-slate-500">Total credit notes</p>
                            </div>
                            <div className="rounded-xl border border-rose-100 bg-rose-50/70 p-4">
                                <p className="text-[10px] font-semibold text-rose-500 uppercase tracking-wider mb-1">
                                    Total credited
                                </p>
                                <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">
                                    RM{' '}
                                    {parseFloat(creditNotes.value).toLocaleString('en-MY', {
                                        minimumFractionDigits: 2,
                                    })}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">Revenue reversed</p>
                            </div>
                        </div>
                    </div>

                    {/* Activity / roadmap placeholder */}
                    <div className="bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm p-6 lg:col-span-2 flex flex-col justify-between">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                                    Coming soon
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    Purchases, AP aging, and richer financial reports will land here.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                <Icons.Document />
                            </div>
                            <div>
                                <p className="text-slate-700 font-medium">
                                    You’re on the enterprise-ready path.
                                </p>
                                <p className="text-slate-400 text-xs mt-1">
                                    As you onboard more customers and invoices, this dashboard will evolve with
                                    more deep-dive analytics.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
