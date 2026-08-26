import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

export default function Provisioning({ status: initialStatus, error: initialError }) {
    const [status, setStatus] = useState(initialStatus);
    const [error, setError] = useState(initialError);
    const intervalRef = useRef(null);

    useEffect(() => {
        if (status === 'ready' || status === 'failed') {
            return undefined;
        }

        const poll = async () => {
            try {
                const response = await axios.get(route('provisioning.status'));
                const nextStatus = response.data.status;
                setStatus(nextStatus);
                setError(response.data.error ?? null);

                if (nextStatus === 'ready' && response.data.redirect) {
                    clearInterval(intervalRef.current);
                    router.visit(response.data.redirect);
                }
            } catch {
                // Keep polling — transient network blips shouldn't strand the user.
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 2000);

        return () => clearInterval(intervalRef.current);
    }, [status]);

    const isFailed = status === 'failed';

    return (
        <GuestLayout>
            <Head title="Setting up your books" />

            <div className="mb-8 text-center">
                <h1 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">
                    {isFailed ? 'Setup encountered a problem' : 'Setting up your books…'}
                </h1>
                <p className="text-ink-muted text-sm font-medium mt-1">
                    {isFailed
                        ? 'We could not finish preparing your workspace.'
                        : 'This usually takes less than a minute.'}
                </p>
            </div>

            {!isFailed && (
                <div className="flex justify-center mb-6">
                    <div
                        className="h-10 w-10 rounded-full border-2 border-terracotta border-t-transparent animate-spin"
                        role="status"
                        aria-label="Loading"
                    />
                </div>
            )}

            {isFailed && error && (
                <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {error}
                </div>
            )}

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                {isFailed ? (
                    <Link
                        href={route('provisioning.retry')}
                        method="post"
                        as="button"
                        className="inline-flex w-full sm:w-auto justify-center"
                    >
                        <PrimaryButton className="w-full sm:w-auto justify-center py-3 rounded-xl font-semibold bg-terracotta hover:bg-terracotta shadow-lg border-0 uppercase tracking-normal text-sm">
                            Try again
                        </PrimaryButton>
                    </Link>
                ) : (
                    <p className="text-sm text-ink-muted text-center sm:text-left">
                        You can leave this tab open — we&apos;ll take you to your dashboard automatically.
                    </p>
                )}

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-center text-sm text-ink-muted hover:text-ink font-medium focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 rounded-lg"
                >
                    Log Out
                </Link>
            </div>
        </GuestLayout>
    );
}
