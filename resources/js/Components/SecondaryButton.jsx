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
                `inline-flex items-center rounded-xl border border-ink bg-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink shadow-sm transition duration-150 ease-in-out hover:bg-surface-alt focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
