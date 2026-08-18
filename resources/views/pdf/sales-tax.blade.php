<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Tax Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; line-height: 1.4; }
        .page { padding: 28px 32px; max-width: 210mm; }
        .header { display: table; width: 100%; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #0f172a; }
        .header-left { display: table-cell; width: 55%; vertical-align: top; }
        .header-right { display: table-cell; width: 45%; text-align: right; vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
        .company-address { font-size: 9px; color: #475569; }
        .report-title { font-size: 20px; font-weight: bold; color: #0f172a; }
        .report-period, .notice { font-size: 9px; color: #64748b; margin-top: 6px; }
        .notice { margin-bottom: 14px; font-style: italic; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 16px; }
        .summary td { width: 20%; padding: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; vertical-align: top; }
        .summary-label { font-size: 7px; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .summary-value { font-size: 12px; font-weight: bold; }
        .section-title { font-size: 11px; font-weight: bold; color: #334155; margin: 14px 0 8px; }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .report-table th { text-align: left; padding: 7px 8px; background: #0f172a; color: white; font-size: 8px; text-transform: uppercase; }
        .report-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right !important; }
        .currency { font-family: DejaVu Sans Mono, monospace; }
        .counts { color: #64748b; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $company['name'] ?? 'Company' }}</div>
                <div class="company-address">{!! nl2br(e($company['address'] ?? '')) !!}</div>
            </div>
            <div class="header-right">
                <div class="report-title">Sales Tax Report</div>
                <div class="report-period">From {{ \Carbon\Carbon::parse($pack['period_from'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($pack['period_to'])->format('d M Y') }}</div>
            </div>
        </div>

        <div class="notice">SST period pack — figures for your return, not a filed form.</div>

        <table class="summary">
            <tr>
                <td><div class="summary-label">Output tax</div><div class="summary-value currency">{{ number_format($pack['output_tax'], 2) }}</div></td>
                <td><div class="summary-label">Input tax</div><div class="summary-value currency">{{ number_format($pack['input_tax'], 2) }}</div></td>
                <td><div class="summary-label">Net tax</div><div class="summary-value currency">{{ number_format($pack['net_tax'], 2) }}</div></td>
                <td><div class="summary-label">Taxable sales</div><div class="summary-value currency">{{ number_format($pack['taxable_sales'], 2) }}</div></td>
                <td><div class="summary-label">Exempt sales</div><div class="summary-value currency">{{ number_format($pack['exempt_sales'], 2) }}</div></td>
            </tr>
        </table>

        <div class="section-title">Sales by tax rate ({{ $base_currency }})</div>
        <table class="report-table">
            <thead>
                <tr><th>Rate</th><th class="text-right">Taxable</th><th class="text-right">Tax</th><th class="text-right">Invoices</th></tr>
            </thead>
            <tbody>
                @forelse($by_rate as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right currency">{{ number_format($row['taxable'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['tax_collected'], 2) }}</td>
                    <td class="text-right">{{ $row['invoice_count'] }}</td>
                </tr>
                @empty
                <tr><td colspan="4">No invoice lines in this period.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">MyInvois submission gaps</div>
        <div class="counts">
            Missing: {{ $gap_counts['missing'] }} · Pending: {{ $gap_counts['pending'] }} · Rejected/invalid: {{ $gap_counts['rejected'] }}
            @if(array_sum($gap_counts) > count($myinvois_gaps)) · First 200 shown @endif
        </div>
        <table class="report-table">
            <thead>
                <tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Status</th><th class="text-right">Total</th></tr>
            </thead>
            <tbody>
                @forelse($myinvois_gaps as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['issue_date'])->format('d M Y') }}</td>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['reason'] }}</td>
                    <td class="text-right currency">{{ number_format($row['total'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5">No MyInvois gaps in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
