import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="mb-8 text-center">
                <h1 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Verify your email</h1>
                <p className="text-ink-muted text-sm font-medium mt-1">Check your inbox for the verification link</p>
            </div>

            <div className="mb-6 text-sm text-ink leading-relaxed">
                Thanks for signing up! Before getting started, could you verify
                your email address by clicking on the link we just emailed to
                you? If you didn&apos;t receive the email, we will gladly send you
                another.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-forest">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <PrimaryButton className="w-full sm:w-auto justify-center py-3 rounded-xl font-semibold bg-terracotta hover:bg-terracotta shadow-lg  border-0 uppercase tracking-normal text-sm" disabled={processing}>
                        Resend Verification Email
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-center text-sm text-ink-muted hover:text-ink font-medium focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 rounded-lg"
                    >
                        Log Out
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
