<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.5; background: #fff; }
        
        .page { padding: 0; position: relative; min-height: 297mm; }
        
        /* Top Header Bar - Black & White */
        .top-bar {
            background-color: #000;
            color: #fff;
            padding: 20px 40px;
            display: table;
            width: 100%;
        }
        .top-bar-left { display: table-cell; vertical-align: middle; width: 50%; }
        .top-bar-right { display: table-cell; vertical-align: middle; width: 50%; text-align: right; font-size: 9px; }
        .top-bar-right span { margin-left: 20px; }
        .invoice-title { font-size: 32px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; }
        
        .content { padding: 40px; }
        
        /* Company & Addresses */
        .addresses-section { display: table; width: 100%; margin-bottom: 40px; }
        .address-col { display: table-cell; width: 33.33%; vertical-align: top; padding-right: 15px; }
        
        .logo-box { margin-bottom: 12px; }
        .logo-text { font-size: 22px; font-weight: 900; color: #000; text-transform: uppercase; border-bottom: 3px solid #000; display: inline-block; padding-bottom: 2px; }
        .logo-headline { font-size: 8px; color: #666; letter-spacing: 3px; margin-top: 4px; text-transform: uppercase; }
        
        .address-title { font-weight: bold; color: #000; margin-bottom: 8px; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #eee; padding-bottom: 2px; }
        .address-details { font-size: 9px; color: #333; line-height: 1.5; }
        
        /* Table Styling */
        .table-container { margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; }
        th { 
            background-color: #f1f5f9; 
            color: #000; 
            text-align: left; 
            padding: 12px; 
            font-size: 9px; 
            text-transform: uppercase;
            font-weight: 800;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        td { 
            padding: 12px; 
            border-bottom: 1px solid #eee; 
            font-size: 9px;
            vertical-align: top;
            color: #111;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Summary Section */
        .bottom-section { display: table; width: 100%; margin-top: 20px; }
        .bottom-left { display: table-cell; width: 60%; vertical-align: top; }
        .bottom-right { display: table-cell; width: 40%; vertical-align: top; }
        
        .instruction-box { margin-bottom: 25px; }
        .instruction-title { font-weight: bold; color: #000; margin-bottom: 10px; font-size: 10px; text-transform: uppercase; }
        .instruction-text { font-size: 9px; color: #444; line-height: 1.6; }
        
        /* Totals */
        .totals-table { width: 100%; border-top: 2px solid #000; padding-top: 10px; }
        .totals-table td { border: none; padding: 6px 0; }
        .totals-label { color: #555; text-transform: uppercase; font-size: 8px; font-weight: bold; }
        .totals-value { text-align: right; font-weight: 600; color: #000; font-size: 10px; }
        .totals-row-total { border-top: 1px solid #000; margin-top: 5px; padding-top: 10px; }
        .totals-row-total .totals-value { font-weight: 800; font-size: 12px; }
        
        .balance-box { 
            background-color: #000; 
            color: #fff; 
            padding: 15px; 
            margin-top: 20px;
            display: table;
            width: 100%;
        }
        .balance-label { display: table-cell; font-weight: 800; font-size: 12px; text-transform: uppercase; }
        .balance-value { display: table-cell; text-align: right; font-weight: 800; font-size: 14px; }
        
        /* Signatures */
        .signature-section { 
            margin-top: 80px; 
            display: table; 
            width: 100%; 
        }
        .signature-col { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #000; width: 75%; margin: 0 auto 10px; }
        .signature-name { font-weight: bold; font-size: 10px; color: #000; text-transform: uppercase; }
        .signature-label { font-size: 8px; color: #666; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
        
        .footer-note {
            margin-top: 60px;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="invoice-title">Invoice</div>
            </div>
            <div class="top-bar-right">
                <span><strong>No:</strong> {{ $invoice->invoice_number }}</span>
                <span><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->issue_date)->format('d M Y') }}</span>
                @if($invoice->due_date)
                    <span><strong>Due:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</span>
                @endif
            </div>
        </div>

        <div class="content">
            <div class="addresses-section">
                <div class="address-col">
                    <div class="logo-box">
                        <div class="logo-text">{{ $company['name'] }}</div>
                        @if(!empty($company['tin']) || !empty($company['brn']))
                            <div class="logo-headline">Reg: {{ $company['brn'] ?? 'N/A' }}</div>
                        @endif
                    </div>
                    <div class="address-details">
                        {{ $company['address'] }}<br>
                        {{ $company['city'] }}, {{ $company['state'] }} {{ $company['zip'] }}<br>
                        {{ $company['country'] }}<br>
                        @if($company['phone']) Tel: {{ $company['phone'] }}<br> @endif
                        @if($company['email']) {{ $company['email'] }}<br> @endif
                        {{ $company['website'] }}
                    </div>
                </div>
                <div class="address-col">
                    <div class="address-title">Bill to</div>
                    <div class="address-details">
                        <strong>{{ $customer->name }}</strong><br>
                        @if($customer->billing_street || $customer->billing_city)
                            {{ $customer->billing_street }}<br>
                            {{ $customer->billing_city }}{{ $customer->billing_state ? ', ' . $customer->billing_state : '' }} {{ $customer->billing_zip ?? '' }}<br>
                            {{ $customer->billing_country }}
                        @endif
                        @if($customer->email)
                            <br>{{ $customer->email }}
                        @endif
                    </div>
                </div>
                <div class="address-col">
                    <div class="address-title">Ship to</div>
                    <div class="address-details">
                        @if($customer->shipping_street || $customer->shipping_city)
                            {{ $customer->shipping_street }}<br>
                            {{ $customer->shipping_city }}{{ $customer->shipping_state ? ', ' . $customer->shipping_state : '' }} {{ $customer->shipping_zip ?? '' }}<br>
                            {{ $customer->shipping_country }}
                        @else
                            <em>Same as billing</em>
                        @endif
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%">Description</th>
                            <th class="text-right" style="width: 15%">Rate ({{ $company['currency'] ?? 'MYR' }})</th>
                            <th class="text-center" style="width: 10%">Qty</th>
                            <th class="text-center" style="width: 10%">Tax %</th>
                            <th class="text-right" style="width: 20%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        @php
                            $lineTotal = ($item->quantity * $item->unit_price) - ($item->discount_amount ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                            <td class="text-center">{{ number_format($item->tax_rate, 2) }}%</td>
                            <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bottom-section">
                <div class="bottom-left">
                    <div class="instruction-box">
                        <div class="instruction-title">Payment information</div>
                        <div class="instruction-text">
                            <strong>Bank Transfer</strong><br>
                            Payable to: {{ $company['name'] }}<br>
                            @if($company['tin']) Tax ID (TIN): {{ $company['tin'] }} @endif
                        </div>
                    </div>
                    
                    @if($invoice->customer_notes)
                    <div class="instruction-box">
                        <div class="instruction-title">Notes</div>
                        <div class="instruction-text">
                            {!! nl2br(e($invoice->customer_notes)) !!}
                        </div>
                    </div>
                    @endif
                </div>
                <div class="bottom-right">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Subtotal:</td>
                            <td class="totals-value">{{ number_format($invoice->amount_before_tax, 2) }}</td>
                        </tr>
                        @if(($invoice->discount_total ?? 0) > 0)
                        <tr>
                            <td class="totals-label">Discount:</td>
                            <td class="totals-value">-{{ number_format($invoice->discount_total, 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="totals-label">Tax Total:</td>
                            <td class="totals-value">{{ number_format($invoice->tax_amount, 2) }}</td>
                        </tr>
                        @if(($invoice->shipping_amount ?? 0) > 0)
                        <tr>
                            <td class="totals-label">Shipping:</td>
                            <td class="totals-value">{{ number_format($invoice->shipping_amount, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="totals-row-total">
                            <td class="totals-label">Grand Total:</td>
                            <td class="totals-value">{{ number_format($invoice->total_amount, 2) }} {{ $company['currency'] ?? 'MYR' }}</td>
                        </tr>
                        <tr>
                            <td class="totals-label">Paid to Date:</td>
                            <td class="totals-value">{{ number_format($invoice->amount_paid, 2) }}</td>
                        </tr>
                    </table>
                    
                    <div class="balance-box">
                        <div class="balance-label">Balance Due:</div>
                        <div class="balance-value">{{ number_format($invoice->total_amount - $invoice->amount_paid, 2) }} {{ $company['currency'] ?? 'MYR' }}</div>
                    </div>
                </div>
            </div>

            <div class="signature-section">
                <div class="signature-col">
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $customer->name }}</div>
                    <div class="signature-label">Customer Signature</div>
                </div>
                <div class="signature-col">
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $company['name'] }}</div>
                    <div class="signature-label">Authorized Signature</div>
                </div>
            </div>
            
            <div class="footer-note">
                This is a computer-generated document. No physical signature required.
            </div>
        </div>
    </div>
</body>
</html>


