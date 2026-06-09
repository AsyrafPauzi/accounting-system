import { useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SpamBotFields from '@/Components/SpamBotFields';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Firm signup form.
 *
 * Deliberately *not* a plan picker — the controller defaults every new
 * firm to the free Practice tier so they can land in the console
 * immediately. They upgrade from /practice/plan once they're inside.
 * That keeps this form short (8 fields) and removes the "I have to
 * decide on pricing before I've seen anything" friction.
 */
export default function RegisterPractice({ botGuard, privacyVersion }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        firm_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        accept_privacy: false,
        privacy_version: privacyVersion ?? '',
        _hp_url: '',
        _hp_ts: botGuard?.ts ?? '',
    });

    useEffect(() => () => reset('password', 'password_confirmation'), []);

    const submit = (e) => {
        e.preventDefault();
        post(route('register.practice'));
    };

    return (
        <GuestLayout>
            <Head title="Register your practice" />

            <div className="mb-6 text-center">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">For accountancy firms</p>
                <h1 className="font-display text-3xl lg:text-4xl font-medium text-ink tracking-tight mt-2">
                    Run your whole practice in one place
                </h1>
                <p className="text-ink-muted text-sm mt-2">
                    Free for your first client. Pick a paid plan whenever you're ready.
                </p>
            </div>

            <div className="mb-7">
                <p className="text-eyebrow font-semibold uppercase text-ink-muted text-center mb-2">
                    I'm signing up as
                </p>
                <div className="grid grid-cols-2 gap-2 bg-surface border border-border-warm rounded-2xl p-1">
                    <Link
                        href={route('register')}
                        className="px-4 py-2.5 rounded-xl text-sm font-semibold text-ink-muted hover:text-ink hover:bg-cream/60 transition-colors text-center"
                    >
                        A single business
                    </Link>
                    <button
                        type="button"
                        className="px-4 py-2.5 rounded-xl text-sm font-semibold bg-terracotta text-white shadow-sm cursor-default"
                    >
                        An accountancy firm
                    </button>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <SpamBotFields data={data} setData={setData} botGuard={botGuard} />

                <div>
                    <InputLabel htmlFor="firm_name" value="Firm name" />
                    <TextInput
                        id="firm_name"
                        name="firm_name"
                        value={data.firm_name}
                        onChange={(e) => setData('firm_name', e.target.value)}
                        placeholder="Acme Tax & Audit Sdn Bhd"
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="organization"
                        isFocused
                        required
                    />
                    <InputError message={errors.firm_name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Your full name" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="name"
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="email"
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
                        onChange={(e) => setData('password', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        required
                    />
                </div>

                <label className="flex items-start gap-2.5 text-sm text-ink cursor-pointer select-none pt-1">
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
                <InputError message={errors.accept_privacy} className="mt-1" />

                <PrimaryButton
                    className="w-full justify-center py-3 rounded-xl"
                    disabled={processing || !data.accept_privacy}
                >
                    Create firm account
                </PrimaryButton>

                <div className="text-center border-t border-border-warm pt-5">
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
