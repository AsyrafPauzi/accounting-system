import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function SubscriptionSuccess({ auth, subscription = null }) {
    const planName = subscription?.plan?.name || 'Pro';
    const interval = subscription?.interval || 'monthly';
    const endsAt = subscription?.current_period_ends_at;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Subscription activated</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            Your plan is now active. You have full access to all modules.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Subscription Success" />

            <div className="max-w-xl space-y-6">
                <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-5 text-sm text-forest-dark">
                    <p className="font-semibold">
                        You&apos;re on <span className="underline">{planName}</span> ({interval}) plan.
                    </p>
                    {endsAt && (
                        <p className="mt-1">
                            Current period ends on{' '}
                            <span className="font-mono">
                                {new Date(endsAt).toLocaleDateString('en-MY', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </span>
                            .
                        </p>
                    )}
                </div>

                <div className="flex gap-3">
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg "
                    >
                        Go to dashboard
                    </Link>
                    <Link
                        href={route('subscription.index')}
                        className="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                    >
                        View subscription
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

