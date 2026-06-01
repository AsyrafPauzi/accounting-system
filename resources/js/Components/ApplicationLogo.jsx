/**
 * BukuCloud icon — open book + cloud brand mark.
 * Renders the official PNG asset; keeps the same prop API so existing
 * callers in layouts continue to work without changes.
 */
export default function ApplicationLogo({ className = '', alt = 'BukuCloud', ...props }) {
    return (
        <img
            src="/images/bukucloud-icon.png"
            alt={alt}
            className={`object-contain ${className}`}
            {...props}
        />
    );
}
