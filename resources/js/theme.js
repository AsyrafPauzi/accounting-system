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
 *  - useTheme() returns the current preference + setter
 *  - When preference is 'system', listens to prefers-color-scheme media query
 *  - setPreference() updates DOM immediately, then PATCHes to the server
 */

const VALID = ['light', 'dark', 'system'];

function applyTheme(preference) {
    if (typeof document === 'undefined') return;

    const actual =
        preference === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : preference;

    document.documentElement.classList.toggle('dark', actual === 'dark');
}

export function useTheme() {
    const initial = usePage().props?.theme ?? 'light';
    const [preference, setPreference] = useState(VALID.includes(initial) ? initial : 'light');

    // React to system preference changes when in 'system' mode
    useEffect(() => {
        if (preference !== 'system' || typeof window === 'undefined') return;

        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyTheme('system');
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, [preference]);

    // Apply on every change (covers manual 'light' / 'dark' switches too)
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
