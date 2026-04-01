/** Predefined industries (last option must be Others). Stored in customers.industry. */
export const INDUSTRY_OPTIONS = [
    'Manufacturing',
    'Retail',
    'Technology',
    'Healthcare',
    'Construction',
    'Food & Beverage',
    'Professional Services',
    'Logistics',
    'Agriculture',
    'Real Estate',
    'Others',
];

/** Payment terms as integer days (matches DB). */
export const PAYMENT_TERM_PRESETS = [
    { value: 0, label: 'Due on receipt / COD' },
    { value: 7, label: 'Net 7 days' },
    { value: 10, label: 'Net 10 days' },
    { value: 15, label: 'Net 15 days' },
    { value: 30, label: 'Net 30 days' },
    { value: 45, label: 'Net 45 days' },
    { value: 60, label: 'Net 60 days' },
    { value: 90, label: 'Net 90 days' },
];

export const PAYMENT_TERM_CUSTOM = '__custom__';

export function deriveIndustryState(stored) {
    if (!stored) return { industry_key: '', industry_other: '' };
    if (INDUSTRY_OPTIONS.includes(stored)) return { industry_key: stored, industry_other: '' };
    if (stored.startsWith('Other:')) return { industry_key: 'Others', industry_other: stored.slice(6).trim() };
    if (stored === 'Other') return { industry_key: 'Others', industry_other: '' };
    return { industry_key: 'Others', industry_other: stored };
}

export function derivePaymentTermsState(days) {
    const d = Number(days);
    if (Number.isNaN(d)) {
        return { payment_terms_select: '30', payment_terms_custom: '30' };
    }
    const preset = PAYMENT_TERM_PRESETS.some((p) => p.value === d);
    if (preset) {
        return { payment_terms_select: String(d), payment_terms_custom: String(d) };
    }
    return { payment_terms_select: PAYMENT_TERM_CUSTOM, payment_terms_custom: String(d) };
}

/**
 * Maps UI-only fields to API payload (industry string, payment_terms int).
 * Omits industry_key, industry_other, payment_terms_select, payment_terms_custom.
 */
export function mergeCustomerFormPayload(data) {
    const industry =
        data.industry_key === 'Others'
            ? data.industry_other?.trim()
                ? `Other: ${data.industry_other.trim()}`
                : 'Other'
            : data.industry_key || '';

    let payment_terms = parseInt(String(data.payment_terms_select), 10);
    if (data.payment_terms_select === PAYMENT_TERM_CUSTOM) {
        payment_terms = Math.min(365, Math.max(0, parseInt(String(data.payment_terms_custom), 10) || 0));
    } else if (Number.isNaN(payment_terms)) {
        payment_terms = 30;
    }

    const {
        industry_key,
        industry_other,
        payment_terms_select,
        payment_terms_custom,
        industry: _i,
        ...rest
    } = data;

    const account_manager_id =
        rest.account_manager_id === '' || rest.account_manager_id === undefined
            ? null
            : rest.account_manager_id;

    return { ...rest, industry, payment_terms, account_manager_id };
}
