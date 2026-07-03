import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

export default function Integrations({ auth, credentials = [] }) {
    const { flash } = usePage().props;
    const [confirmingId, setConfirmingId] = useState(null);
    const [issuedKey, setIssuedKey] = useState(null);
    const revoke = useForm({});
    const generate = useForm({});

    useEffect(() => {
        if (flash?.issued_api_key) {
            setIssuedKey(flash.issued_api_key);
        }
    }, [flash?.issued_api_key]);

    const onRevoke = (id) => {
        revoke.post(route('settings.integrations.revoke', id), {
            preserveScroll: true,
            onFinish: () => setConfirmingId(null),
        });
    };

    const onGenerate = () => {
        generate.post(route('settings.integrations.store'), {
            preserveScroll: true,
        });
    };

    const active = credentials.filter((c) => c.is_active);
    const inactive = credentials.filter((c) => !c.is_active);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="font-display text-2xl font-medium text-ink">API & Integrations</h2>
                        <p className="text-sm text-ink-muted mt-1">Generate an API key for external apps and integrations.</p>
                    </div>
                    <PrimaryButton onClick={onGenerate} disabled={generate.processing}>
                        {generate.processing ? 'Generating…' : 'Generate API key'}
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="API & Integrations" />

            <div className="py-6 max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                {issuedKey && (
                    <div className="rounded-2xl border border-terracotta/30 bg-terracotta/5 p-5 space-y-3">
                        <h3 className="font-medium text-ink">Copy your API key now</h3>
                        <p className="text-sm text-ink-muted">
                            Paste this into your app. BukuCloud will not show the full key again.
                        </p>
                        <div className="rounded-xl bg-white border border-border-warm px-4 py-3 font-mono text-sm break-all text-ink">
                            {issuedKey}
                        </div>
                        <div className="flex gap-2">
                            <SecondaryButton
                                type="button"
                                onClick={() => navigator.clipboard?.writeText(issuedKey)}
                            >
                                Copy
                            </SecondaryButton>
                            <SecondaryButton type="button" onClick={() => setIssuedKey(null)}>
                                Dismiss
                            </SecondaryButton>
                        </div>
                    </div>
                )}

                <div className="rounded-2xl border border-border-warm bg-cream/40 p-5 text-sm">
                    <h3 className="font-medium text-ink mb-2">How it works</h3>
                    <ol className="list-decimal list-inside text-ink-muted space-y-1">
                        <li>Click <strong>Generate API key</strong>.</li>
                        <li>Copy the key and paste it into your app.</li>
                        <li>Your app calls the BukuCloud API with <code className="text-xs">Authorization: Bearer &lt;api_key&gt;</code>.</li>
                        <li>Revoke the key here anytime to disconnect the app.</li>
                    </ol>
                </div>

                <section>
                    <h3 className="font-medium text-ink mb-3">Active API keys ({active.length})</h3>
                    {active.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border-warm p-6 text-center text-sm text-ink-muted">
                            No API keys yet. Generate one to connect an external app.
                        </div>
                    ) : (
                        <ul className="space-y-3">
                            {active.map((c) => (
                                <li key={c.id} className="rounded-2xl border border-border-warm p-4 space-y-3">
                                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                        <div className="space-y-1">
                                            <div className="font-medium text-ink">{c.partner_name}</div>
                                            <div className="text-xs text-ink-muted">
                                                Issued {c.issued_at?.slice(0, 10)}
                                                {c.issued_by && <> by {c.issued_by.name}</>}
                                            </div>
                                            {c.last_used_at && (
                                                <div className="text-xs text-ink-muted">
                                                    Last used {c.last_used_at.slice(0, 10)}
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            {confirmingId === c.id ? (
                                                <div className="flex gap-2">
                                                    <SecondaryButton onClick={() => setConfirmingId(null)} disabled={revoke.processing}>
                                                        Cancel
                                                    </SecondaryButton>
                                                    <button
                                                        type="button"
                                                        onClick={() => onRevoke(c.id)}
                                                        disabled={revoke.processing}
                                                        className="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                                    >
                                                        {revoke.processing ? 'Revoking…' : 'Confirm revoke'}
                                                    </button>
                                                </div>
                                            ) : (
                                                <SecondaryButton onClick={() => setConfirmingId(c.id)}>
                                                    Revoke
                                                </SecondaryButton>
                                            )}
                                        </div>
                                    </div>
                                    <div className="rounded-xl bg-cream/50 px-3 py-2 text-xs">
                                        <div className="text-ink-muted">API key</div>
                                        <div className="font-mono text-ink mt-0.5">{c.masked_api_key}</div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {inactive.length > 0 && (
                    <section>
                        <h3 className="font-medium text-ink mb-3">Revoked keys ({inactive.length})</h3>
                        <ul className="space-y-2">
                            {inactive.map((c) => (
                                <li key={c.id} className="rounded-xl border border-border-warm p-3 text-sm flex flex-wrap items-center gap-x-4 gap-y-1 opacity-70">
                                    <span className="font-medium text-ink">{c.partner_name}</span>
                                    <span className="font-mono text-xs text-ink-muted">{c.masked_api_key}</span>
                                    {c.revoked_at && (
                                        <span className="text-xs text-ink-muted">
                                            Revoked {c.revoked_at.slice(0, 10)}
                                            {c.revoked_by && <> by {c.revoked_by.name}</>}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
