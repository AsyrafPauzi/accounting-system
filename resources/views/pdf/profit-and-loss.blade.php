<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Profit &amp; Loss</title>
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
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .report-table th { text-align: left; padding: 8px 10px; background: #0f172a; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .report-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .report-table .text-right { text-align: right; }
        .section-title { font-size: 11px; font-weight: bold; color: #334155; margin-top: 16px; margin-bottom: 8px; }
        .totals-row { font-weight: bold; background: #f1f5f9; }
        .net-row { font-size: 12px; font-weight: bold; border-top: 2px solid #0f172a; padding-top: 10px; margin-top: 8px; }
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
                <div class="report-title">Profit &amp; Loss</div>
                <div class="report-period">From {{ \Carbon\Carbon::parse($date_from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($date_to)->format('d M Y') }}</div>
                @if(($basis ?? 'accrual') === 'cash')
                <div class="report-period">Cash basis</div>
                @endif
                @if($compare !== 'none')
                <div class="report-period">{{ $compare_label }}: {{ \Carbon\Carbon::parse($compare_from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($compare_to)->format('d M Y') }}</div>
                @endif
            </div>
        </div>

        <div class="section-title">Revenue (Income)</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">This period</th>
                    @if($compare !== 'none')
                    <th class="text-right">Compare</th>
                    <th class="text-right">Variance</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($revenue_accounts as $row)
                <tr>
                    <td><span style="font-family: DejaVu Sans Mono;">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="text-right currency">{{ number_format($row['amount'], 2) }}</td>
                    @if($compare !== 'none')
                    <td class="text-right currency">{{ number_format($row['compare_amount'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['variance'], 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $compare !== 'none' ? 4 : 2 }}">No revenue in this period.</td></tr>
                @endforelse
                <tr class="totals-row">
                    <td>Total revenue</td>
                    <td class="text-right currency">{{ number_format($total_revenue, 2) }}</td>
                    @if($compare !== 'none')
                    <td class="text-right currency">{{ number_format($compare_revenue, 2) }}</td>
                    <td class="text-right currency">{{ number_format($revenue_variance, 2) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>

        <div class="section-title">Expenses</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">This period</th>
                    @if($compare !== 'none')
                    <th class="text-right">Compare</th>
                    <th class="text-right">Variance</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($expense_accounts as $row)
                <tr>
                    <td><span style="font-family: DejaVu Sans Mono;">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="text-right currency">{{ number_format($row['amount'], 2) }}</td>
                    @if($compare !== 'none')
                    <td class="text-right currency">{{ number_format($row['compare_amount'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($row['variance'], 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $compare !== 'none' ? 4 : 2 }}">No expenses in this period.</td></tr>
                @endforelse
                <tr class="totals-row">
                    <td>Total expenses</td>
                    <td class="text-right currency">{{ number_format($total_expenses, 2) }}</td>
                    @if($compare !== 'none')
                    <td class="text-right currency">{{ number_format($compare_expenses, 2) }}</td>
                    <td class="text-right currency">{{ number_format($expenses_variance, 2) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>

        <table class="report-table">
            <tr class="net-row">
                <td>Net {{ $net_profit >= 0 ? 'profit' : 'loss' }}</td>
                <td class="text-right currency">{{ number_format($net_profit, 2) }} MYR</td>
                @if($compare !== 'none')
                <td class="text-right currency">{{ number_format($compare_net_profit, 2) }} MYR</td>
                <td class="text-right currency">{{ number_format($net_profit_variance, 2) }} MYR</td>
                @endif
            </tr>
        </table>
    </div>
</body>
</html>
