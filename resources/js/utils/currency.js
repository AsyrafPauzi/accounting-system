/**
 * Currency utilities shared across invoice / receipt / payment screens.
 * Single source of truth for symbol, decimals, and rounding behaviour.
 */

export const SUPPORTED_CURRENCIES = [
    { value: 'MYR', label: 'MYR — Malaysian Ringgit' },
    { value: 'IDR', label: 'IDR — Indonesian Rupiah' },
    { value: 'USD', label: 'USD — US Dollar' },
    { value: 'SGD', label: 'SGD — Singapore Dollar' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'GBP', label: 'GBP — British Pound' },
    { value: 'JPY', label: 'JPY — Japanese Yen' },
];

export function normalizeCurrency(currency) {
    return (currency || 'MYR').toUpperCase();
}

export function currencySymbol(currency) {
    switch (normalizeCurrency(currency)) {
        case 'USD':
            return 'US$';
        case 'JPY':
            return '¥';
        case 'MYR':
            return 'RM';
        case 'IDR':
            return 'Rp';
        case 'SGD':
            return 'S$';
        case 'EUR':
            return '€';
        case 'GBP':
            return '£';
        default:
            return normalizeCurrency(currency);
    }
}

/**
 * Number of decimal places typically used by the currency.
 * (JPY and IDR are zero-decimal currencies in everyday use; MYR/USD use 2dp.)
 */
export function currencyDecimals(currency) {
    const c = normalizeCurrency(currency);
    return c === 'JPY' || c === 'IDR' ? 0 : 2;
}

/**
 * Smallest billable unit used when rounding the invoice grand total.
 * MYR uses 5-sen rounding; JPY/IDR round to 1 unit; everything else uses 1 cent.
 */
export function currencyRoundStep(currency) {
    const c = normalizeCurrency(currency);
    if (c === 'MYR') return 0.05;
    if (c === 'JPY' || c === 'IDR') return 1;
    return 0.01;
}

/**
 * HTML <input type="number"> step attribute for amounts in this currency.
 */
export function currencyInputStep(currency) {
    const c = normalizeCurrency(currency);
    return c === 'JPY' || c === 'IDR' ? '1' : '0.01';
}

/**
 * Format a numeric amount with the appropriate prefix and decimals.
 * IDR uses id-ID locale formatting (`.` thousand separator) so amounts read
 * the way Indonesian receipts display them — e.g. Rp 1.500.000.
 */
export function formatCurrency(amount, currency) {
    const c = normalizeCurrency(currency);
    const decimals = currencyDecimals(c);
    const n = Number(amount) || 0;
    const locale = c === 'IDR' ? 'id-ID' : 'en-MY';
    const formatted = n.toLocaleString(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
    return `${currencySymbol(c)} ${formatted}`;
}

/**
 * Short human-readable description of the rounding rule for this currency.
 */
export function roundingLabel(currency) {
    const c = normalizeCurrency(currency);
    if (c === 'MYR') return '5-Sen Rounding';
    if (c === 'JPY') return 'Whole-Yen Rounding';
    if (c === 'IDR') return 'Whole-Rupiah Rounding';
    return 'Cent Rounding';
}
