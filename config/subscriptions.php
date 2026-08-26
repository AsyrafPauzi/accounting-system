<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New-tenant Solo trial
    |--------------------------------------------------------------------------
    |
    | When a new SME signs up via /register we drop them onto the Solo plan
    | with status="trialing" for this many days, with the free Startup tier
    | queued as `pending_plan_id`. The existing
    | `subscription:apply-pending` cron flips them to Startup automatically
    | when the trial ends — no extra cron needed.
    |
    | Set `trial_days` to 0 (or `trial_enabled` to false) to disable the
    | trial entirely; new tenants will land on the free Startup plan instead.
    |
    | The trial only applies to the SME signup flow. Firms (practice) keep
    | their existing free Practice tier and are not auto-trialed.
    |
    */

    'trial_enabled' => (bool) env('SUBSCRIPTION_TRIAL_ENABLED', true),

    'trial_days'    => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),

    /**
     * The slug of the plan a new tenant trials. Must exist in the `plans`
     * table with audience=sme. Defaults to "solo" so trial users can try
     * core paid bookkeeping features (bills, suppliers, credit notes,
     * recurring invoices, estimates, OCR, and standard reports) without
     * unlocking higher-tier Growth/Corporate-only features.
     */
    'trial_plan_slug'    => env('SUBSCRIPTION_TRIAL_PLAN_SLUG', 'solo'),

    /**
     * The slug the trial auto-downgrades into when it ends. Must exist in
     * `plans` with price_monthly = 0.
     */
    'trial_fallback_slug' => env('SUBSCRIPTION_TRIAL_FALLBACK_SLUG', 'startup'),

    'renewal_lead_days' => (int) env('SUBSCRIPTION_RENEWAL_LEAD_DAYS', 7),
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),
];
