import { useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge({ status }) {
    const [mode, setMode] = useState('totp'); // 'totp' | 'recovery'
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('auth.2fa.challenge.store'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Two-factor authentication" />

            <div className="mb-6 text-center">
                <h1 className="font-display text-2xl font-medium text-ink tracking-tight">
                    Confirm your identity
                </h1>
                <p className="text-ink-muted text-sm mt-2">
                    {mode === 'totp'
                        ? 'Enter the 6-digit code from your authenticator app.'
                        : 'Enter one of your recovery codes.'}
                </p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-forest">{status}</div>
            )}

            <form onSubmit={submit} className="space-y-5">
                {mode === 'totp' ? (
                    <div>
                        <InputLabel htmlFor="code" value="6-digit code" />
                        <TextInput
                            id="code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            maxLength={8}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            className="mt-1.5 block w-full rounded-xl text-center font-tabular text-lg tracking-widest"
                            required
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="recovery_code" value="Recovery code" />
                        <TextInput
                            id="recovery_code"
                            type="text"
                            autoComplete="one-time-code"
                            autoFocus
                            value={data.recovery_code}
                            onChange={(e) => setData('recovery_code', e.target.value)}
                            className="mt-1.5 block w-full rounded-xl font-tabular"
                            required
                        />
                        <InputError message={errors.recovery_code} className="mt-2" />
                    </div>
                )}

                <PrimaryButton className="w-full justify-center py-3 rounded-xl" disabled={processing}>
                    {processing ? 'Verifying…' : 'Continue'}
                </PrimaryButton>

                <div className="text-center">
                    <button
                        type="button"
                        onClick={() => { setMode(mode === 'totp' ? 'recovery' : 'totp'); reset('code', 'recovery_code'); }}
                        className="text-sm text-terracotta hover:text-terracotta-dark font-semibold"
                    >
                        {mode === 'totp' ? 'Use a recovery code instead' : 'Use the authenticator app'}
                    </button>
                </div>

                <div className="text-center text-sm border-t border-border-warm pt-4">
                    <Link href={route('login')} className="text-ink-muted hover:text-ink">
                        ← Back to sign in
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
