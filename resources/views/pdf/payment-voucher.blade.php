<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Voucher {{ $payment->voucher_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #334155; }
        h1 { font-size: 22px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 8px; }
        .muted { color: #64748b; font-size: 10px; }
        .box { border: 1px solid #e2e8f0; padding: 16px; margin-top: 16px; }
        .amount { font-size: 20px; font-weight: bold; margin: 12px 0 6px; }
        .label { font-size: 9px; text-transform: uppercase; color: #64748b; margin: 0 0 4px; }
        .row { margin: 0 0 10px; }
    </style>
</head>
<body>
    <h1>Payment Voucher</h1>
    <div><strong>{{ $company['name'] ?? '' }}</strong></div>
    <div class="muted">
        @php
            $location = trim(implode(' ', array_filter([
                $company['city'] ?? '',
                $company['state'] ?? '',
                $company['zip'] ?? '',
            ])));
            $meta = array_filter([
                $company['address'] ?? '',
                $location,
                ! empty($company['tin']) ? 'TIN '.$company['tin'] : '',
                ! empty($company['brn']) ? 'BRN '.$company['brn'] : '',
                ! empty($company['sst']) ? 'SST '.$company['sst'] : '',
            ]);
        @endphp
        {{ implode(' · ', $meta) }}
    </div>

    <p style="margin-top: 20px;">
        <strong>No.</strong> {{ $payment->voucher_number }}<br>
        <strong>Date</strong> {{ $payment->payment_date?->format('d M Y') }}<br>
        <strong>Paid to</strong> {{ $supplier->name ?? '—' }}
        @if(!empty($supplier?->tin)) (TIN {{ $supplier->tin }}) @endif
    </p>

    <div class="box">
        <div class="row">
            <p class="label">Payment for</p>
            <p style="margin: 0;">Bill <strong>{{ $bill->bill_number }}</strong></p>
        </div>

        <div class="row">
            <p class="label">Payment method</p>
            <p style="margin: 0;">{{ $payment->bank_account_code }}</p>
        </div>

        @if($payment->reference)
            <div class="row">
                <p class="label">Reference</p>
                <p style="margin: 0;">{{ $payment->reference }}</p>
            </div>
        @endif

        <p class="amount">
            {{ number_format($payment->amount, 2) }} {{ strtoupper($bill->currency ?? 'MYR') }}
        </p>
        <p class="muted" style="margin: 0;">
            {{ \App\Support\AmountInWords::format((float) $payment->amount, $bill->currency ?? 'MYR') }}
        </p>
    </div>
</body>
</html>
