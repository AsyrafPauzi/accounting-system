<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>SST-02 Helper Summary</title>
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
        .notice { margin-bottom: 14px; font-style: italic; border: 1px solid #e2e8f0; background: #f8fafc; padding: 10px 12px; border-radius: 4px; }
        .section-title { font-size: 11px; font-weight: bold; color: #334155; margin: 14px 0 8px; }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .report-table th { text-align: left; padding: 7px 8px; background: #0f172a; color: white; font-size: 8px; text-transform: uppercase; }
        .report-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right !important; }
        .currency { font-family: DejaVu Sans Mono, monospace; }
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
                <div class="report-title">SST-02 Helper Summary</div>
                <div class="report-period">From {{ \Carbon\Carbon::parse($period_from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($period_to)->format('d M Y') }}</div>
            </div>
        </div>

        <div class="notice">
            Filing helper only — these figures are for your SST-02 / SST-02A return preparation.
            Verify all amounts against source documents before submitting to MyTax.
        </div>

        <div class="section-title">Totals by tax code ({{ $base_currency }})</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Tax code</th>
                    <th class="text-right">Taxable sales</th>
                    <th class="text-right">Output tax</th>
                    <th class="text-right">Taxable purchases</th>
                    <th class="text-right">Input tax</th>
                    <th class="text-right">Net tax</th>
                    <th class="text-right">CN adj.</th>
                    <th class="text-right">DN adj.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td>{{ $row['tax_code'] }}</td>
                    <td class="text-right currency">{{ number_format($row['taxable_sales'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['output_tax'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['taxable_purchases'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['input_tax'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['net_tax'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['cn_adjustment'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['dn_adjustment'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
