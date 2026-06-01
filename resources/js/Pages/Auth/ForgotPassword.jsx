import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm, Link } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-8 text-center">
                <h1 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Reset Password</h1>
                <p className="text-ink-muted text-sm font-medium mt-1">
                    No problem. Enter your email and we&apos;ll send you a link to choose a new one.
                </p>
            </div>

            {status && <div className="mb-4 font-medium text-sm text-forest text-center">{status}</div>}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email Address" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                        isFocused={true}
                        placeholder="Enter your email address"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl font-semibold bg-terracotta hover:bg-terracotta shadow-lg  border-0 uppercase tracking-normal text-sm" disabled={processing}>
                        Send Reset Link
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