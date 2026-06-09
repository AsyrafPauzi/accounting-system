import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Modal from '@/Components/Modal';

/**
 * Soft email-verification nag. Shows on top of every authenticated
 * page when the user is unverified AND has not dismissed the modal in
 * the last 2 days. The "every 2 days" cadence is enforced server-side
 * via `users.verify_reminder_at`; this component just renders the
 * modal when the layout decides it should appear.
 *
 * Three actions:
 *
 *   - "Resend verification email" → POSTs to `verification.send` and
 *     closes the modal optimistically. We do NOT stamp the reminder
 *     here — clicking "send" is engagement, and we want the modal to
 *     come back tomorrow if they still haven't clicked the link.
 *
 *   - "Skip for now" → POSTs to `onboarding.verify-reminder.dismiss`
 *     which stamps verify_reminder_at = now(). Modal stays away for
 *     2 days, then comes back if still unverified.
 *
 *   - "I already verified — refresh" → soft reload. Handles the case
 *     where the user clicked the verify link in another tab and is
 *     just waiting for the auth state to update on this one.
 *
 * Closing via the X (top-right) is intentionally NOT supported — the
 * user has to make an explicit choice between "send", "skip", or
 * "refresh". Otherwise an outside-click could silently bury the nag
 * for the rest of the session.
 */
export default function VerifyEmailReminderModal({ show, onClose }) {
    const page = usePage();
    const userEmail = page.props.auth?.user?.email ?? '';
    const productName = page.props.product_name ?? 'BukuCloud';

    const [busy, setBusy] = useState(null); // 'send' | 'skip' | null
    const [sent, setSent] = useState(false);

    const sendVerification = () => {
        if (busy) return;
        setBusy('send');
        router.post(
            route('verification.send'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setSent(true),
                onFinish: () => setBusy(null),
            },
        );
    };

    const skip = () => {
        if (busy) return;
        setBusy('skip');
        router.post(
            route('onboarding.verify-reminder.dismiss'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setBusy(null);
                    onClose?.();
                },
            },
        );
    };

    return (
        <Modal show={show} maxWidth="lg" closeable={false} onClose={() => {}}>
            <div className="bg-cream">
                <div className="px-6 sm:px-8 pt-6 pb-4 border-b border-border-warm">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">
                        One small thing
                    </p>
                    <h2 className="font-display text-2xl text-ink tracking-tight mt-1">
                        Verify your email
                    </h2>
                </div>

                <div className="px-6 sm:px-8 py-6 space-y-4">
                    <p className="text-ink leading-relaxed">
                        We sent a verification link to{' '}
                        <strong className="text-ink">{userEmail || 'your inbox'}</strong>.
                        Click it to confirm your address and unlock features like password resets and important account notifications.
                    </p>

                    <div className="rounded-xl bg-surface border border-border-warm p-4 text-sm text-ink-muted">
                        You can keep using {productName} either way — we'll just remind you again in 2 days if you skip.
                    </div>

                    {sent && (
                        <div className="rounded-xl bg-forest/10 border border-forest/30 p-3 text-sm font-medium text-forest">
                            Verification email re-sent. Check your inbox (and spam folder).
                        </div>
                    )}
                </div>

                <div className="px-6 sm:px-8 py-4 bg-surface-alt border-t border-border-warm flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <button
                        type="button"
                        onClick={() => router.reload({ preserveScroll: true })}
                        disabled={Boolean(busy)}
                        className="text-eyebrow font-semibold uppercase text-ink-muted hover:text-ink transition-colors text-left disabled:opacity-50"
                    >
                        I already verified — refresh
                    </button>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={skip}
                            disabled={Boolean(busy)}
                            className="px-4 py-2 rounded-xl text-sm font-semibold text-ink hover:bg-cream transition-colors disabled:opacity-50"
                        >
                            {busy === 'skip' ? 'Saving…' : 'Skip for now'}
                        </button>
                        <button
                            type="button"
                            onClick={sendVerification}
                            disabled={Boolean(busy)}
                            className="px-5 py-2 rounded-xl text-sm font-semibold bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors disabled:opacity-50"
                        >
                            {busy === 'send'
                                ? 'Sending…'
                                : sent
                                    ? 'Resend email'
                                    : 'Send verification email'}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
