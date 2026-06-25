import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function InvalidVerificationLink({ isVerified = false, homeUrl = '/' }) {
    const { post, processing, recentlySuccessful } = useForm({});

    const resend = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verification link problem" />

            <div className="mb-8 text-center">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">
                    Email verification
                </p>
                <h1 className="mt-2 text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">
                    This link can&apos;t be used
                </h1>
                <p className="mt-2 text-sm font-medium text-ink-muted">
                    The verification link is expired, already used, or no longer matches your account.
                </p>
            </div>

            <div className="mb-6 rounded-xl bg-cream border border-border-warm p-4 text-sm leading-relaxed text-ink-muted">
                {isVerified
                    ? 'Your email is already verified. You can continue back to BukuCloud.'
                    : 'No worries. Send yourself a fresh verification email and use the newest link from your inbox.'}
            </div>

            {recentlySuccessful && (
                <div className="mb-4 rounded-xl bg-forest/10 border border-forest/30 p-3 text-sm font-medium text-forest">
                    A new verification email has been sent. Check your inbox and spam folder.
                </div>
            )}

            {isVerified ? (
                <Link
                    href={homeUrl}
                    className="inline-flex w-full items-center justify-center rounded-xl bg-terracotta px-4 py-3 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-terracotta-dark dark:hover:bg-terracotta-light"
                >
                    Continue to BukuCloud
                </Link>
            ) : (
                <form onSubmit={resend} className="space-y-4">
                    <PrimaryButton
                        className="w-full py-3 text-sm uppercase tracking-normal shadow-lg"
                        disabled={processing}
                    >
                        {processing ? 'Sending...' : 'Send a new verification email'}
                    </PrimaryButton>

                    <Link
                        href={homeUrl}
                        className="block text-center text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        Back to BukuCloud
                    </Link>
                </form>
            )}
        </GuestLayout>
    );
}
