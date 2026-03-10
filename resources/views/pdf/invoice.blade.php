<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        .page { padding: 28px 32px; max-width: 210mm; }
        .header { display: table; width: 100%; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 2px solid #0f172a; }
        .header-left { display: table-cell; width: 55%; vertical-align: top; }
        .header-right { display: table-cell; width: 45%; text-align: right; vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 8px; }
        .company-address { font-size: 9px; color: #475569; }
        .invoice-title { font-size: 24px; font-weight: bold; color: #0f172a; letter-spacing: -0.5px; }
        .invoice-meta { margin-top: 16px; font-size: 9px; }
        .invoice-meta div { margin-bottom: 4px; }
        .meta-label { color: #64748b; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; margin-top: 4px; }
        .status-draft { background: #f1f5f9; color: #64748b; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-partially { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-void { background: #fee2e2; color: #991b1b; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .bill-to { padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; }
        .customer-name { font-weight: bold; font-size: 11px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        .items-table th { text-align: left; padding: 10px 8px; background: #0f172a; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .items-table td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }
        .totals { margin-top: 24px; width: 280px; margin-left: auto; }
        .totals-row { display: table; width: 100%; padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        .totals-label { display: table-cell; color: #64748b; }
        .totals-value { display: table-cell; text-align: right; font-weight: 500; }
        .totals-row.grand { font-size: 12px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #0f172a; padding: 10px 0; margin-top: 4px; }
        .notes { margin-top: 24px; padding: 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; font-size: 9px; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #64748b; }
        .footer-grid { display: table; width: 100%; }
        .footer-cell { display: table-cell; width: 33%; vertical-align: top; padding-right: 12px; }
        .currency { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
    <div class="page">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $company['name'] }}</div>
                <div class="company-address">
                    {{ $company['address'] }}<br>
                    {{ $company['city'] }}, {{ $company['state'] }} {{ $company['zip'] }}<br>
                    {{ $company['country'] }}
                    @if($company['phone'])
                        <br>Tel: {{ $company['phone'] }}
                    @endif
                    @if($company['email'])
                        <br>Email: {{ $company['email'] }}
                    @endif
                    @if($company['website'])
                        <br>Web: {{ $company['website'] }}
                    @endif
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">TAX INVOICE</div>
                <div class="invoice-meta">
                    <div><span class="meta-label">Invoice No:</span> {{ $invoice->invoice_number }}</div>
                    <div><span class="meta-label">Issue Date:</span> {{ \Carbon\Carbon::parse($invoice->issue_date)->format('d M Y') }}</div>
                    @if($invoice->due_date)
                        <div><span class="meta-label">Due Date:</span> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</div>
                    @endif
                    <div><span class="meta-label">MSIC:</span> {{ $invoice->msic_code }}</div>
                    <div><span class="status status-{{ str_replace(' ', '-', strtolower($invoice->status)) }}">{{ strtoupper($invoice->status) }}</span></div>
                </div>
            </div>
        </div>

        {{-- Bill To --}}
        <div class="section">
            <div class="section-title">Bill To</div>
            <div class="bill-to">
                <div class="customer-name">{{ $customer->name }}</div>
                @if($customer->billing_street || $customer->billing_city)
                    <div>{{ $customer->billing_street }}</div>
                    <div>{{ $customer->billing_city }}{{ $customer->billing_state ? ', ' . $customer->billing_state : '' }} {{ $customer->billing_zip ?? '' }}</div>
                    @if($customer->billing_country)
                        <div>{{ $customer->billing_country }}</div>
                    @endif
                @endif
                @if($customer->tin)
                    <div style="margin-top:6px;"><span class="meta-label">TIN:</span> {{ $customer->tin }}</div>
                @endif
                @if($customer->brn)
                    <div><span class="meta-label">BRN:</span> {{ $customer->brn }}</div>
                @endif
            </div>
        </div>

        {{-- Line Items --}}
        <div class="section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:4%">#</th>
                        <th style="width:40%">Description</th>
                        <th class="text-center" style="width:10%">Qty</th>
                        <th class="text-right" style="width:14%">Unit Price (MYR)</th>
                        <th class="text-center" style="width:8%">Tax %</th>
                        <th class="text-right" style="width:12%">Discount</th>
                        <th class="text-right" style="width:12%">Amount (MYR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $index => $item)
                    @php
                        $lineTotal = ($item->quantity * $item->unit_price) - ($item->discount_amount ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                        <td class="text-right currency">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-center">{{ number_format($item->tax_rate, 0) }}%</td>
                        <td class="text-right currency">{{ number_format($item->discount_amount ?? 0, 2) }}</td>
                        <td class="text-right currency">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="totals">
            <div class="totals-row">
                <span class="totals-label">Subtotal</span>
                <span class="totals-value currency">{{ number_format($invoice->amount_before_tax, 2) }} MYR</span>
            </div>
            @if(($invoice->discount_total ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Discount</span>
                <span class="totals-value currency">-{{ number_format($invoice->discount_total, 2) }} MYR</span>
            </div>
            @endif
            @if(($invoice->tax_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Tax (SST)</span>
                <span class="totals-value currency">{{ number_format($invoice->tax_amount, 2) }} MYR</span>
            </div>
            @endif
            @if(($invoice->shipping_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Shipping</span>
                <span class="totals-value currency">{{ number_format($invoice->shipping_amount, 2) }} MYR</span>
            </div>
            @endif
            @if(($invoice->rounding_adjustment ?? 0) != 0)
            <div class="totals-row">
                <span class="totals-label">Rounding (5-sen)</span>
                <span class="totals-value currency">{{ number_format($invoice->rounding_adjustment, 2) }} MYR</span>
            </div>
            @endif
            <div class="totals-row grand">
                <span class="totals-label">TOTAL</span>
                <span class="totals-value currency">{{ number_format($invoice->total_amount, 2) }} MYR</span>
            </div>
        </div>

        @if($invoice->amount_paid > 0)
        <div class="totals" style="margin-top:8px;">
            <div class="totals-row">
                <span class="totals-label">Amount Paid</span>
                <span class="totals-value currency">{{ number_format($invoice->amount_paid, 2) }} MYR</span>
            </div>
            @if($invoice->status === 'paid')
            <div class="totals-row">
                <span class="totals-label">Balance Due</span>
                <span class="totals-value currency">0.00 MYR</span>
            </div>
            @else
            <div class="totals-row">
                <span class="totals-label">Balance Due</span>
                <span class="totals-value currency">{{ number_format($invoice->total_amount - $invoice->amount_paid, 2) }} MYR</span>
            </div>
            @endif
        </div>
        @endif

        @if($invoice->customer_notes)
        <div class="notes">
            <strong>Notes:</strong><br>
            {!! nl2br(e($invoice->customer_notes)) !!}
        </div>
        @endif

        {{-- Footer - LHDN / Regulatory --}}
        <div class="footer">
            <div class="footer-grid">
                <div class="footer-cell">
                    @if($company['tin'])
                        <strong>Tax ID (TIN):</strong> {{ $company['tin'] }}
                    @else
                        <em>Set INVOICE_COMPANY_TIN in .env</em>
                    @endif
                </div>
                <div class="footer-cell">
                    @if($company['brn'])
                        <strong>Business Registration (BRN):</strong> {{ $company['brn'] }}
                    @else
                        <em>Set INVOICE_COMPANY_BRN in .env</em>
                    @endif
                </div>
                <div class="footer-cell">
                    This is a computer-generated tax invoice. No signature required.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
