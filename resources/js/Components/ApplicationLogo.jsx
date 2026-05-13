/**
 * BukuCloud mark — open book + cloud (uses currentColor; shown white on indigo sidebar).
 */
export default function ApplicationLogo(props) {
    return (
        <svg
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            {/* Cloud */}
            <path
                fill="currentColor"
                fillOpacity="0.5"
                d="M11 17.5c0-3.4 2.8-6.2 6.2-6.2.8 0 1.5.2 2.2.5 1.2-2.1 3.5-3.5 6.1-3.5 3.8 0 6.9 2.8 7.5 6.4.3 0 .7-.05 1-.05 2.3 0 4.2 1.9 4.2 4.2 0 2.3-1.9 4.2-4.2 4.2H15.2c-2.9 0-5.2-2.3-5.2-5.2 0-1.7.8-3.2 2.1-4.1-.2-.5-.3-1.1-.3-1.7z"
            />
            {/* Book — left page */}
            <path
                fill="currentColor"
                d="M9 33V22.5L20 18.5V33H9z"
            />
            {/* Book — right page */}
            <path
                fill="currentColor"
                d="M20 18.5L31 22.5V33H20V18.5z"
            />
            {/* Spine highlight */}
            <path
                fill="currentColor"
                fillOpacity="0.35"
                d="M19 18.5h2V33h-2V18.5z"
            />
        </svg>
    );
}
