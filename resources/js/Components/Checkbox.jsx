export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-border-warm bg-surface text-terracotta shadow-sm focus:ring-terracotta ' +
                className
            }
        />
    );
}
