import { usePage } from '@inertiajs/react';

/**
 * BukuCloud frontend i18n helper.
 *
 * Server side: HandleInertiaRequests middleware shares `translations` (deep
 * dictionary, ms.json merged on top of en.json) and `locale` ('en' | 'ms').
 *
 * Client side: pages call `t('dashboard.welcome', { name: 'Asyraf' })`.
 *
 *  - Dot-paths walk the dictionary
 *  - Missing keys return the path itself so devs notice gaps
 *  - `:placeholder` tokens are interpolated from the params object
 *
 * Emails, PDFs and notifications stay in English (rendered server-side with
 * the default locale) — this helper governs UI strings only.
 */
function resolvePath(dict, path) {
    if (!dict || typeof dict !== 'object') return undefined;
    return path.split('.').reduce((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) {
            return acc[key];
        }
        return undefined;
    }, dict);
}

function interpolate(template, params) {
    if (typeof template !== 'string') return template;
    if (!params) return template;
    return template.replace(/:(\w+)/g, (match, key) => {
        return key in params ? String(params[key]) : match;
    });
}

/**
 * Pure helper. Pass the translations dict explicitly. Useful in tests, in
 * non-Inertia contexts, or when you want to memoize the dict at module load.
 */
export function translate(translations, key, params) {
    const value = resolvePath(translations, key);
    if (value === undefined) {
        return key; // Fallback so missing keys are visible to QA
    }
    return interpolate(value, params);
}

/**
 * Inertia-aware hook. Reads `translations` from page props.
 */
export function useTranslation() {
    const { translations = {}, locale = 'en' } = usePage().props;

    const t = (key, params) => translate(translations, key, params);

    return { t, locale };
}

/**
 * Bare module-level `t()` for cases where hooks aren't usable (e.g. inside
 * static class component methods or component-config arrays). Reads from the
 * Inertia page on each call. Slightly slower than the hook but always correct
 * after locale changes.
 */
export function t(key, params) {
    if (typeof window === 'undefined') return key;
    const inertia = window?.Inertia;
    const props = inertia?.page?.props ?? {};
    return translate(props.translations ?? {}, key, params);
}
