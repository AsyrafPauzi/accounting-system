<?php

namespace App\Services\Copilot;

/**
 * Tool metadata: risk (read | draft | high) and the Spatie permission required.
 */
class CopilotCatalog
{
    public const RISK_READ = 'read';
    public const RISK_DRAFT = 'draft';
    public const RISK_HIGH = 'high';

    /**
     * @return array<string, array{risk: string, permission: string, description: string, parameters: array<string, mixed>}>
     */
    public static function tools(): array
    {
        return [
            'ar_aging' => [
                'risk' => self::RISK_READ,
                'permission' => 'reports.aged-reports',
                'description' => 'AR aging buckets (current, 1-30, 31-60, 61-90, 90+) for unpaid invoices.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            'overdue_invoices' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.view',
                'description' => 'List overdue unpaid / partially paid invoices with remaining balance.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 25'],
                    ],
                ],
            ],
            'list_invoices' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.view',
                'description' => 'Search invoices by number, status, or customer name.',
                'parameters' => self::searchParams(['status' => ['type' => 'string']]),
            ],
            'show_invoice' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.view',
                'description' => 'Show one invoice by id or invoice_number.',
                'parameters' => self::idOrNumber('invoice_id', 'invoice_number'),
            ],
            'list_estimates' => [
                'risk' => self::RISK_READ,
                'permission' => 'estimates.view',
                'description' => 'List recent estimates / quotations.',
                'parameters' => self::searchParams(),
            ],
            'show_estimate' => [
                'risk' => self::RISK_READ,
                'permission' => 'estimates.view',
                'description' => 'Show one estimate by id or estimate_number.',
                'parameters' => self::idOrNumber('estimate_id', 'estimate_number'),
            ],
            'list_credit_notes' => [
                'risk' => self::RISK_READ,
                'permission' => 'credit-notes.view',
                'description' => 'List credit notes.',
                'parameters' => self::searchParams(),
            ],
            'list_debit_notes' => [
                'risk' => self::RISK_READ,
                'permission' => 'debit-notes.view',
                'description' => 'List debit notes.',
                'parameters' => self::searchParams(),
            ],
            'list_sales_orders' => [
                'risk' => self::RISK_READ,
                'permission' => 'sales-orders.view',
                'description' => 'List sales orders.',
                'parameters' => self::searchParams(),
            ],
            'list_delivery_orders' => [
                'risk' => self::RISK_READ,
                'permission' => 'delivery-orders.view',
                'description' => 'List delivery orders.',
                'parameters' => self::searchParams(),
            ],
            'list_recurring_invoices' => [
                'risk' => self::RISK_READ,
                'permission' => 'recurring-invoices.view',
                'description' => 'List recurring invoice templates.',
                'parameters' => self::searchParams(),
            ],
            'list_customers' => [
                'risk' => self::RISK_READ,
                'permission' => 'customers.view',
                'description' => 'Search customers by name, email, or code.',
                'parameters' => self::searchParams(),
            ],
            'show_customer' => [
                'risk' => self::RISK_READ,
                'permission' => 'customers.view',
                'description' => 'Show one customer by id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['customer_id' => ['type' => 'integer']],
                    'required' => ['customer_id'],
                ],
            ],
            'list_products' => [
                'risk' => self::RISK_READ,
                'permission' => 'products.view',
                'description' => 'Search products / services catalogue.',
                'parameters' => self::searchParams(),
            ],
            'customer_statement_snapshot' => [
                'risk' => self::RISK_READ,
                'permission' => 'customer-statements.view',
                'description' => 'Balance-forward statement snapshot for a customer (opening, charges, payments, closing).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['customer_id'],
                ],
            ],
            'download_customer_statement_pdf' => [
                'risk' => self::RISK_READ,
                'permission' => 'customer-statements.view',
                'description' => 'Build a customer statement PDF download link for the given date range. Use this when the user asks for a statement PDF, report, or download. Runs immediately — do not email. Reply with the pdf_url as a markdown link.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['customer_id'],
                ],
            ],
            'sales_report_snapshot' => [
                'risk' => self::RISK_READ,
                'permission' => 'reports.sales',
                'description' => 'Sales totals by customer for a date range.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                ],
            ],
            'document_trail' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.view',
                'description' => 'Estimate → SO → DO → invoice → CN trail. Pass invoice_id or estimate_id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_id' => ['type' => 'integer'],
                        'estimate_id' => ['type' => 'integer'],
                    ],
                ],
            ],
            'explain_journal' => [
                'risk' => self::RISK_READ,
                'permission' => 'journal.view',
                'description' => 'Explain GL journals linked to an invoice, credit note, or debit note.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_type' => ['type' => 'string', 'enum' => ['invoice', 'credit_note', 'debit_note']],
                        'document_id' => ['type' => 'integer'],
                    ],
                    'required' => ['document_type', 'document_id'],
                ],
            ],
            'suggest_lhdn_classification' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.view',
                'description' => 'Suggest a MyInvois classification code and SST rate. Does not write.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                    ],
                    'required' => ['description'],
                ],
            ],
            'draft_invoice' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'invoices.create',
                'description' => 'Propose a draft invoice. Confirm to save as draft (unposted).',
                'parameters' => self::draftDocParams(true),
            ],
            'draft_estimate' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'estimates.create',
                'description' => 'Propose a draft estimate. Confirm to save as draft.',
                'parameters' => self::draftDocParams(false),
            ],
            'draft_bill_from_receipt' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'bills.create',
                'description' => 'Propose a supplier bill draft from OCR fields. Always pass vendor_name from the receipt. On Confirm: links an existing supplier by name (case-insensitive) or creates one, then saves the bill draft linked to that supplier.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'supplier_id' => ['type' => 'integer'],
                        'vendor_name' => ['type' => 'string', 'description' => 'Supplier / vendor name from the receipt (required if supplier_id missing)'],
                        'vendor_email' => ['type' => 'string'],
                        'vendor_phone' => ['type' => 'string'],
                        'vendor_tin' => ['type' => 'string'],
                        'vendor_brn' => ['type' => 'string'],
                        'bill_date' => ['type' => 'string'],
                        'due_date' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                        'tax_amount' => ['type' => 'number'],
                        'receipt_path' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                    'required' => ['items', 'vendor_name'],
                ],
            ],
            'draft_invoice_from_receipt' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'invoices.create',
                'description' => 'Propose an invoice draft from receipt/PO vision JSON mapped to invoice lines. Confirm to save draft.',
                'parameters' => self::draftDocParams(true),
            ],
            'draft_customer' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'customers.create',
                'description' => 'Propose a new customer. Confirm to save. Never invent TIN or BRN.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'tin' => ['type' => 'string'],
                        'brn' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'draft_product' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'products.create',
                'description' => 'Propose a catalogue product/service. Confirm to save.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'unit_price' => ['type' => 'number'],
                        'tax_rate' => ['type' => 'number'],
                        'account_code' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'unit_price'],
                ],
            ],
            'draft_sales_order' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'sales-orders.create',
                'description' => 'Propose a sales order. Confirm to save.',
                'parameters' => self::draftDocParams(false),
            ],
            'draft_recurring_invoice' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'recurring-invoices.create',
                'description' => 'Propose a recurring invoice template. Confirm to save.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => array_merge(self::draftDocParams(true)['properties'], [
                        'name' => ['type' => 'string'],
                        'cadence' => ['type' => 'string', 'enum' => ['weekly', 'monthly', 'quarterly', 'yearly']],
                        'interval' => ['type' => 'integer'],
                        'start_date' => ['type' => 'string'],
                    ]),
                    'required' => ['customer_id', 'items', 'cadence', 'start_date'],
                ],
            ],
            'post_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.post',
                'description' => 'Post a draft invoice to the GL. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['invoice_id' => ['type' => 'integer']], 'required' => ['invoice_id']],
            ],
            'void_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.void',
                'description' => 'Void a posted invoice. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['invoice_id' => ['type' => 'integer']], 'required' => ['invoice_id']],
            ],
            'record_invoice_payment' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Record a payment against an invoice. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'number'],
                        'payment_date' => ['type' => 'string'],
                        'bank_account_code' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                    ],
                    'required' => ['invoice_id', 'amount', 'payment_date', 'bank_account_code'],
                ],
            ],
            'reverse_invoice_payment' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Reverse an invoice payment. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'payment_id' => ['type' => 'integer'],
                    ],
                    'required' => ['payment_id'],
                ],
            ],
            'issue_credit_note' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'credit-notes.create',
                'description' => 'Issue a credit note (posts GL). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'invoice_id' => ['type' => 'integer'],
                        'reason_code' => ['type' => 'string'],
                        'reason_description' => ['type' => 'string'],
                        'issue_date' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                    'required' => ['customer_id', 'reason_code', 'items'],
                ],
            ],
            'apply_credit_note' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'credit-notes.create',
                'description' => 'Apply unapplied credit to an invoice (no second AR journal). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'credit_note_id' => ['type' => 'integer'],
                        'invoice_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'number'],
                    ],
                    'required' => ['credit_note_id', 'invoice_id', 'amount'],
                ],
            ],
            'refund_credit_note' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'credit-notes.create',
                'description' => 'Cash refund of unapplied credit. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'credit_note_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'number'],
                        'bank_account_code' => ['type' => 'string'],
                        'payment_date' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                    ],
                    'required' => ['credit_note_id', 'amount', 'bank_account_code', 'payment_date'],
                ],
            ],
            'issue_debit_note' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'debit-notes.create',
                'description' => 'Issue a debit note (posts GL). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'invoice_id' => ['type' => 'integer'],
                        'reason_code' => ['type' => 'string'],
                        'reason_description' => ['type' => 'string'],
                        'issue_date' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                    'required' => ['customer_id', 'items'],
                ],
            ],
            'receive_ar_deposit' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Receive a customer deposit (bank → 2250). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'number'],
                        'payment_date' => ['type' => 'string'],
                        'bank_account_code' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                    ],
                    'required' => ['customer_id', 'amount', 'payment_date', 'bank_account_code'],
                ],
            ],
            'apply_ar_deposit' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Apply deposit to invoice (2250 → 1100). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'deposit_id' => ['type' => 'integer'],
                        'invoice_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'number'],
                    ],
                    'required' => ['deposit_id', 'invoice_id', 'amount'],
                ],
            ],
            'refund_ar_deposit' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Refund leftover customer deposit. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'deposit_id' => ['type' => 'integer'],
                        'payment_date' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                    ],
                    'required' => ['deposit_id', 'payment_date'],
                ],
            ],
            'forfeit_ar_deposit' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.record-payment',
                'description' => 'Forfeit leftover customer deposit. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'deposit_id' => ['type' => 'integer'],
                        'date' => ['type' => 'string'],
                    ],
                    'required' => ['deposit_id', 'date'],
                ],
            ],
            'convert_estimate_to_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'estimates.convert',
                'description' => 'Convert estimate to a draft invoice. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['estimate_id' => ['type' => 'integer']], 'required' => ['estimate_id']],
            ],
            'generate_recurring_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'recurring-invoices.run',
                'description' => 'Generate one draft invoice from a recurring template. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['recurring_invoice_id' => ['type' => 'integer']], 'required' => ['recurring_invoice_id']],
            ],
            'email_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.email',
                'description' => 'Queue invoice email to the customer. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['invoice_id' => ['type' => 'integer']], 'required' => ['invoice_id']],
            ],
            'email_estimate' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'estimates.email',
                'description' => 'Queue estimate email to the customer. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['estimate_id' => ['type' => 'integer']], 'required' => ['estimate_id']],
            ],
            'send_invoice_reminder' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.email',
                'description' => 'Send an invoice payment reminder. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_id' => ['type' => 'integer'],
                        'offset' => ['type' => 'integer', 'description' => 'Days relative to due date, e.g. 0 for due today'],
                    ],
                    'required' => ['invoice_id'],
                ],
            ],
            'email_customer_statement' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'customer-statements.view',
                'description' => 'Email a customer statement PDF to the customer. ONLY when the user explicitly asks to email/send the statement. For PDF download/report requests use download_customer_statement_pdf instead. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'from' => ['type' => 'string'],
                        'to' => ['type' => 'string'],
                    ],
                    'required' => ['customer_id'],
                ],
            ],
            'myinvois_submit' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'myinvois.submit',
                'description' => 'Submit invoice/CN/DN to MyInvois. Requires Confirm. Never claim success without the service response.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_type' => ['type' => 'string', 'enum' => ['invoice', 'credit_note', 'debit_note']],
                        'document_id' => ['type' => 'integer'],
                    ],
                    'required' => ['document_type', 'document_id'],
                ],
            ],
            'myinvois_refresh' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'myinvois.submit',
                'description' => 'Refresh MyInvois status. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_type' => ['type' => 'string', 'enum' => ['invoice', 'credit_note', 'debit_note']],
                        'document_id' => ['type' => 'integer'],
                    ],
                    'required' => ['document_type', 'document_id'],
                ],
            ],
            'myinvois_cancel' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'myinvois.submit',
                'description' => 'Cancel a MyInvois submission. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_type' => ['type' => 'string', 'enum' => ['invoice', 'credit_note', 'debit_note']],
                        'document_id' => ['type' => 'integer'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['document_type', 'document_id', 'reason'],
                ],
            ],
            'draft_owner_expense_claim' => [
                'risk' => self::RISK_DRAFT,
                'permission' => 'bills.create',
                'description' => 'Draft a bill claiming reimbursement when the owner/staff paid an expense personally. Creates/finds supplier as claimant. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'amount' => ['type' => 'number'],
                        'claimant_name' => ['type' => 'string'],
                        'account_code' => ['type' => 'string', 'description' => 'Expense account, default 5000'],
                        'bill_date' => ['type' => 'string'],
                        'receipt_path' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['description', 'amount'],
                ],
            ],
            'list_team_members' => [
                'risk' => self::RISK_READ,
                'permission' => 'users.view',
                'description' => 'List team members for the current tenant (name, email, roles).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            'invite_team_member' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'users.create',
                'description' => 'Invite a team member when seats are available. Queues welcome email with password setup link. Requires Confirm. Never claim seat payment succeeded.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'role' => ['type' => 'string', 'enum' => ['admin', 'accountant', 'sales', 'viewer']],
                    ],
                    'required' => ['name', 'email', 'role'],
                ],
            ],
            'show_sales_order' => [
                'risk' => self::RISK_READ,
                'permission' => 'sales-orders.view',
                'description' => 'Show one sales order by id or so_number.',
                'parameters' => self::idOrNumber('sales_order_id', 'so_number'),
            ],
            'show_delivery_order' => [
                'risk' => self::RISK_READ,
                'permission' => 'delivery-orders.view',
                'description' => 'Show one delivery order by id or do_number.',
                'parameters' => self::idOrNumber('delivery_order_id', 'do_number'),
            ],
            'list_ar_deposits' => [
                'risk' => self::RISK_READ,
                'permission' => 'invoices.record-payment',
                'description' => 'List customer AR deposits with remaining open amounts.',
                'parameters' => self::searchParams([
                    'customer_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string'],
                ]),
            ],
            'deliver_sales_order' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'sales-orders.create',
                'description' => 'Create a delivery order from a sales order (partial quantities optional). Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sales_order_id' => ['type' => 'integer'],
                        'so_number' => ['type' => 'string'],
                        'quantities' => ['type' => 'object', 'description' => 'Map of sales_order_item_id => qty'],
                    ],
                ],
            ],
            'cancel_sales_order' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'sales-orders.edit',
                'description' => 'Cancel a sales order. Requires Confirm.',
                'parameters' => self::idOrNumber('sales_order_id', 'so_number'),
            ],
            'convert_sales_order_to_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.create',
                'description' => 'Convert a sales order to a draft invoice. Requires Confirm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sales_order_id' => ['type' => 'integer'],
                        'so_number' => ['type' => 'string'],
                        'quantities' => ['type' => 'object', 'description' => 'Map of sales_order_item_id => qty'],
                    ],
                ],
            ],
            'convert_delivery_order_to_invoice' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.create',
                'description' => 'Convert a delivery order to a draft invoice. Requires Confirm.',
                'parameters' => self::idOrNumber('delivery_order_id', 'do_number'),
            ],
            'return_delivery_order' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'delivery-orders.edit',
                'description' => 'Full return of an uninvoiced delivery order. Requires Confirm.',
                'parameters' => self::idOrNumber('delivery_order_id', 'do_number'),
            ],
            'email_sales_order' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.email',
                'description' => 'Queue sales order email to the customer. Requires Confirm.',
                'parameters' => self::idOrNumber('sales_order_id', 'so_number'),
            ],
            'email_delivery_order' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.email',
                'description' => 'Queue delivery order email to the customer. Requires Confirm.',
                'parameters' => self::idOrNumber('delivery_order_id', 'do_number'),
            ],
            'email_debit_note' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'invoices.email',
                'description' => 'Queue debit note email to the customer. Requires Confirm.',
                'parameters' => self::idOrNumber('debit_note_id', 'dn_number'),
            ],
            'delete_customer' => [
                'risk' => self::RISK_HIGH,
                'permission' => 'customers.delete',
                'description' => 'Delete a customer only if deletionBlockedReason is null. Requires Confirm.',
                'parameters' => ['type' => 'object', 'properties' => ['customer_id' => ['type' => 'integer']], 'required' => ['customer_id']],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function openaiTools(): array
    {
        $out = [];
        foreach (self::tools() as $name => $meta) {
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $meta['description'],
                    'parameters' => $meta['parameters'],
                ],
            ];
        }

        return $out;
    }

    public static function risk(string $name): string
    {
        return self::tools()[$name]['risk'] ?? self::RISK_HIGH;
    }

    public static function permission(string $name): ?string
    {
        return self::tools()[$name]['permission'] ?? null;
    }

    public static function exists(string $name): bool
    {
        return isset(self::tools()[$name]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function searchParams(array $extra = []): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
            ], $extra),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function idOrNumber(string $idKey, string $numberKey): array
    {
        return [
            'type' => 'object',
            'properties' => [
                $idKey => ['type' => 'integer'],
                $numberKey => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function draftDocParams(bool $invoice): array
    {
        $props = [
            'customer_id' => ['type' => 'integer'],
            'issue_date' => ['type' => 'string'],
            'currency' => ['type' => 'string'],
            'customer_notes' => ['type' => 'string'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'quantity' => ['type' => 'number'],
                        'unit_price' => ['type' => 'number'],
                        'tax_rate' => ['type' => 'number'],
                        'discount_amount' => ['type' => 'number'],
                        'account_code' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
        if ($invoice) {
            $props['due_date'] = ['type' => 'string'];
            $props['msic_code'] = ['type' => 'string'];
        } else {
            $props['expiry_date'] = ['type' => 'string'];
            $props['expected_date'] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'properties' => $props,
            'required' => ['customer_id', 'items'],
        ];
    }
}
