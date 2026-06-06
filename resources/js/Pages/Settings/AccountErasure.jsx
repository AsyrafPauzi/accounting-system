import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';

const formatDateTime = (iso) => {
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
};

export default function AccountErasure({
    auth,
    isScheduled,
    requestedAt,
    scheduledDeletionAt,
    coolingOffDays,
    dpoEmail,
    firmGuard = { is_firm_owner: false, active_client_count: 0, blocked: false, practice_dashboard_url: '/practice' },
}) {
    const [showConfirm, setShowConfirm] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        confirm: false,
    });

    const submitErase = (e) => {
        e.preventDefault();
        post(route('settings.account_erase.request'), {
            preserveScroll: true,
            onFinish: () => reset('password'),
        });
    };

    const cancelScheduled = () => {
        if (!window.confirm('Cancel the scheduled deletion and keep your account active?')) return;
        router.post(route('settings.account_erase.cancel'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Privacy</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        Delete my account
                    </h1>
                    <p className="text-ink-muted text-sm">
                        Permanently remove your account and your organisation's books from BukuCloud.
                    </p>
                </div>
            }
        >
            <Head title="Delete my account" />

            <div className="space-y-8 max-w-3xl">
                {isScheduled && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-5 text-sm">
                        <p className="font-semibold text-terracotta-dark dark:text-terracotta-light">
                            Your account is scheduled for deletion on {formatDateTime(scheduledDeletionAt)}.
                        </p>
                        <p className="mt-1 text-ink">
                            You requested deletion on {formatDateTime(requestedAt)}. We're keeping your account active until then so you can change your mind, export a copy, or contact support.
                        </p>
                        <button
                            type="button"
                            onClick={cancelScheduled}
                            className="mt-4 px-5 py-2 rounded-xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted transition-colors"
                        >
                            Cancel scheduled deletion
                        </button>
                    </div>
                )}

                {/* Firm-owner guard. Account deletion is blocked until
                    every linked client has been unlinked, otherwise the
                    firm is left orphaned. */}
                {!isScheduled && firmGuard.blocked && (
                    <div className="bg-mustard/15 border border-mustard/40 rounded-2xl px-6 py-5 text-sm">
                        <p className="font-semibold text-ink">
                            You can&apos;t delete your account yet — your firm still manages
                            {' '}{firmGuard.active_client_count}{' '}
                            active client{firmGuard.active_client_count === 1 ? '' : 's'}.
                        </p>
                        <p className="mt-1 text-ink-muted">
                            Unlinking keeps each client&apos;s books and users intact — only your firm loses access.
                            Once every client is unlinked, come back here to schedule deletion.
                        </p>
                        <Link
                            href={firmGuard.practice_dashboard_url}
                            className="mt-3 inline-block px-5 py-2 rounded-xl bg-ink text-cream text-sm font-semibold hover:bg-ink-muted"
                        >
                            Open Practice console →
                        </Link>
                    </div>
                )}

                {!isScheduled && (
                    <>
                        <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8 space-y-4">
                            <h2 className="font-display text-lg font-medium text-ink">Before you proceed</h2>
                            <ul className="list-disc pl-5 text-sm text-ink-muted space-y-1.5">
                                <li>You have a <strong>{coolingOffDays}-day cooling-off period</strong> after requesting deletion. Sign in any time during that window to cancel.</li>
                                <li>Once the cooling-off period ends, your tenant database is dropped, your uploaded receipts are deleted, and personally identifying fields in retained financial records are redacted.</li>
                                <li>We retain anonymised financial records for 7 years to satisfy the Income Tax Act 1967. Customer / supplier names will be replaced with placeholders.</li>
                                <li>If you'd rather take a copy of everything before deleting, use{' '}
                                    <Link href={route('settings.data_export.show')} className="text-terracotta font-semibold hover:text-terracotta-dark">
                                        Download my data
                                    </Link>{' '}first.</li>
                            </ul>
                            <p className="text-xs text-ink-muted">
                                Need help or want to talk to someone first? Email us at{' '}
                                <a className="text-terracotta font-semibold" href={`mailto:${dpoEmail}`}>{dpoEmail}</a>.
                            </p>
                        </section>

                        {!showConfirm ? (
                            <button
                                type="button"
                                onClick={() => setShowConfirm(true)}
                                disabled={firmGuard.blocked}
                                className={`px-6 py-3 rounded-2xl text-sm font-semibold transition-colors ${
                                    firmGuard.blocked
                                        ? 'bg-terracotta/30 text-white/60 cursor-not-allowed'
                                        : 'bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light'
                                }`}
                            >
                                {firmGuard.blocked
                                    ? 'Unlink your clients first'
                                    : 'I understand, schedule my account for deletion'}
                            </button>
                        ) : (
                            <form
                                onSubmit={submitErase}
                                className="bg-terracotta/5 border border-terracotta/30 rounded-3xl p-6 sm:p-8 space-y-5"
                            >
                                <h3 className="font-display text-lg font-medium text-ink">Confirm with your password</h3>

                                <div>
                                    <InputLabel htmlFor="password" value="Current password" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="mt-1.5 block w-full rounded-xl"
                                        required
                                    />
                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <label className="flex items-start gap-2.5 text-sm text-ink cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 rounded border-border-warm text-terracotta focus:ring-terracotta"
                                        checked={data.confirm}
                                        onChange={(e) => setData('confirm', e.target.checked)}
                                    />
                                    <span className="leading-snug">
                                        I understand that after the cooling-off period my account and my organisation's books will be deleted, and that this is irreversible.
                                    </span>
                                </label>
                                <InputError message={errors.confirm} className="mt-2" />

                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={processing || !data.confirm || !data.password}
                                        className={`px-6 py-3 rounded-2xl font-semibold text-sm transition-colors ${
                                            processing || !data.confirm || !data.password
                                                ? 'bg-terracotta/30 text-white/60 cursor-not-allowed'
                                                : 'bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light'
                                        }`}
                                    >
                                        {processing ? 'Scheduling…' : 'Schedule deletion'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setShowConfirm(false); reset('password'); }}
                                        className="px-6 py-3 rounded-2xl text-sm font-semibold text-ink hover:bg-ink/5 transition-colors"
                                    >
                                        Back
                                    </button>
                                </div>
                            </form>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
