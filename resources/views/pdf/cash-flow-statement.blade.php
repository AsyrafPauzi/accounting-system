<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cash Flow Statement</title>
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
        .report-table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; }
        .report-table .text-right { text-align: right; }
        .section-title { font-size: 11px; font-weight: bold; color: #334155; margin-top: 12px; margin-bottom: 6px; text-transform: uppercase; }
        .totals-row { font-weight: bold; background: #f1f5f9; }
        .indent { padding-left: 24px; }
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
                <div class="report-title">Statement of Cash Flows</div>
                <div class="report-period">Indirect method (IAS 7)</div>
                <div class="report-period">From {{ \Carbon\Carbon::parse($date_from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($date_to)->format('d M Y') }}</div>
            </div>
        </div>

        <div class="section-title">Cash flows from operating activities</div>
        <table class="report-table">
            <tr><td>Net profit for the period</td><td class="text-right currency">{{ number_format($net_profit, 2) }}</td></tr>
            @foreach($operating_adjustments as $line)
            <tr><td class="indent">Change in {{ $line['name'] }}</td><td class="text-right currency">{{ number_format($line['amount'], 2) }}</td></tr>
            @endforeach
            <tr class="totals-row"><td>Net cash from operating activities</td><td class="text-right currency">{{ number_format($net_cash_operating, 2) }}</td></tr>
        </table>

        <div class="section-title">Cash flows from investing activities</div>
        <table class="report-table">
            @forelse($investing_lines as $line)
            <tr><td>Change in {{ $line['name'] }}</td><td class="text-right currency">{{ number_format($line['amount'], 2) }}</td></tr>
            @empty
            <tr><td colspan="2">No investing cash flows in this period.</td></tr>
            @endforelse
            <tr class="totals-row"><td>Net cash from investing activities</td><td class="text-right currency">{{ number_format($net_cash_investing, 2) }}</td></tr>
        </table>

        <div class="section-title">Cash flows from financing activities</div>
        <table class="report-table">
            @forelse($financing_lines as $line)
            <tr><td>Change in {{ $line['name'] }}</td><td class="text-right currency">{{ number_format($line['amount'], 2) }}</td></tr>
            @empty
            <tr><td colspan="2">No financing cash flows in this period.</td></tr>
            @endforelse
            <tr class="totals-row"><td>Net cash from financing activities</td><td class="text-right currency">{{ number_format($net_cash_financing, 2) }}</td></tr>
        </table>

        <div class="section-title">Reconciliation of cash</div>
        <table class="report-table">
            <tr><td>Net increase/(decrease) in cash</td><td class="text-right currency">{{ number_format($net_change_in_cash, 2) }}</td></tr>
            <tr><td>Cash at beginning of period</td><td class="text-right currency">{{ number_format($opening_cash, 2) }}</td></tr>
            <tr class="totals-row"><td>Cash at end of period</td><td class="text-right currency">{{ number_format($closing_cash, 2) }}</td></tr>
        </table>
    </div>
</body>
</html>
