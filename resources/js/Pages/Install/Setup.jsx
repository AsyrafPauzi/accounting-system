import { Head, useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export default function Setup({ envWritable, licenseStatus }) {
    const { data, setData, post, processing, errors } = useForm({
        license_key: '',
        company_name: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('install.store'));
    };

    return (
        <div className="min-h-screen bg-cream text-ink py-12 px-4">
            <Head title="Set up BukuCloud" />
            <div className="max-w-2xl mx-auto">
                <div className="text-center mb-10">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">First-run setup</p>
                    <h1 className="font-display text-3xl lg:text-4xl font-medium tracking-tight mt-2">
                        Welcome to your BukuCloud install
                    </h1>
                    <p className="text-ink-muted text-sm mt-2">
                        Three things and you&apos;re live: paste your licence key, create the admin user, and we&apos;ll do the rest.
                    </p>
                </div>

                {!envWritable && (
                    <div className="mb-6 px-4 py-3 rounded-xl bg-terracotta/10 border border-terracotta/30 text-ink text-sm">
                        Your <code>.env</code> file isn&apos;t writable by the application user. Either chown it (recommended) or set
                        <code className="mx-1">APP_LICENSE_KEY</code> manually in <code>.env</code> before submitting this form.
                    </div>
                )}

                <form onSubmit={submit} className="bg-surface border border-border-warm rounded-3xl p-6 lg:p-8 space-y-6">
                    <section className="space-y-3">
                        <h2 className="font-display text-lg font-medium">1. Licence key</h2>
                        <p className="text-xs text-ink-muted">
                            The licence key in your purchase email. Starts with a long base64 string and ends with another. It&apos;ll be saved to <code>.env</code> as <code>APP_LICENSE_KEY</code>.
                        </p>
                        <textarea
                            id="license_key"
                            name="license_key"
                            rows={4}
                            value={data.license_key}
                            onChange={(e) => setData('license_key', e.target.value)}
                            className="block w-full rounded-xl border-border-warm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta font-mono text-xs"
                            placeholder="eyJsaWNlbnNlX2lkIjoi…"
                            required
                        />
                        <InputError message={errors.license_key} />
                        {licenseStatus?.status && licenseStatus.status !== 'missing' && licenseStatus.status !== 'unconfigured' && (
                            <p className="text-xs text-ink-muted">Current state: {licenseStatus.status}</p>
                        )}
                    </section>

                    <section className="space-y-3">
                        <h2 className="font-display text-lg font-medium">2. Company name (optional)</h2>
                        <TextInput
                            id="company_name"
                            name="company_name"
                            value={data.company_name}
                            onChange={(e) => setData('company_name', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Acme Sdn Bhd"
                        />
                        <InputError message={errors.company_name} />
                    </section>

                    <section className="space-y-4">
                        <h2 className="font-display text-lg font-medium">3. Admin user</h2>

                        <div>
                            <InputLabel htmlFor="admin_name" value="Full name" />
                            <TextInput
                                id="admin_name"
                                name="admin_name"
                                value={data.admin_name}
                                onChange={(e) => setData('admin_name', e.target.value)}
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={errors.admin_name} />
                        </div>

                        <div>
                            <InputLabel htmlFor="admin_email" value="Email" />
                            <TextInput
                                id="admin_email"
                                type="email"
                                name="admin_email"
                                value={data.admin_email}
                                onChange={(e) => setData('admin_email', e.target.value)}
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={errors.admin_email} />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel htmlFor="admin_password" value="Password" />
                                <TextInput
                                    id="admin_password"
                                    type="password"
                                    name="admin_password"
                                    value={data.admin_password}
                                    onChange={(e) => setData('admin_password', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                                <InputError message={errors.admin_password} />
                            </div>
                            <div>
                                <InputLabel htmlFor="admin_password_confirmation" value="Confirm password" />
                                <TextInput
                                    id="admin_password_confirmation"
                                    type="password"
                                    name="admin_password_confirmation"
                                    value={data.admin_password_confirmation}
                                    onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                            </div>
                        </div>
                    </section>

                    <div className="pt-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-3 rounded-2xl font-semibold text-sm bg-ink text-cream hover:bg-ink-muted disabled:opacity-50"
                        >
                            {processing ? 'Setting up…' : 'Activate this install'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
