/**
 * Currency utilities shared across invoice / receipt / payment screens.
 * Single source of truth for symbol, decimals, and rounding behaviour.
 */

export const SUPPORTED_CURRENCIES = [
    { value: 'MYR', label: 'MYR — Malaysian Ringgit' },
    { value: 'USD', label: 'USD — US Dollar' },
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
        default:
            return normalizeCurrency(currency);
    }
}

/**
 * Number of decimal places typically used by the currency
 * (JPY rounds to whole yen, MYR/USD use 2dp).
 */
export function currencyDecimals(currency) {
    return normalizeCurrency(currency) === 'JPY' ? 0 : 2;
}

/**
 * Smallest billable unit used when rounding the invoice grand total.
 * MYR uses 5-sen rounding; JPY rounds to 1 yen; everything else uses 1 cent.
 */
export function currencyRoundStep(currency) {
    const c = normalizeCurrency(currency);
    if (c === 'MYR') return 0.05;
    if (c === 'JPY') return 1;
    return 0.01;
}

/**
 * HTML <input type="number"> step attribute for amounts in this currency.
 */
export function currencyInputStep(currency) {
    return normalizeCurrency(currency) === 'JPY' ? '1' : '0.01';
}

/**
 * Format a numeric amount with the appropriate prefix and decimals.
 */
export function formatCurrency(amount, currency) {
    const c = normalizeCurrency(currency);
    const decimals = currencyDecimals(c);
    const n = Number(amount) || 0;
    const formatted = n.toLocaleString('en-MY', {
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
    return 'Cent Rounding';
}
