/**
 * Shared gate for the verify-email reminder modal. Both layouts
 * (`AuthenticatedLayout` for SME / firm-acting-on-client, and
 * `PracticeLayout` for the firm console) need the exact same trigger
 * logic, so it lives here rather than duplicated in two files.
 *
 * The modal pops when ALL of these are true:
 *   1. The user is logged in.
 *   2. They have not verified their email yet (`email_verified_at` null).
 *   3. They aren't a platform super-admin (internal operators are exempt).
 *   4. They aren't being impersonated by an admin (we don't want to
 *      pester the admin while they're debugging the customer's account).
 *   5. Either they've never seen the reminder before, or they last
 *      dismissed it 1+ day ago.
 *
 * The 1-day cadence is computed against the timestamp the server
 * stamps on `users.verify_reminder_at` whenever the user clicks
 * "Skip for now" in the modal. We also keep a same-browser copy so
 * stale Inertia props can't immediately re-open the modal after Skip.
 */

const REMINDER_INTERVAL_MS = 24 * 60 * 60 * 1000; // 1 day

function reminderStorageKey(user) {
    const identifier = user?.id ?? user?.email ?? 'anonymous';
    return `bukucloud.verifyReminderSkippedAt.${identifier}`;
}

export function markVerifyReminderSkipped(user, timestamp = Date.now()) {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(reminderStorageKey(user), String(timestamp));
}

function localSkippedAt(user) {
    if (typeof window === 'undefined') return null;

    const value = window.localStorage.getItem(reminderStorageKey(user));
    if (!value) return null;

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

export function shouldShowVerifyReminder(user, isImpersonating = false) {
    if (!user) return false;
    if (user.email_verified_at) return false;
    if (user.role_name === 'super-admin') return false;
    if (isImpersonating) return false;

    const skippedAt = [
        user.verify_reminder_at ? new Date(user.verify_reminder_at).getTime() : null,
        localSkippedAt(user),
    ].filter((value) => value !== null && !Number.isNaN(value));

    if (skippedAt.length === 0) return true;

    const last = Math.max(...skippedAt);
    return Date.now() - last >= REMINDER_INTERVAL_MS;
}
