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
                <h1 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Reset Password</h1>
                <p className="text-slate-500 text-sm font-medium mt-1">
                    No problem. Enter your email and we&apos;ll send you a link to choose a new one.
                </p>
            </div>

            {status && <div className="mb-4 font-medium text-sm text-emerald-600 text-center">{status}</div>}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email Address" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-xl border-slate-200 text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500"
                        isFocused={true}
                        placeholder="Enter your email address"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl font-semibold bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 border-0 uppercase tracking-normal text-sm" disabled={processing}>
                        Send Reset Link
                    </PrimaryButton>
                </div>

                <div className="text-center pt-2">
                    <Link href={route('login')} className="text-sm text-slate-500 hover:text-slate-700 font-medium">
                        &larr; Back to login
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}