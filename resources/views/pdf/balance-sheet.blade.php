<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Balance Sheet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        .page { padding: 28px 32px; max-width: 210mm; }
        .header { display: table; width: 100%; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #0f172a; }
        .header-left { display: table-cell; width: 55%; vertical-align: top; }
        .header-right { display: table-cell; width: 45%; text-align: right; vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
        .company-address { font-size: 9px; color: #475569; }
        .report-title { font-size: 20px; font-weight: bold; color: #0f172a; }
        .report-period { font-size: 10px; color: #64748b; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .report-table th { text-align: left; padding: 8px 10px; background: #0f172a; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .report-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .report-table .text-right { text-align: right; }
        .section-title { font-size: 11px; font-weight: bold; color: #334155; margin-top: 14px; margin-bottom: 6px; }
        .totals-row { font-weight: bold; background: #f1f5f9; }
        .equation { margin-top: 20px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; font-weight: bold; font-size: 11px; }
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
                <div class="report-title">Balance Sheet</div>
                <div class="report-period">As at {{ \Carbon\Carbon::parse($as_at_date)->format('d M Y') }}</div>
            </div>
        </div>

        <div class="section-title">Assets</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($asset_accounts as $row)
                <tr>
                    <td><span style="font-family: DejaVu Sans Mono;">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="text-right currency">{{ number_format($row['amount'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2">No asset balances.</td></tr>
                @endforelse
                <tr class="totals-row">
                    <td>Total assets</td>
                    <td class="text-right currency">{{ number_format($total_assets, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Liabilities</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liability_accounts as $row)
                <tr>
                    <td><span style="font-family: DejaVu Sans Mono;">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="text-right currency">{{ number_format($row['amount'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2">No liability balances.</td></tr>
                @endforelse
                <tr class="totals-row">
                    <td>Total liabilities</td>
                    <td class="text-right currency">{{ number_format($total_liabilities, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Equity</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equity_accounts as $row)
                <tr>
                    <td><span style="font-family: DejaVu Sans Mono;">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="text-right currency">{{ number_format($row['amount'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2">No equity balances.</td></tr>
                @endforelse
                <tr class="totals-row">
                    <td>Total equity</td>
                    <td class="text-right currency">{{ number_format($total_equity, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="equation">
            Assets = Liabilities + Equity &nbsp; &nbsp;
            <span class="currency">{{ number_format($total_assets, 2) }}</span> = <span class="currency">{{ number_format($total_liabilities, 2) }}</span> + <span class="currency">{{ number_format($total_equity, 2) }}</span> = <span class="currency">{{ number_format($total_liabilities_and_equity, 2) }}</span> MYR
            @if($balanced)
                <span style="color: #065f46; margin-left: 8px;">(Balanced)</span>
            @endif
        </div>
    </div>
</body>
</html>
