import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactor({
    auth,
    isEnabled,
    hasPending,
    pendingSecret,
    qrCode,
    recoveryCodes,
    enabledAt,
}) {
    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const regenForm = useForm({ password: '' });

    const [showRegen, setShowRegen] = useState(false);
    const [showDisable, setShowDisable] = useState(false);

    const startEnrolment = (e) => {
        e.preventDefault();
        enableForm.post(route('settings.2fa.enable'));
    };

    const confirmEnrolment = (e) => {
        e.preventDefault();
        confirmForm.post(route('settings.2fa.confirm'), {
            preserveScroll: true,
            onFinish: () => confirmForm.reset('code'),
        });
    };

    const disable2fa = (e) => {
        e.preventDefault();
        disableForm.post(route('settings.2fa.disable'), {
            preserveScroll: true,
            onFinish: () => disableForm.reset('password'),
        });
    };

    const regenerateCodes = (e) => {
        e.preventDefault();
        regenForm.post(route('settings.2fa.recovery_codes'), {
            preserveScroll: true,
            onFinish: () => { regenForm.reset('password'); setShowRegen(false); },
        });
    };

    const cancelPending = () => {
        if (!confirm('Cancel the in-progress 2FA setup and start over?')) return;
        router.post(route('settings.2fa.cancel_pending'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Security</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        Two-factor authentication
                    </h1>
                    <p className="text-ink-muted text-sm">
                        Add a second step to sign-in using an authenticator app on your phone.
                    </p>
                </div>
            }
        >
            <Head title="Two-factor authentication" />

            <div className="space-y-6 max-w-2xl">
                {recoveryCodes && recoveryCodes.length > 0 && (
                    <RecoveryCodesPanel codes={recoveryCodes} />
                )}

                {!isEnabled && !hasPending && (
                    <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8">
                        <h2 className="font-display text-lg font-medium text-ink mb-2">2FA is currently off</h2>
                        <p className="text-ink-muted text-sm">
                            Once enabled, you'll need to type a 6-digit code from an authenticator app (Google Authenticator, 1Password, Authy, …) every time you sign in.
                        </p>
                        <form onSubmit={startEnrolment} className="mt-5">
                            <button
                                type="submit"
                                disabled={enableForm.processing}
                                className="px-6 py-3 rounded-2xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted transition-colors"
                            >
                                {enableForm.processing ? 'Generating…' : 'Set up 2FA'}
                            </button>
                        </form>
                    </section>
                )}

                {hasPending && !isEnabled && (
                    <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8 space-y-5">
                        <div>
                            <h2 className="font-display text-lg font-medium text-ink mb-2">Step 1 — Scan this with your authenticator app</h2>
                            <p className="text-ink-muted text-sm mb-4">
                                Open Google Authenticator, 1Password, Authy, or your password manager's TOTP feature, then scan the QR code.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-6 items-start">
                                {qrCode && (
                                    <div className="bg-white p-3 rounded-xl border border-border-warm" dangerouslySetInnerHTML={{ __html: qrCode }} />
                                )}
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs uppercase tracking-wide text-ink-muted">Or enter this secret manually</p>
                                    <code className="mt-1.5 block break-all bg-cream border border-border-warm rounded-lg px-3 py-2 text-sm font-tabular">
                                        {pendingSecret}
                                    </code>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={confirmEnrolment} className="border-t border-border-warm pt-5 space-y-4">
                            <h3 className="font-display text-lg font-medium text-ink">Step 2 — Confirm with a code</h3>
                            <div>
                                <InputLabel htmlFor="code" value="6-digit code from your app" />
                                <TextInput
                                    id="code"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    autoFocus
                                    maxLength={8}
                                    value={confirmForm.data.code}
                                    onChange={(e) => confirmForm.setData('code', e.target.value)}
                                    className="mt-1.5 block w-full sm:w-48 rounded-xl text-center font-tabular text-lg tracking-widest"
                                />
                                <InputError message={confirmForm.errors.code} className="mt-2" />
                            </div>
                            <div className="flex gap-3">
                                <button
                                    type="submit"
                                    disabled={confirmForm.processing || !confirmForm.data.code}
                                    className="px-6 py-3 rounded-2xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted transition-colors disabled:opacity-50"
                                >
                                    {confirmForm.processing ? 'Verifying…' : 'Verify and turn on'}
                                </button>
                                <button
                                    type="button"
                                    onClick={cancelPending}
                                    className="px-6 py-3 rounded-2xl text-sm font-semibold text-ink hover:bg-ink/5 transition-colors"
                                >
                                    Start over
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                {isEnabled && (
                    <>
                        <section className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-5 text-sm text-forest-dark dark:text-forest-light">
                            <p className="font-semibold">2FA is on for your account.</p>
                            {enabledAt && (
                                <p className="text-ink-muted mt-0.5">
                                    Enabled {new Date(enabledAt).toLocaleString()}
                                </p>
                            )}
                        </section>

                        <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8">
                            <h2 className="font-display text-lg font-medium text-ink mb-3">Recovery codes</h2>
                            <p className="text-ink-muted text-sm mb-4">
                                If you lose your phone, use one of your recovery codes to sign in. Each code only works once.
                                Regenerating cancels every existing code.
                            </p>
                            {!showRegen ? (
                                <button
                                    type="button"
                                    onClick={() => setShowRegen(true)}
                                    className="px-5 py-2 rounded-xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted transition-colors"
                                >
                                    Regenerate recovery codes
                                </button>
                            ) : (
                                <form onSubmit={regenerateCodes} className="space-y-4">
                                    <div>
                                        <InputLabel htmlFor="regen_password" value="Confirm with your password" />
                                        <TextInput
                                            id="regen_password"
                                            type="password"
                                            autoComplete="current-password"
                                            value={regenForm.data.password}
                                            onChange={(e) => regenForm.setData('password', e.target.value)}
                                            className="mt-1.5 block w-full rounded-xl"
                                            required
                                        />
                                        <InputError message={regenForm.errors.password} className="mt-2" />
                                    </div>
                                    <div className="flex gap-3">
                                        <button
                                            type="submit"
                                            disabled={regenForm.processing || !regenForm.data.password}
                                            className="px-5 py-2 rounded-xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted transition-colors disabled:opacity-50"
                                        >
                                            {regenForm.processing ? 'Generating…' : 'Generate new codes'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setShowRegen(false); regenForm.reset('password'); }}
                                            className="px-5 py-2 text-sm font-semibold text-ink hover:bg-ink/5 rounded-xl transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            )}
                        </section>

                        <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8">
                            <h2 className="font-display text-lg font-medium text-ink mb-3">Turn off 2FA</h2>
                            <p className="text-ink-muted text-sm mb-4">
                                Disabling will remove 2FA from your account. You'll no longer need a code at sign-in.
                            </p>
                            {!showDisable ? (
                                <button
                                    type="button"
                                    onClick={() => setShowDisable(true)}
                                    className="px-5 py-2 rounded-xl border border-terracotta text-terracotta text-sm font-semibold hover:bg-terracotta hover:text-white transition-colors"
                                >
                                    Turn off 2FA
                                </button>
                            ) : (
                                <form onSubmit={disable2fa} className="space-y-4">
                                    <div>
                                        <InputLabel htmlFor="disable_password" value="Confirm with your password" />
                                        <TextInput
                                            id="disable_password"
                                            type="password"
                                            autoComplete="current-password"
                                            value={disableForm.data.password}
                                            onChange={(e) => disableForm.setData('password', e.target.value)}
                                            className="mt-1.5 block w-full rounded-xl"
                                            required
                                        />
                                        <InputError message={disableForm.errors.password} className="mt-2" />
                                    </div>
                                    <div className="flex gap-3">
                                        <button
                                            type="submit"
                                            disabled={disableForm.processing || !disableForm.data.password}
                                            className="px-5 py-2 rounded-xl bg-terracotta text-white text-sm font-semibold hover:bg-terracotta-dark transition-colors disabled:opacity-50"
                                        >
                                            {disableForm.processing ? 'Disabling…' : 'Yes, turn off 2FA'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setShowDisable(false); disableForm.reset('password'); }}
                                            className="px-5 py-2 text-sm font-semibold text-ink hover:bg-ink/5 rounded-xl transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            )}
                        </section>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function RecoveryCodesPanel({ codes }) {
    return (
        <section className="bg-mustard/15 border border-mustard/40 rounded-2xl px-6 py-5">
            <p className="font-semibold text-ink">Save these recovery codes somewhere safe.</p>
            <p className="text-sm text-ink-muted mt-1">
                Each code only works once. We won't show them again — copy them into your password manager or print this page now.
            </p>
            <div className="mt-4 grid grid-cols-2 gap-2">
                {codes.map((code) => (
                    <code key={code} className="block bg-cream rounded-lg px-3 py-2 text-sm font-tabular text-center select-all">
                        {code}
                    </code>
                ))}
            </div>
        </section>
    );
}
