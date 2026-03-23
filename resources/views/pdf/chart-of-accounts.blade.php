<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Chart of Accounts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        .page { padding: 28px 32px; max-width: 210mm; }
        .header { display: table; width: 100%; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #0f172a; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
        .company-address { font-size: 9px; color: #475569; }
        .report-title { font-size: 20px; font-weight: bold; color: #0f172a; }
        .report-date { font-size: 10px; color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .report-table th { text-align: left; padding: 6px 8px; background: #0f172a; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .report-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .section-title { font-size: 10px; font-weight: bold; color: #334155; margin-top: 12px; margin-bottom: 4px; }
        .code { font-family: DejaVu Sans Mono, monospace; }
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
                <div class="report-title">Chart of Accounts</div>
                <div class="report-date">As at {{ now()->format('d M Y') }}</div>
            </div>
        </div>

        @php
            $typeLabels = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Revenue', 'expense' => 'Expenses'];
        @endphp
        @foreach($groupedByType as $type => $accounts)
        <div class="section-title">{{ $typeLabels[$type] ?? ucfirst($type) }}</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Parent</th>
                    <th>Active</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $a)
                <tr>
                    <td class="code">{{ $a->code }}</td>
                    <td>{{ $a->name }}</td>
                    <td class="code">{{ $a->parent?->code ?? '—' }}</td>
                    <td>{{ $a->is_active ? 'Yes' : 'No' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach
    </div>
</body>
</html>
