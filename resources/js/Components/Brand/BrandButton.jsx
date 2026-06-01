import { Link } from '@inertiajs/react';

/**
 * Brand button.
 *
 * All sizes share the same canonical font size (`text-xs`) so primary,
 * secondary, ghost and forest buttons look like one family across the app.
 * Vertical padding scales between sizes for hierarchy, but the type
 * weight stays consistent — matches the sidebar Settings/Logout pattern.
 *
 * Variants:
 * - primary  — filled terracotta, white text (default CTA)
 * - secondary — outline ink on surface background
 * - ghost     — text-only, hover bg-surface-alt
 * - forest    — filled forest, white text (positive / accept flows)
 * - danger    — destructive primary
 *
 * Renders as a <Link> if `href` is supplied, otherwise <button>.
 */
const variantClass = {
    primary:   'bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light focus:ring-terracotta',
    secondary: 'bg-surface border border-border-warm text-ink hover:bg-surface-alt focus:ring-terracotta',
    ghost:     'bg-transparent text-ink hover:bg-surface-alt focus:ring-terracotta',
    forest:    'bg-forest text-white hover:bg-forest-dark dark:bg-forest-light dark:hover:bg-forest focus:ring-forest',
    danger:    'bg-surface border border-terracotta/30 text-terracotta hover:bg-terracotta/10 focus:ring-terracotta',
};

const sizeClass = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-xs',
    lg: 'px-5 py-2.5 text-xs',
};

export default function BrandButton({
    children,
    variant = 'primary',
    size = 'md',
    href,
    method,
    as,
    type = 'button',
    disabled = false,
    className = '',
    ...props
}) {
    const cls = `inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed ${
        variantClass[variant] || variantClass.primary
    } ${sizeClass[size] || sizeClass.md} ${className}`;

    if (href) {
        return (
            <Link href={href} method={method} as={as} className={cls} disabled={disabled} {...props}>
                {children}
            </Link>
        );
    }
    return (
        <button type={type} className={cls} disabled={disabled} {...props}>
            {children}
        </button>
    );
}
