export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center justify-center gap-1.5 rounded-xl border border-border-warm bg-surface px-4 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-alt focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 ${
                    disabled && 'opacity-50 cursor-not-allowed'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
