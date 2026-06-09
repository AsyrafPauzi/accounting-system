/**
 * Shared gate for the verify-email reminder modal. Both layouts
 * (`AuthenticatedLayout` for SME / firm-acting-on-client, and
 * `PracticeLayout` for the firm console) need the exact same trigger
 * logic, so it lives here rather than duplicated in two files.
 *
 * The modal pops when ALL of these are true:
 *   1. The user is logged in.
 *   2. They have not verified their email yet (`email_verified_at` null).
 *   3. They aren't being impersonated by an admin (we don't want to
 *      pester the admin while they're debugging the customer's account).
 *   4. Either they've never seen the reminder before, or they last
 *      dismissed it 2+ days ago.
 *
 * The 2-day cadence is computed against the timestamp the server
 * stamps on `users.verify_reminder_at` whenever the user clicks
 * "Skip for now" in the modal.
 */

const REMINDER_INTERVAL_MS = 2 * 24 * 60 * 60 * 1000; // 2 days

export function shouldShowVerifyReminder(user, isImpersonating = false) {
    if (!user) return false;
    if (user.email_verified_at) return false;
    if (isImpersonating) return false;

    if (!user.verify_reminder_at) return true;

    const last = new Date(user.verify_reminder_at).getTime();
    if (Number.isNaN(last)) return true; // unparseable → safer to show
    return Date.now() - last >= REMINDER_INTERVAL_MS;
}
