<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #334155; }
        h1 { font-size: 22px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; border-bottom: 1px solid #0f172a; padding: 8px; font-size: 9px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 10px; }
        .qr { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div><strong>{{ $company['name'] ?? '' }}</strong></div>
    <div class="muted">{{ $company['address'] ?? '' }} · TIN {{ $company['tin'] ?? '—' }} · BRN {{ $company['brn'] ?? '—' }}</div>
    <p>
        <strong>No.</strong> {{ $number }}<br>
        <strong>Date</strong> {{ $issue_date }}<br>
        <strong>To</strong> {{ $customer->name ?? '—' }}
        @if(!empty($customer?->tin)) (TIN {{ $customer->tin }}) @endif
    </p>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Tax %</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $attrs = $item instanceof \Illuminate\Database\Eloquent\Model
                        ? $item->getAttributes()
                        : (array) $item;
                    $qty = (float) ($attrs['quantity'] ?? 0);
                    $price = (float) ($attrs['unit_price'] ?? 0);
                    $taxRate = (float) ($attrs['tax_rate'] ?? 0);
                    $amount = array_key_exists('amount', $attrs)
                        ? (float) $attrs['amount']
                        : round($qty * $price, 2);
                @endphp
                <tr>
                    <td>{{ $attrs['description'] ?? '' }}</td>
                    <td class="right">{{ number_format($qty, 2) }}</td>
                    <td class="right">{{ number_format($price, 2) }}</td>
                    <td class="right">{{ number_format($taxRate, 2) }}</td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="right">
        @if(isset($tax)) Tax: {{ number_format($tax, 2) }}<br> @endif
        <strong>Total: {{ number_format($total, 2) }} {{ $currency }}</strong>
    </p>
    @if(!empty($notes))
        <p class="muted">{{ $notes }}</p>
    @endif
    @if(!empty($qr_url))
        <p class="qr muted">MyInvois: {{ $qr_url }}</p>
    @endif
</body>
</html>
