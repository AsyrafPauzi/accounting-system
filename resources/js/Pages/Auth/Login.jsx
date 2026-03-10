import { useEffect } from 'react';
import Checkbox from '@/Components/Checkbox';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-8 text-center">
                <h1 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Welcome Back</h1>
                <p className="text-slate-500 text-sm font-medium mt-1">Please enter your details to sign in</p>
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
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex justify-between items-center">
                        <InputLabel htmlFor="password" value="Password" />
                        {canResetPassword && (
                            <Link href={route('password.request')} className="text-sm text-blue-600 hover:text-blue-500 font-medium">
                                Forgot password?
                            </Link>
                        )}
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full rounded-xl border-slate-200 text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="block">
                    <label className="flex items-center">
                        <Checkbox name="remember" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                        <span className="ms-2 text-sm text-slate-600">Remember me</span>
                    </label>
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl font-semibold bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 border-0 uppercase tracking-normal text-sm" disabled={processing}>
                        Sign In
                    </PrimaryButton>
                </div>

                <div className="text-center border-t border-slate-100 pt-6">
                    <p className="text-slate-600 text-sm">
                        Don't have an account?{' '}
                        <Link href={route('register')} className="text-blue-600 hover:text-blue-500 font-bold">
                            Create a free account
                        </Link>
                    </p>
                </div>
            </form>
        </GuestLayout>
    );
}