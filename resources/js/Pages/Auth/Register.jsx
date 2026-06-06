import { useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SpamBotFields from '@/Components/SpamBotFields';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register({ botGuard, privacyVersion }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        accept_privacy: false,
        privacy_version: privacyVersion ?? '',
        _hp_email: '',
        _hp_ts: botGuard?.ts ?? '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <div className="mb-6 text-center">
                <h1 className="font-display text-3xl lg:text-4xl font-medium text-ink tracking-tight">Create your account</h1>
                <p className="text-ink-muted text-sm mt-2">Books made for the way you actually work.</p>
            </div>

            <div className="mb-7">
                <p className="text-eyebrow font-semibold uppercase text-ink-muted text-center mb-2">
                    I'm signing up as
                </p>
                <div className="grid grid-cols-2 gap-2 bg-surface border border-border-warm rounded-2xl p-1">
                    <button
                        type="button"
                        className="px-4 py-2.5 rounded-xl text-sm font-semibold bg-terracotta text-white shadow-sm cursor-default"
                    >
                        A single business
                    </button>
                    <Link
                        href={route('register.practice.show')}
                        className="px-4 py-2.5 rounded-xl text-sm font-semibold text-ink-muted hover:text-ink hover:bg-cream/60 transition-colors text-center"
                    >
                        An accountancy firm
                    </Link>
                </div>
                <p className="text-ink-muted text-xs mt-2 text-center">
                    Run a firm with multiple clients? Switch to the accountant signup.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <SpamBotFields data={data} setData={setData} botGuard={botGuard} />

                <div>
                    <InputLabel htmlFor="name" value="Full Name" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="name"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email Address" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="pt-1">
                    <label className="flex items-start gap-2.5 text-sm text-ink cursor-pointer select-none">
                        <input
                            type="checkbox"
                            className="mt-0.5 rounded border-border-warm text-terracotta focus:ring-terracotta"
                            checked={data.accept_privacy}
                            onChange={(e) => setData('accept_privacy', e.target.checked)}
                        />
                        <span className="leading-snug">
                            I have read and accept the{' '}
                            <Link
                                href={route('privacy.show')}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light font-semibold"
                            >
                                privacy policy
                            </Link>
                            .
                        </span>
                    </label>
                    <InputError message={errors.accept_privacy} className="mt-2" />
                </div>

                <div className="pt-2">
                    <PrimaryButton
                        className="w-full justify-center py-3 rounded-xl"
                        disabled={processing || !data.accept_privacy}
                    >
                        Create account
                    </PrimaryButton>
                </div>

                <div className="text-center border-t border-border-warm pt-6">
                    <p className="text-ink-muted text-sm">
                        Already have an account?{' '}
                        <Link href={route('login')} className="text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light font-semibold">
                            Sign in
                        </Link>
                    </p>
                </div>
            </form>
        </GuestLayout>
    );
}