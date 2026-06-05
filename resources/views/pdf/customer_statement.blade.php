<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement — {{ $customer->name }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #334155; line-height: 1.5; background: #fff; }

        .top-bar {
            background-color: #0f172a;
            color: #fff;
            display: table;
            width: 100%;
            border-bottom: 8px solid #334155;
        }
        .top-bar-left { display: table-cell; vertical-align: middle; width: 60%; padding: 30px 0 30px 40px; }
        .top-bar-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; padding: 30px 40px 30px 0; font-size: 10px; }
        .top-bar-right span { display: block; margin-bottom: 2px; }

        .doc-title { font-size: 28px; font-weight: bold; text-transform: uppercase; letter-spacing: 4px; line-height: 1; }
        .doc-subtitle { font-size: 11px; opacity: 0.8; margin-top: 6px; letter-spacing: 1px; }

        .content { padding: 32px 40px 60px; }

        .addresses { display: table; width: 100%; margin-bottom: 28px; }
        .address-col { display: table-cell; width: 50%; vertical-align: top; }
        .address-col-inner { padding-right: 30px; }
        .address-col-last .address-col-inner { padding-right: 0; padding-left: 30px; }
        .address-title {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .address-name { font-weight: 800; color: #0f172a; font-size: 12px; margin-bottom: 4px; }
        .address-detail { color: #64748b; font-size: 10px; line-height: 1.6; }

        .summary {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 22px;
            background: #f8fafc;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 4px 0; font-size: 11px; }
        .summary .label { color: #64748b; font-weight: 600; }
        .summary .value { color: #0f172a; text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .summary .closing-label { color: #000000; font-weight: 800; font-size: 12px; padding-top: 8px; border-top: 2px solid #e2e8f0; }
        .summary .closing-value { color: #000000; text-align: right; font-weight: 800; font-size: 14px; padding-top: 8px; border-top: 2px solid #e2e8f0; font-family: 'DejaVu Sans Mono', monospace; }

        table.activity { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.activity th {
            background: #0f172a;
            color: #fff;
            text-align: left;
            padding: 10px 12px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table.activity th.right { text-align: right; }
        table.activity td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            vertical-align: top;
        }
        table.activity td.right { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        table.activity td.muted { color: #94a3b8; }
        table.activity tr.opening td {
            background: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
        }
        table.activity tr.closing td {
            background: #0f172a;
            color: #fff;
            font-weight: 800;
            border-bottom: none;
        }

        .charge { color: #0f172a; }
        .payment { color: #16a34a; }
        .credit { color: #16a34a; }

        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="top-bar">
        <div class="top-bar-left">
            <div class="doc-title">Statement</div>
            <div class="doc-subtitle">Account activity &middot; {{ \Carbon\Carbon::parse($statement['from'])->format('d M Y') }} &mdash; {{ \Carbon\Carbon::parse($statement['to'])->format('d M Y') }}</div>
        </div>
        <div class="top-bar-right">
            <span style="font-size: 14px; font-weight: 800; letter-spacing: 1px;">{{ strtoupper($company['name'] ?? config('app.name')) }}</span>
            @if (! empty($company['email']))<span>{{ $company['email'] }}</span>@endif
            @if (! empty($company['phone']))<span>{{ $company['phone'] }}</span>@endif
        </div>
    </div>

    <div class="content">
        <div class="addresses">
            <div class="address-col">
                <div class="address-col-inner">
                    <div class="address-title">From</div>
                    <div class="address-name">{{ $company['name'] ?? config('app.name') }}</div>
                    @if (! empty($company['address']))<div class="address-detail">{{ $company['address'] }}</div>@endif
                    @if (! empty($company['tin']))<div class="address-detail">TIN: {{ $company['tin'] }}</div>@endif
                </div>
            </div>
            <div class="address-col address-col-last">
                <div class="address-col-inner">
                    <div class="address-title">For</div>
                    <div class="address-name">{{ $customer->name }}</div>
                    @if ($customer->billing_street || $customer->billing_city)
                        <div class="address-detail">
                            {{ trim(implode(' ', array_filter([$customer->billing_street, $customer->billing_city, $customer->billing_state, $customer->billing_zip, $customer->billing_country]))) }}
                        </div>
                    @endif
                    @if ($customer->tin)<div class="address-detail">TIN: {{ $customer->tin }}</div>@endif
                </div>
            </div>
        </div>

        <div class="summary">
            <table>
                <tr>
                    <td class="label">Opening balance</td>
                    <td class="value">{{ number_format($statement['opening_balance'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Charges in period</td>
                    <td class="value charge">+ {{ number_format($statement['total_charges'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Payments received</td>
                    <td class="value payment">- {{ number_format($statement['total_payments'], 2) }}</td>
                </tr>
                @if ($statement['total_credits'] > 0)
                    <tr>
                        <td class="label">Credit notes</td>
                        <td class="value credit">- {{ number_format($statement['total_credits'], 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="closing-label">Closing balance</td>
                    <td class="closing-value">{{ number_format($statement['closing_balance'], 2) }}</td>
                </tr>
            </table>
        </div>

        <table class="activity">
            <thead>
                <tr>
                    <th style="width: 14%;">Date</th>
                    <th style="width: 16%;">Reference</th>
                    <th>Description</th>
                    <th class="right" style="width: 12%;">Charge</th>
                    <th class="right" style="width: 12%;">Payment</th>
                    <th class="right" style="width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening">
                    <td>{{ \Carbon\Carbon::parse($statement['from'])->format('d M Y') }}</td>
                    <td colspan="3">Opening balance brought forward</td>
                    <td class="right muted">—</td>
                    <td class="right">{{ number_format($statement['opening_balance'], 2) }}</td>
                </tr>

                @forelse ($statement['activity'] as $event)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($event['date'])->format('d M Y') }}</td>
                        <td><strong>{{ $event['reference'] }}</strong></td>
                        <td>{{ $event['description'] }}</td>
                        <td class="right charge">
                            @if ($event['charge'] > 0){{ number_format($event['charge'], 2) }}@else<span class="muted">—</span>@endif
                        </td>
                        <td class="right payment">
                            @if ($event['payment'] > 0){{ number_format($event['payment'], 2) }}@elseif($event['credit'] > 0){{ number_format($event['credit'], 2) }} <span style="font-size: 8px; color: #94a3b8;">(CN)</span>@else<span class="muted">—</span>@endif
                        </td>
                        <td class="right">{{ number_format($event['running_balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: #94a3b8; font-style: italic;">
                            No activity in this period.
                        </td>
                    </tr>
                @endforelse

                <tr class="closing">
                    <td>{{ \Carbon\Carbon::parse($statement['to'])->format('d M Y') }}</td>
                    <td colspan="3">Closing balance carried forward</td>
                    <td class="right">—</td>
                    <td class="right">{{ number_format($statement['closing_balance'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            Generated on {{ now()->format('d M Y H:i') }} &middot; This statement reflects activity recorded as of the generation time.
        </div>
    </div>
</div>
</body>
</html>
