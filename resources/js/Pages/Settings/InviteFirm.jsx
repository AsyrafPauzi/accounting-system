import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm } from '@inertiajs/react';

export default function InviteFirm({ currentFirm, pending, incoming = [], flash }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const [confirmRevokeId, setConfirmRevokeId] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('settings.invite-firm.store'), {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    const revoke = (id) => {
        // Issuing a DELETE on a queryless URL — the form prop carries
        // no body since the id is in the path.
        useForm({}).delete(route('settings.invite-firm.destroy', id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Invite my accountant</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Give a firm access to manage your books</p>
                </div>
            }
        >
            <Head title="Invite my accountant" />

            <div className="max-w-3xl space-y-6">
                {incoming?.length > 0 && (
                    <div className="bg-mustard/15 border-2 border-mustard/50 rounded-2xl p-5">
                        <p className="text-eyebrow font-semibold uppercase text-mustard-dark dark:text-mustard">
                            You have a pending invitation
                        </p>
                        <p className="text-sm text-ink mt-2">
                            An accountancy firm has asked to manage your books. Accept to give them access, or decline if you don't recognise them.
                        </p>
                        <ul className="mt-4 space-y-3">
                            {incoming.map((inv) => (
                                <li
                                    key={inv.id}
                                    className="bg-surface border border-border-warm rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                                >
                                    <div>
                                        <p className="font-semibold text-ink">{inv.firm_name}</p>
                                        <p className="text-xs text-ink-muted">
                                            {inv.permission_level} access · sent{' '}
                                            {new Date(inv.created_at).toLocaleDateString()} · expires{' '}
                                            {new Date(inv.expires_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex gap-2 shrink-0">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(route('settings.invite-firm.accept', inv.id), {}, {
                                                    preserveScroll: true,
                                                })
                                            }
                                            className="px-4 py-2 rounded-xl bg-forest text-cream font-semibold text-sm hover:bg-forest-dark"
                                        >
                                            Accept
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (!window.confirm('Decline this invitation?')) return;
                                                router.post(route('settings.invite-firm.decline', inv.id), {}, {
                                                    preserveScroll: true,
                                                });
                                            }}
                                            className="px-4 py-2 rounded-xl border border-border-warm text-ink-muted hover:text-ink font-semibold text-sm"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {currentFirm && (
                    <div className="bg-surface border border-border-warm rounded-2xl p-5">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Currently managed by</p>
                        <p className="font-display text-lg font-medium text-ink mt-1">{currentFirm.name}</p>
                        <p className="text-xs text-ink-muted mt-1">
                            {currentFirm.permission_level} access · linked{' '}
                            {currentFirm.linked_at ? new Date(currentFirm.linked_at).toLocaleDateString() : '—'}
                        </p>
                    </div>
                )}

                {!currentFirm && (
                    <div className="bg-surface border border-border-warm rounded-2xl p-5">
                        <p className="text-ink text-sm">
                            Hand off your books to your accountancy firm in two clicks. They&apos;ll get a link they can accept from inside their Practice console — no need to share your password.
                        </p>

                        <form onSubmit={submit} className="mt-4 flex flex-col sm:flex-row gap-3">
                            <div className="flex-1">
                                <InputLabel htmlFor="email" value="Firm contact email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="firm@example.com"
                                    className="mt-1 block w-full"
                                    required
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>
                            <div className="sm:self-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto px-5 py-2.5 rounded-2xl font-semibold text-sm bg-ink text-cream hover:bg-ink-muted disabled:opacity-50"
                                >
                                    Send invite
                                </button>
                            </div>
                        </form>

                        {flash?.success && (
                            <div className="mt-4 text-xs text-ink bg-mustard/15 border border-mustard/40 rounded-lg p-3 break-all">
                                {flash.success}
                            </div>
                        )}
                    </div>
                )}

                {pending?.length > 0 && (
                    <div className="bg-surface border border-border-warm rounded-2xl">
                        <div className="px-5 py-4 border-b border-border-warm">
                            <p className="font-display text-base font-medium text-ink">Pending invites</p>
                        </div>
                        <ul className="divide-y divide-border-warm">
                            {pending.map((p) => (
                                <li key={p.id} className="px-5 py-4 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm text-ink">{p.email}</p>
                                        <p className="text-xs text-ink-muted">
                                            sent {new Date(p.created_at).toLocaleDateString()} · expires {new Date(p.expires_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    {confirmRevokeId === p.id ? (
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => revoke(p.id)}
                                                className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-terracotta text-cream hover:bg-terracotta-dark"
                                            >
                                                Confirm revoke
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setConfirmRevokeId(null)}
                                                className="px-3 py-1.5 rounded-lg text-xs font-semibold text-ink-muted hover:text-ink"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setConfirmRevokeId(p.id)}
                                            className="text-xs font-semibold text-terracotta hover:text-terracotta-dark"
                                        >
                                            Revoke
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
