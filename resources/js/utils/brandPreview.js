import { useEffect } from 'react';
import { router } from '@inertiajs/react';

/**
 * Cross-page accent preview for the super-admin's branding form.
 *
 * The Branding form lets the super-admin tweak terracotta / forest / mustard
 * accents before saving. While they're tweaking we want the WHOLE app to
 * re-render in those colors (including hover states), so they can navigate
 * to Invoices, Dashboard, Reports etc. and verify the look before committing.
 *
 * Storage:
 *  - Preview hex codes are stashed in sessionStorage under PREVIEW_KEY.
 *  - sessionStorage scope = the current browser tab, so opening the app in a
 *    new tab gives a clean baseline.
 *  - applyBrandPreview() pushes the preview into document.documentElement.style
 *    so it beats whatever <style id="bukucloud-brand-vars"> already has.
 *  - clearBrandPreview() removes both the stash and the inline overrides.
 *
 * Performance: HSL conversion + 9 setProperty calls runs once per page load
 * — under 1 ms total even on a low-end laptop. Safe to call from a global
 * watcher mounted at the React root.
 */

const HEX = /^#[A-Fa-f0-9]{6}$/;
const PREVIEW_KEY = 'bukucloud:brand-preview';

const ACCENTS_WITH_VARIANTS = ['terracotta', 'forest']; // accents that have -dark / -light utilities

function safeStorage() {
    try {
        if (typeof window === 'undefined' || !window.sessionStorage) return null;
        return window.sessionStorage;
    } catch {
        return null;
    }
}

export function readPreview() {
    const ss = safeStorage();
    if (!ss) return null;
    try {
        const raw = ss.getItem(PREVIEW_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function writePreview(values) {
    const ss = safeStorage();
    if (!ss) return;
    try {
        // Strip empty / invalid entries so { color_terracotta: '' } doesn't
        // wipe the saved value — only set keys when they're a proper hex.
        const cleaned = {};
        for (const k of ['color_terracotta', 'color_forest', 'color_mustard']) {
            if (values?.[k] && HEX.test(values[k])) cleaned[k] = values[k];
        }
        if (Object.keys(cleaned).length === 0) {
            ss.removeItem(PREVIEW_KEY);
        } else {
            ss.setItem(PREVIEW_KEY, JSON.stringify(cleaned));
        }
    } catch {
        // Storage quota / private browsing — silently no-op.
    }
}

export function clearPreview() {
    const ss = safeStorage();
    try { ss?.removeItem(PREVIEW_KEY); } catch {}

    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    for (const accent of ['terracotta', 'forest', 'mustard']) {
        root.style.removeProperty(`--color-${accent}`);
        root.style.removeProperty(`--color-${accent}-dark`);
        root.style.removeProperty(`--color-${accent}-light`);
    }
}

/* ---------------- color math ---------------- */

function hexToRgb(hex) {
    return [
        parseInt(hex.slice(1, 3), 16),
        parseInt(hex.slice(3, 5), 16),
        parseInt(hex.slice(5, 7), 16),
    ];
}

function rgbToHsl(r, g, b) {
    const rN = r / 255, gN = g / 255, bN = b / 255;
    const max = Math.max(rN, gN, bN), min = Math.min(rN, gN, bN);
    const l = (max + min) / 2;
    if (max === min) return [0, 0, l];

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h;
    switch (max) {
        case rN: h = (gN - bN) / d + (gN < bN ? 6 : 0); break;
        case gN: h = (bN - rN) / d + 2; break;
        default: h = (rN - gN) / d + 4;
    }
    return [h / 6, s, l];
}

function hslToTriplet(h, s, l) {
    if (s === 0) {
        const v = Math.round(l * 255);
        return `${v} ${v} ${v}`;
    }
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    const hue2rgb = (p, q, t) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
    };
    const r = Math.round(hue2rgb(p, q, h + 1 / 3) * 255);
    const g = Math.round(hue2rgb(p, q, h) * 255);
    const b = Math.round(hue2rgb(p, q, h - 1 / 3) * 255);
    return `${r} ${g} ${b}`;
}

const SHIFT = 0.12;

export function deriveAccentTriplets(hex) {
    if (!hex || !HEX.test(hex)) return null;
    const [r, g, b] = hexToRgb(hex);
    const [h, s, l] = rgbToHsl(r, g, b);
    return {
        base: `${r} ${g} ${b}`,
        dark: hslToTriplet(h, s, Math.max(l - SHIFT, 0.05)),
        light: hslToTriplet(h, Math.max(s - 0.05, 0), Math.min(l + SHIFT, 0.95)),
    };
}

/**
 * Push the given preview values onto :root as inline CSS variables. Pass
 * `null` / undefined for an accent to clear that accent's inline overrides
 * (so the persisted Blade <style> takes back over).
 */
export function applyBrandPreview(values) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;

    for (const accent of ['terracotta', 'forest', 'mustard']) {
        const hex = values?.[`color_${accent}`];
        const triplets = deriveAccentTriplets(hex);
        if (!triplets) {
            root.style.removeProperty(`--color-${accent}`);
            root.style.removeProperty(`--color-${accent}-dark`);
            root.style.removeProperty(`--color-${accent}-light`);
            continue;
        }
        root.style.setProperty(`--color-${accent}`, triplets.base);
        if (ACCENTS_WITH_VARIANTS.includes(accent)) {
            root.style.setProperty(`--color-${accent}-dark`, triplets.dark);
            root.style.setProperty(`--color-${accent}-light`, triplets.light);
        }
    }
}

/**
 * Mount once at the Inertia app root. On every page the user lands on, it
 * reads the preview from sessionStorage and pushes the inline overrides onto
 * <html style="…">. Cost: one sessionStorage read + one HSL conversion + a
 * handful of setProperty calls. Sub-millisecond on every measured device.
 */
export function BrandPreviewWatcher() {
    useEffect(() => {
        // Apply on first mount.
        applyBrandPreview(readPreview());

        // Re-apply after every Inertia navigation. Inertia keeps the React
        // tree mounted across visits, so without this listener the inline
        // overrides survive (which we want) but the browser doesn't get a
        // chance to re-poll storage if another tab updated the preview.
        const off = router.on('finish', () => {
            applyBrandPreview(readPreview());
        });
        return () => off();
    }, []);

    return null;
}
