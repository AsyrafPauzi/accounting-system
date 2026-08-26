<?php

/**
 * Product changelog — Settings → What's new.
 *
 * Covers the full BukuCloud history from the first commit (2026-03-10).
 * Add a new release block at the top when shipping user-visible changes.
 */
return [

    'meta' => [
        'product'        => 'BukuCloud',
        'first_commit'   => '2026-03-10',
        'first_release'  => 'Foundation — first code',
    ],

    'releases' => [
        [
            'id' => 'wave-4',
            'label' => 'Wave 4 — Depth & Scale',
            'date' => '2026-08-27',
            'summary' => 'Inventory, fixed assets, cash flow reporting, FX, customer portal, budgets, payroll exports, and observability.',
            'sections' => [
                'added' => [
                    'Inventory lite — stock on hand, GRN receive, weighted-average COGS on invoice post',
                    'Fixed assets — register assets, monthly straight-line depreciation, disposal journals',
                    'IAS 7 cash flow statement (PDF) and cash vs accrual P&L toggle',
                    'Budgets vs actual — budget entry by account/month and variance report with CSV export',
                    'Realized foreign exchange gain/loss on invoice and bill payment (accounts 4200 / 4300)',
                    'Month-end unrealized FX revaluation command (`fx:revaluate`) for open foreign AR/AP',
                    'Bank statement PDF import (text-based PDFs; CSV also supported)',
                    'P&L drill-through to source documents from each account line',
                    'Customer portal — magic-link history, statement PDF, and Pay Now deep links',
                    'Comparative balance sheet and trial balance (prior period columns)',
                    'Employee master with EPF and PCB CSV export; manual payroll totals for external payroll systems',
                    'Sentry error tracking (optional) and JSON logs with tenant_id for production debugging',
                    'NavSidebar extracted from layout; shared invoice Create/Edit/Show document layout',
                    'Bahasa Malaysia labels on invoice and bill list pages (including action menus and confirm dialogs)',
                    'Settings → What\'s new changelog page (this page)',
                    '`sentry:smoke` artisan command for staging DSN verification',
                ],
                'fixed' => [
                    'Bill and credit note lines post tax to the correct accounts from tax codes',
                    'Invoice edit reposts GL through JournalWriter with balance checks',
                    'Period lock enforced on void and related money paths',
                ],
            ],
        ],
        [
            'id' => 'wave-3',
            'label' => 'Wave 3 — Bukku Parity & Practice Moat',
            'date' => '2026-08-27',
            'summary' => 'Bank reconciliation, SST filing helpers, receipt inbox, practice close pack, and MyInvois vault.',
            'sections' => [
                'added' => [
                    'Bank reconciliation v1 — CSV statement import, suggest-match, and reconcile to GL',
                    'SST-02 export (CSV/PDF) driven by tax codes',
                    'Official Receipt (AR) and Payment Voucher (AP) PDFs with document numbering',
                    'Receipt inbox — OCR jobs list with confirm-to-bill flow',
                    'Practice close pack widget and firm staff invites with seat cap',
                    'MyInvois submission payload vault; e-invoice submit on Growth plan',
                    'Async tenant provisioning — no tenants:migrate on every ECS boot',
                ],
                'fixed' => [
                    'Tax-code CRUD and line FK on sales/purchase documents',
                ],
            ],
        ],
        [
            'id' => 'wave-2',
            'label' => 'Wave 2 — Accountant & Wave Simplicity',
            'date' => '2026-08-27',
            'summary' => 'Month-end controls, tax codes, public pay page, and SME onboarding.',
            'sections' => [
                'added' => [
                    'Accounting period lock with reopen permission',
                    'Tax code master (SR-8, ST-10, ES, ZRL) with SST-aware posting',
                    'AR/AP aging uses remaining balance after credit notes and deposits',
                    'Document numbering settings — prefix, next number, financial-year reset',
                    'Public HTML invoice page with Pay Now, PDF, and WhatsApp share',
                    'Day-1 onboarding checklist; Startup tier can record payments',
                    'Partial Bahasa Malaysia (nav and core labels); base currency from company settings',
                ],
                'fixed' => [
                    'Deposit and credit-note applications reflected consistently in AR balances',
                ],
            ],
        ],
        [
            'id' => 'wave-1',
            'label' => 'Wave 1 — Trust Foundation',
            'date' => '2026-08-27',
            'summary' => 'Ledger integrity, webhook security, scheduler, CI gate, and firm RBAC.',
            'sections' => [
                'added' => [
                    'Posted journals only — reports and GL filter on status=posted',
                    'Balance sheet includes current-year earnings (A = L + E)',
                    'Invoice journal date uses issue_date for TB/cash alignment',
                    'ECS scheduler runs Laravel schedule:run every 60 seconds',
                    'GitHub Actions test + build gate before deploy',
                ],
                'fixed' => [
                    'ToyyibPay invoice webhooks verified server-side',
                    'Billplz callbacks reject unsigned payloads when x-signature key is set',
                    'Firm viewer role blocked from POST write routes when acting on client tenant',
                ],
            ],
        ],
        [
            'id' => '2026-08-reports-parity',
            'label' => 'Reports hub, sales chain & Copilot',
            'date' => '2026-08-18',
            'summary' => 'Wave-style reports hub, full sales/purchases document chain, Accountant Copilot, and Billplz renewals.',
            'sections' => [
                'added' => [
                    'Reports hub with ledger drill-down and Malaysia tax/payroll report packs',
                    'Sales & Purchases parity — estimates, sales orders, delivery orders, PO, GRN, credit/debit notes',
                    'Unified document show layouts across sales and purchase modules',
                    'Accountant Copilot (confirm-gated AI writes) and Ilmu OCR provider',
                    'Billplz subscription payment-link renewals with grace-period expiry',
                    'Payroll journal API with HMAC signing key shown once at creation',
                    'Background OCR processing and optimized date-range queries',
                ],
                'fixed' => [
                    'Show-page crashes on sales and purchase documents',
                    'Unified sales and purchases screen styling',
                ],
            ],
        ],
        [
            'id' => '2026-06-bukucloud-product',
            'label' => 'BukuCloud rebrand & SME features',
            'date' => '2026-06-01',
            'summary' => 'Rebrand to BukuCloud, receipt OCR, estimates, recurring invoices, products, and partner API.',
            'sections' => [
                'added' => [
                    'BukuCloud branding — logo, favicon, and mail templates',
                    'Receipt OCR — Tesseract upload (PDF/image) with optional Gemini enhancement',
                    'Estimates and recurring invoice templates',
                    'Products & Services catalog in revenue section',
                    'Extra seat billing for team users',
                    '14-day Corporate trial on SME signup with auto-downgrade',
                    'Partner API (/api/v1) with Bearer key and HMAC-signed writes',
                    'Soft email verification, billing history, and Resend mail transport',
                    'S3-backed tenant uploads and cached invoice PDFs',
                    'Payroll run form (journal paste)',
                ],
                'improved' => [
                    'OCR accuracy — preprocessing fallback, faster paths, raised nginx timeout',
                    'ECS entrypoint — auto migrations and plan/role sync on deploy',
                ],
                'fixed' => [
                    'CSRF 419 errors on Inertia uploads and receipt OCR',
                    'Trial balance formatting; delete invoice and bill line items',
                    'Docker case-sensitive imports and container startup',
                    'Transaction module and PDF preview issues',
                ],
            ],
        ],
        [
            'id' => '2026-05-platform-scale',
            'label' => 'Platform admin & multi-currency',
            'date' => '2026-05-04',
            'summary' => 'Super-admin control plane, chart of accounts seeding, multi-currency invoices, and transactional email.',
            'sections' => [
                'added' => [
                    'Super-admin platform control plane (tenants, plans, platform users)',
                    'Default chart of accounts seeded per tenant',
                    'Multi-currency on invoices with exchange rate field',
                    'Lifetime subscription interval and plan-audit alignment tests',
                    'Mailgun transactional email for invoices (later moved to Resend)',
                    'Company settings flow through to invoice PDF layout',
                    'Optional digital signature on PDFs (enable/disable)',
                    'YEN and expanded account types',
                ],
                'improved' => [
                    'PDF layout and invoice numbering',
                    'Automated seeders and deployment entrypoint with tenant migrations',
                ],
                'fixed' => [
                    'CSP asset blocks on custom domain behind AWS ALB',
                    'Migration crash on deploy; customer fields optional on create',
                    'Branding refresh after company settings update',
                ],
            ],
        ],
        [
            'id' => '2026-04-saas-deploy',
            'label' => 'SaaS subscriptions & cloud deploy',
            'date' => '2026-04-24',
            'summary' => 'Docker/ECS deployment, subscription plans, ToyyibPay billing, manual journal, and audit logs.',
            'sections' => [
                'added' => [
                    'Dockerized app with ECS Fargate deployment and GitHub Actions CI',
                    'Subscription plans and pricing tiers with Spatie role permissions',
                    'ToyyibPay integration for SaaS subscription payments',
                    'Manual journal entries with tracking',
                    'Audit logs with plan-based view restrictions',
                    'Company settings pre-populated on tenant setup',
                    'Reports with plan-based access and export restrictions',
                    'Collapsible sidebar navigation and status banners',
                ],
                'fixed' => [
                    'Trial balance empty after posting invoices and bills',
                    'Session expiry (419) handling',
                    'Corporate plan role permissions configured correctly',
                    'Miscellaneous accounting and UI fixes from early testing',
                ],
            ],
        ],
        [
            'id' => '2026-04-security-mobile',
            'label' => 'Security hardening & mobile UI',
            'date' => '2026-04-09',
            'summary' => 'Security headers, rate limiting, mobile-responsive layout, and migration cleanup.',
            'sections' => [
                'added' => [
                    'Security headers middleware and input sanitization',
                    'Rate limiting on auth and sensitive forms',
                    'Mobile-responsive layout and fixed mobile sidebar',
                ],
                'improved' => [
                    'Tenant migration structure refactored; redundant migrations removed',
                    'Bill module refactored',
                ],
                'fixed' => [
                    'Blurry sidebar on mobile',
                    'Duplicate success messages in UI',
                    'Issues found in early QA pass',
                ],
            ],
        ],
        [
            'id' => '2026-03-foundation',
            'label' => 'Foundation — first code',
            'date' => '2026-03-10',
            'summary' => 'Initial release — multi-tenant Laravel + Inertia accounting app for Malaysian SMEs.',
            'sections' => [
                'added' => [
                    'Multi-tenant architecture (Stancl Tenancy — database per tenant)',
                    'Core accounting — customers, suppliers, invoices, bills, chart of accounts',
                    'General ledger, trial balance, profit & loss, and balance sheet reports',
                    'Invoice and bill create/post/pay money path',
                    'Laravel Breeze authentication with team roles',
                    'Inertia.js + React frontend',
                ],
            ],
        ],
    ],
];
