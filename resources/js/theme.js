import { useEffect, useState, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';

/**
 * BukuCloud light/dark theme system.
 *
 * Storage:
 *  - Persisted per user as users.theme_preference ('light' | 'dark' | 'system')
 *  - Initial paint applied via inline boot script in app.blade.php (FOUC safe)
 *
 * Runtime:
 *  - <ThemeWatcher /> is mounted once at the Inertia app root and keeps the
 *    html.dark class in sync with the user's preference everywhere — including
 *    reacting to OS-level prefers-color-scheme changes when preference is
 *    'system'. Without it, OS theme changes only updated the page that
 *    happened to render <AppearanceForm/> (i.e. /profile).
 *  - useTheme() returns the current preference + setter for the AppearanceForm
 *    UI. Updating the preference applies the DOM class immediately, then
 *    PATCHes /profile/theme so future pages render with it.
 */

const VALID = ['light', 'dark', 'system'];

function resolveActual(preference) {
    if (preference === 'system' && typeof window !== 'undefined' && window.matchMedia) {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return preference === 'dark' ? 'dark' : 'light';
}

function applyTheme(preference) {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.toggle('dark', resolveActual(preference) === 'dark');
}

/**
 * Side-effect hook that keeps the html.dark class in sync with the
 * server-shared `theme` prop, AND listens to OS-level prefers-color-scheme
 * changes whenever preference === 'system'. Use via <ThemeWatcher />.
 */
export function useThemeSync() {
    const shared = usePage().props?.theme;
    const preference = VALID.includes(shared) ? shared : 'light';

    useEffect(() => {
        applyTheme(preference);
    }, [preference]);

    useEffect(() => {
        if (preference !== 'system' || typeof window === 'undefined' || !window.matchMedia) return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyTheme('system');
        // Older Safari uses addListener/removeListener; modern browsers use addEventListener.
        if (mq.addEventListener) {
            mq.addEventListener('change', handler);
            return () => mq.removeEventListener('change', handler);
        }
        mq.addListener(handler);
        return () => mq.removeListener(handler);
    }, [preference]);
}

/** Mount once at the Inertia app root to enable global theme syncing. */
export function ThemeWatcher() {
    useThemeSync();
    return null;
}

/**
 * Read + write the user's theme preference. Used in the AppearanceForm.
 *
 * Local state is initialised from the shared prop and re-synced whenever the
 * server-side preference changes (e.g. another tab updated it). This makes the
 * active-button highlight reliably reflect the persisted value.
 */
export function useTheme() {
    const shared = usePage().props?.theme;
    const initial = VALID.includes(shared) ? shared : 'light';
    const [preference, setPreference] = useState(initial);

    useEffect(() => {
        if (initial !== preference) {
            setPreference(initial);
            applyTheme(initial);
        }
    }, [initial]);

    useEffect(() => {
        applyTheme(preference);
    }, [preference]);

    const updatePreference = useCallback((next) => {
        if (!VALID.includes(next)) return;
        setPreference(next);
        applyTheme(next);

        router.patch(
            route('profile.theme'),
            { theme_preference: next },
            { preserveScroll: true, preserveState: true, only: ['theme'] }
        );
    }, []);

    return { preference, setPreference: updatePreference, options: VALID };
}
