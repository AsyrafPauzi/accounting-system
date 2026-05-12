<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Audit Summary - {{ $year }}</title>
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
        
        .stats-grid { display: table; width: 100%; margin-bottom: 30px; }
        .stat-item { display: table-cell; width: 25%; text-align: center; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .stat-label { font-size: 8px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #0f172a; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .report-table th { text-align: left; padding: 8px 10px; background: #0f172a; color: white; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .report-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        .text-right { text-align: right; }
        .currency { font-family: DejaVu Sans Mono, monospace; }
        
        .status-badge { padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .status-verified { background: #dcfce7; color: #166534; }
        .status-unaudited { background: #fef2f2; color: #991b1b; }
        .status-flagged { background: #fff7ed; color: #9a3412; }
        
        .footer { position: fixed; bottom: 20px; left: 32px; right: 32px; font-size: 8px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 10px; text-align: center; }
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
                <div class="report-title">Audit Summary</div>
                <div class="report-period">Financial Year {{ $year }}</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-item" style="border-left: 0;">
                <div class="stat-label">Verified</div>
                <div class="stat-value" style="color: #166534;">{{ $stats['verified'] }}</div>
            </div>
            <div class="stat-item" style="border-left: 0;">
                <div class="stat-label">Unaudited</div>
                <div class="stat-value" style="color: #991b1b;">{{ $stats['unaudited'] }}</div>
            </div>
            <div class="stat-item" style="border-left: 0;">
                <div class="stat-label">Total Amount</div>
                <div class="stat-value currency">RM {{ number_format($stats['total_amount'], 2) }}</div>
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th class="text-right">Amount (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bills as $bill)
                <tr>
                    <td>{{ $bill->bill_date->format('d M Y') }}</td>
                    <td>{{ $bill->bill_number }}</td>
                    <td>{{ $bill->supplier?->name ?? '—' }}</td>
                    <td>
                        @php
                            $status = $bill->audit_status ?? 'unaudited';
                        @endphp
                        <span class="status-badge status-{{ $status }}">
                            {{ $status }}
                        </span>
                    </td>
                    <td class="text-right currency">{{ number_format($bill->total_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Generated on {{ now()->format('d M Y, h:i A') }} • Powered by {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
