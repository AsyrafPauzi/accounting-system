import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SpamBotFields from '@/Components/SpamBotFields';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email, botGuard }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
        _hp_url: '',
        _hp_ts: botGuard?.ts ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <div className="mb-8 text-center">
                <h1 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Set New Password</h1>
                <p className="text-ink-muted text-sm font-medium mt-1">
                    Enter your new password below. You can sign in with it after saving.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <SpamBotFields data={data} setData={setData} botGuard={botGuard} />

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="New Password" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                    <TextInput
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl font-semibold bg-terracotta hover:bg-terracotta shadow-lg  border-0 uppercase tracking-normal text-sm" disabled={processing}>
                        Reset Password
                    </PrimaryButton>
                </div>

                <div className="text-center pt-2">
                    <Link href={route('login')} className="text-sm text-ink-muted hover:text-ink font-medium">
                        &larr; Back to login
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
