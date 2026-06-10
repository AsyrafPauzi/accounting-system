import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Settings → API & Integrations.
 *
 * Shows every OAuth-issued credential (active and revoked) for the
 * tenant. The plaintext API key + signing key are NEVER shown here —
 * they only ever appear in the partner's server-side response from
 * /api/oauth/token. We only show last4 + masked dots, plus issue/
 * revoke audit trails.
 */
export default function Integrations({ auth, credentials = [], available_partners = [] }) {
    const [confirmingId, setConfirmingId] = useState(null);
    const revoke = useForm({});

    const onRevoke = (id) => {
        revoke.post(route('settings.integrations.revoke', id), {
            preserveScroll: true,
            onFinish: () => setConfirmingId(null),
        });
    };

    const active = credentials.filter((c) => c.is_active);
    const inactive = credentials.filter((c) => !c.is_active);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="font-display text-2xl font-medium text-ink">API & Integrations</h2>
                    <p className="text-sm text-ink-muted mt-1">Manage external apps connected to your BukuCloud account.</p>
                </div>
            }
        >
            <Head title="API & Integrations" />

            <div className="py-6 max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                {/* How it works */}
                <div className="rounded-2xl border border-border-warm bg-cream/40 p-5 text-sm">
                    <h3 className="font-medium text-ink mb-2">How API access works</h3>
                    <ol className="list-decimal list-inside text-ink-muted space-y-1">
                        <li>Open the partner app (e.g. Fin Persona) and click "Connect to BukuCloud".</li>
                        <li>Sign in to BukuCloud and review the data they're requesting.</li>
                        <li>Click <strong>Authorize</strong> — the partner receives an API key + signing key automatically.</li>
                        <li>Manage or revoke their access from this page anytime.</li>
                    </ol>
                    <p className="text-ink-muted mt-3 text-xs">
                        BukuCloud never shows the full API key after issuance — even to you. If the partner loses theirs, they must re-authorize.
                    </p>
                </div>

                {/* Active credentials */}
                <section>
                    <h3 className="font-medium text-ink mb-3">Active connections ({active.length})</h3>
                    {active.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border-warm p-6 text-center text-sm text-ink-muted">
                            No active integrations yet.
                            {available_partners.length > 0 && (
                                <div className="mt-3 text-xs">
                                    Available partners:
                                    <ul className="mt-2 space-y-1">
                                        {available_partners.map((p) => (
                                            <li key={p.id} className="font-medium text-ink">{p.name}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
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
                                    <div className="grid sm:grid-cols-2 gap-3 text-xs">
                                        <div className="rounded-xl bg-cream/50 px-3 py-2">
                                            <div className="text-ink-muted">API key</div>
                                            <div className="font-mono text-ink mt-0.5">{c.masked_api_key}</div>
                                        </div>
                                        <div className="rounded-xl bg-cream/50 px-3 py-2">
                                            <div className="text-ink-muted">Signing key</div>
                                            <div className="font-mono text-ink mt-0.5">{c.masked_signing}</div>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Revoked / expired */}
                {inactive.length > 0 && (
                    <section>
                        <h3 className="font-medium text-ink mb-3">Past connections ({inactive.length})</h3>
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
