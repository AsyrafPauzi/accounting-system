<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Estimate {{ $estimate->estimate_number }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #334155; line-height: 1.5; background: #fff; }

        .page { padding: 0; position: relative; min-height: 297mm; }

        .top-bar {
            background-color: #0f172a;
            color: #fff;
            display: table;
            width: 100%;
            border-bottom: 8px solid #334155;
        }
        .top-bar-left {
            display: table-cell;
            vertical-align: middle;
            width: 50%;
            padding: 30px 0 30px 40px;
        }
        .top-bar-right {
            display: table-cell;
            vertical-align: middle;
            width: 50%;
            text-align: right;
            padding: 30px 40px 30px 0;
            font-size: 10px;
        }
        .top-bar-right span { display: block; margin-bottom: 2px; }
        .doc-title { font-size: 28px; font-weight: bold; text-transform: uppercase; letter-spacing: 4px; line-height: 1; }
        .doc-subtitle { font-size: 10px; color: #94a3b8; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }

        .content { padding: 40px; }

        .addresses-section { display: table; width: 100%; margin-bottom: 40px; border-collapse: collapse; }
        .address-col { display: table-cell; width: 50%; vertical-align: top; }
        .address-col-inner { padding-right: 20px; }
        .address-col-last .address-col-inner { padding-right: 0; }

        .address-title {
            font-weight: 800; color: #0f172a; margin-bottom: 12px;
            font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;
        }

        .logo-text { font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .logo-headline { font-size: 8px; color: #64748b; letter-spacing: 1px; margin-bottom: 8px; text-transform: uppercase; }
        .address-details { font-size: 9px; color: #475569; line-height: 1.6; }

        .table-container { margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; }
        th {
            background-color: #f8fafc; color: #0f172a; text-align: left;
            padding: 12px; font-size: 9px; text-transform: uppercase;
            font-weight: 800; border-top: 1px solid #0f172a; border-bottom: 1px solid #0f172a;
        }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 9px; vertical-align: top; color: #1e293b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .bottom-section { display: table; width: 100%; margin-top: 20px; }
        .bottom-left { display: table-cell; width: 60%; vertical-align: top; padding-right: 40px; }
        .bottom-right { display: table-cell; width: 40%; vertical-align: top; }

        .instruction-box { margin-bottom: 25px; }
        .instruction-title { font-weight: bold; color: #0f172a; margin-bottom: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .instruction-text { font-size: 9px; color: #64748b; line-height: 1.6; }

        .totals-table { width: 100%; margin-top: 0; }
        .totals-table td { border: none; padding: 4px 0; }
        .totals-label { color: #64748b; text-transform: uppercase; font-size: 8px; font-weight: bold; }
        .totals-value { text-align: right; font-weight: 600; color: #0f172a; font-size: 10px; }
        .totals-row-total { border-top: 2px solid #0f172a; }
        .totals-row-total td { padding-top: 12px; }
        .totals-row-total .totals-value { font-weight: 800; font-size: 14px; color: #0f172a; }

        .stamp-box {
            background-color: #fef3c7;
            color: #78350f;
            padding: 15px;
            margin-top: 20px;
            width: 100%;
            border: 1px dashed #d97706;
        }
        .stamp-table { width: 100%; }
        .stamp-table td { border: none; padding: 0; color: #78350f; }
        .stamp-label { font-weight: 800; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .stamp-value { text-align: right; font-weight: 800; font-size: 12px; }

        .footer-note {
            margin-top: 60px; text-align: center; font-size: 8px;
            color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px;
        }
    </style>
</head>
<body>
    @php
        $currency = strtoupper((string) ($estimate->currency ?? 'MYR'));
    @endphp
    <div class="page">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="doc-title">Estimate</div>
                <div class="doc-subtitle">Quotation · Not a tax invoice</div>
            </div>
            <div class="top-bar-right">
                <span><strong>No:</strong> {{ $estimate->estimate_number }}</span>
                <span><strong>Issued:</strong> {{ \Carbon\Carbon::parse($estimate->issue_date)->format('d M Y') }}</span>
                @if($estimate->expiry_date)
                    <span><strong>Valid until:</strong> {{ \Carbon\Carbon::parse($estimate->expiry_date)->format('d M Y') }}</span>
                @endif
            </div>
        </div>

        <div class="content">
            <div class="addresses-section">
                <div class="address-col">
                    <div class="address-col-inner">
                        <div class="address-title">From</div>
                        <div>
                            <div class="logo-text">{{ $company['name'] }}</div>
                            @if(!empty($company['brn']))
                                <div class="logo-headline">Reg: {{ $company['brn'] }}</div>
                            @endif
                        </div>
                        <div class="address-details">
                            {{ $company['address'] ?? '' }}<br>
                            {{ $company['city'] ?? '' }}{{ !empty($company['state']) ? ', '.$company['state'] : '' }} {{ $company['zip'] ?? '' }}<br>
                            {{ $company['country'] ?? '' }}<br>
                            @if(!empty($company['phone'])) Tel: {{ $company['phone'] }}<br> @endif
                            @if(!empty($company['email'])) {{ $company['email'] }}<br> @endif
                            {{ $company['website'] ?? '' }}
                        </div>
                    </div>
                </div>
                <div class="address-col address-col-last">
                    <div class="address-col-inner">
                        <div class="address-title">Quote for</div>
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
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%">Description</th>
                            <th class="text-right" style="width: 15%">Rate ({{ $currency }})</th>
                            <th class="text-center" style="width: 10%">Qty</th>
                            <th class="text-center" style="width: 10%">Tax %</th>
                            <th class="text-right" style="width: 20%">Amount ({{ $currency }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($estimate->items as $item)
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
                    @if($estimate->customer_notes)
                    <div class="instruction-box">
                        <div class="instruction-title">Notes</div>
                        <div class="instruction-text">
                            {!! nl2br(e($estimate->customer_notes)) !!}
                        </div>
                    </div>
                    @endif
                    <div class="instruction-box">
                        <div class="instruction-title">How to accept</div>
                        <div class="instruction-text">
                            Reply to this email to confirm. Once accepted, we&apos;ll convert this
                            quote into a tax invoice for payment.
                        </div>
                    </div>
                </div>
                <div class="bottom-right">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Subtotal:</td>
                            <td class="totals-value">{{ number_format($estimate->amount_before_tax, 2) }} {{ $currency }}</td>
                        </tr>
                        @if(($estimate->discount_total ?? 0) > 0)
                        <tr>
                            <td class="totals-label">Discount:</td>
                            <td class="totals-value">-{{ number_format($estimate->discount_total, 2) }} {{ $currency }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="totals-label">Tax Total:</td>
                            <td class="totals-value">{{ number_format($estimate->tax_amount, 2) }} {{ $currency }}</td>
                        </tr>
                        @if(($estimate->shipping_amount ?? 0) > 0)
                        <tr>
                            <td class="totals-label">Shipping:</td>
                            <td class="totals-value">{{ number_format($estimate->shipping_amount, 2) }} {{ $currency }}</td>
                        </tr>
                        @endif
                        <tr class="totals-row-total">
                            <td class="totals-label">Quote Total:</td>
                            <td class="totals-value">{{ number_format($estimate->total_amount, 2) }} {{ $currency }}</td>
                        </tr>
                    </table>

                    <div class="stamp-box">
                        <table class="stamp-table">
                            <tr>
                                <td class="stamp-label">Estimate · Not yet billed</td>
                                <td class="stamp-value">{{ ucfirst($estimate->status) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="footer-note">
                This estimate is valid until the date shown above. Prices and availability may
                change after that date. This is a computer-generated document; no physical
                signature required.
            </div>
        </div>
    </div>
</body>
</html>
