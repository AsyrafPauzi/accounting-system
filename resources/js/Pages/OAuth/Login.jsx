import { useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SpamBotFields from '@/Components/SpamBotFields';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Custom branded login page for the "Connect to BukuCloud" handshake.
 *
 * Why a separate page from /login:
 *   - The user got here from a partner (Fin Persona, etc.) — we want
 *     to show "BukuCloud is being asked by Fin Persona to grant access"
 *     so they understand why they're being asked to log in mid-flow.
 *   - Success path is /oauth/consent, not /dashboard.
 *
 * Backend (App\Http\Controllers\OAuth\LoginController) seeds
 * `url.intended` to /oauth/consent before authenticating, so the
 * regular 2FA challenge controller will route the user back here
 * after a successful TOTP / recovery code without us forking it.
 */
export default function OAuthLogin({ partner, status, canResetPassword, botGuard }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
        _hp_url: '',
        _hp_ts: botGuard?.ts ?? '',
    });

    useEffect(() => () => reset('password'), []);

    const submit = (e) => {
        e.preventDefault();
        post(route('oauth.login.store'));
    };

    return (
        <GuestLayout>
            <Head title={`Sign in to authorize ${partner?.name ?? 'integration'}`} />

            <div className="mb-6 rounded-2xl border border-border-warm bg-cream/50 p-4 text-center">
                <p className="text-xs uppercase tracking-wider text-ink-muted">Connect to BukuCloud</p>
                <h1 className="mt-1 font-display text-2xl font-medium text-ink">
                    {partner?.name ?? 'A partner application'} is requesting access
                </h1>
                <p className="mt-2 text-sm text-ink-muted">
                    Sign in to your BukuCloud account to review what they're asking for. Nothing is shared until you click <strong>Authorize</strong>.
                </p>
            </div>

            {status && <div className="mb-4 font-medium text-sm text-forest text-center">{status}</div>}

            <form onSubmit={submit} className="space-y-5">
                <SpamBotFields data={data} setData={setData} botGuard={botGuard} />

                <div>
                    <InputLabel htmlFor="email" value="Email Address" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="username"
                        isFocused
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex justify-between items-center">
                        <InputLabel htmlFor="password" value="Password" />
                        {canResetPassword && (
                            <Link href={route('password.request')} className="text-sm text-terracotta hover:text-terracotta font-medium">
                                Forgot password?
                            </Link>
                        )}
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl" disabled={processing}>
                        Sign in to continue
                    </PrimaryButton>
                </div>

                <div className="text-center border-t border-border-warm pt-6 space-y-2">
                    <p className="text-ink-muted text-xs">
                        BukuCloud will only share data after you explicitly authorize {partner?.name ?? 'this app'} on the next screen.
                    </p>
                    <p className="text-ink-muted text-sm">
                        Don't have an account?{' '}
                        <Link href={route('register')} className="text-terracotta hover:text-terracotta-dark font-semibold">
                            Create one
                        </Link>
                    </p>
                </div>
            </form>
        </GuestLayout>
    );
}
