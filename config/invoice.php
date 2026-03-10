<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company (Seller) Details for Invoice PDF
    |--------------------------------------------------------------------------
    | Customize these values in .env for your business branding on invoices.
    */
    'company' => [
        'name'    => env('INVOICE_COMPANY_NAME', config('app.name')),
        'address' => env('INVOICE_COMPANY_ADDRESS', '123 Business Street'),
        'city'    => env('INVOICE_COMPANY_CITY', 'Kuala Lumpur'),
        'state'   => env('INVOICE_COMPANY_STATE', 'Wilayah Persekutuan'),
        'zip'     => env('INVOICE_COMPANY_ZIP', '50000'),
        'country' => env('INVOICE_COMPANY_COUNTRY', 'Malaysia'),
        'phone'   => env('INVOICE_COMPANY_PHONE', null),
        'email'   => env('INVOICE_COMPANY_EMAIL', null),
        'website' => env('INVOICE_COMPANY_WEBSITE', null),
        'tin'     => env('INVOICE_COMPANY_TIN', null),
        'brn'     => env('INVOICE_COMPANY_BRN', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Behaviour & Copy
    |--------------------------------------------------------------------------
    | Controls how invoice emails are composed. Values can be overridden
    | per-environment via .env.
    */
    'email' => [
        'subject_format' => env('INVOICE_EMAIL_SUBJECT', 'Invoice :number from :company'),
        'intro_text' => env('INVOICE_EMAIL_INTRO', 'Please find attached your tax invoice in PDF format.'),
        'footer_text' => env('INVOICE_EMAIL_FOOTER', 'Thank you for your business.'),
    ],

];
