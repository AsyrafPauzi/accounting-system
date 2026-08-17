<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $payment->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #334155; }
        h1 { font-size: 22px; text-transform: uppercase; letter-spacing: 2px; }
        .box { border: 1px solid #e2e8f0; padding: 16px; margin-top: 16px; }
    </style>
</head>
<body>
    <h1>Payment receipt</h1>
    <div>{{ $company['name'] ?? '' }}</div>
    <div class="box">
        <p>Received from <strong>{{ $customer->name ?? '—' }}</strong></p>
        <p>Invoice <strong>{{ $invoice->invoice_number }}</strong></p>
        <p>Date {{ $payment->payment_date?->toDateString() }}</p>
        <p>Account {{ $payment->bank_account_code }}</p>
        <p style="font-size: 20px; font-weight: bold;">
            {{ number_format($payment->amount, 2) }} {{ strtoupper($invoice->currency ?? 'MYR') }}
        </p>
        @if($payment->reference)
            <p>Reference: {{ $payment->reference }}</p>
        @endif
    </div>
</body>
</html>
