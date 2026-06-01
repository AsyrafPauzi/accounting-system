import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const cssVar = (name) => `rgb(var(${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            colors: {
                cream:           cssVar('--color-cream'),
                surface:         cssVar('--color-surface'),
                'surface-alt':   cssVar('--color-surface-alt'),
                ink: {
                    DEFAULT: cssVar('--color-ink'),
                    muted:   cssVar('--color-ink-muted'),
                },
                'border-warm':   cssVar('--color-border-warm'),
                terracotta: {
                    DEFAULT: cssVar('--color-terracotta'),
                    dark:    cssVar('--color-terracotta-dark'),
                    light:   cssVar('--color-terracotta-light'),
                },
                forest: {
                    DEFAULT: cssVar('--color-forest'),
                    dark:    cssVar('--color-forest-dark'),
                    light:   cssVar('--color-forest-light'),
                },
                mustard:         cssVar('--color-mustard'),
            },
            fontFamily: {
                display: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
                sans:    ['Inter', ...defaultTheme.fontFamily.sans],
                mono:    ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            fontSize: {
                'eyebrow': ['11px', { lineHeight: '1.4', letterSpacing: '0.05em' }],
            },
        },
    },

    plugins: [forms],
};
