<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>General Ledger Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; line-height: 1.35; }
        .page { padding: 20px 24px; }
        .header { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #0f172a; }
        .company-name { font-size: 14px; font-weight: bold; color: #0f172a; }
        .company-address { font-size: 8px; color: #475569; }
        .report-title { font-size: 16px; font-weight: bold; color: #0f172a; margin-top: 8px; }
        .report-period { font-size: 9px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .report-table th { text-align: left; padding: 6px 6px; background: #0f172a; color: white; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .report-table td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .report-table .text-right { text-align: right; }
        .currency { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="company-name">{{ $company['name'] ?? 'Company' }}</div>
            <div class="company-address">{!! nl2br(e($company['address'] ?? '')) !!}</div>
            <div class="report-title">General Ledger Report (all transaction lines)</div>
            <div class="report-period">
                @if($date_from || $date_to)
                    {{ $date_from ? \Carbon\Carbon::parse($date_from)->format('d M Y') : 'Start' }} to {{ $date_to ? \Carbon\Carbon::parse($date_to)->format('d M Y') : 'End' }}
                @else
                    All dates
                @endif
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Entry #</th>
                    <th>Description</th>
                    <th>Account</th>
                    <th>Reference</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                <tr>
                    <td>{{ $tx['date'] }}</td>
                    <td>{{ $tx['entry_id'] }}</td>
                    <td style="max-width:160px;">{{ Str::limit($tx['description'], 35) }}</td>
                    <td><span class="currency">{{ $tx['account_code'] }}</span> {{ Str::limit($tx['account_name'], 20) }}</td>
                    <td>{{ $tx['reference_type'] ?? '—' }}</td>
                    <td class="text-right currency">{{ number_format($tx['debit'], 2) }}</td>
                    <td class="text-right currency">{{ number_format($tx['credit'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
