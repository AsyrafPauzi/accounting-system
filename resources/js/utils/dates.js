/**
 * Date-only display. Laravel `date` casts JSON-serialize as
 * 2026-08-13T00:00:00.000000Z — never interpolate those raw.
 */
export function formatDate(value) {
    if (! value) {
        return '';
    }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return String(value).slice(0, 10);
    }
    return d.toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}
