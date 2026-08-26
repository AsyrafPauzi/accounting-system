<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15,23,42,.06); }
        .hero { background: {{ $company['brand_color'] ?? '#0f172a' }}; color: #fff; padding: 28px 24px; }
        .hero h1 { margin: 0 0 6px; font-size: 1.5rem; }
        .hero p { margin: 0; opacity: .9; }
        .body { padding: 24px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0 24px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: .95rem; }
        th { color: #64748b; font-size: .75rem; text-transform: uppercase; }
        .total { font-size: 1.75rem; font-weight: 800; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .btn { display: inline-block; padding: 12px 18px; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: .95rem; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        .btn-wa { background: #25D366; color: #fff; }
        .muted { color: #64748b; font-size: .875rem; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="hero">
            <h1>{{ $company['name'] ?? config('app.name') }}</h1>
            <p>Invoice {{ $invoice->invoice_number }}</p>
        </div>
        <div class="body">
            <div class="grid">
                <div>
                    <div class="label">Bill to</div>
                    <strong>{{ $invoice->customer?->name ?? 'Customer' }}</strong>
                </div>
                <div>
                    <div class="label">Due date</div>
                    <strong>{{ optional($invoice->due_date)->format('d M Y') ?? '—' }}</strong>
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:right">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td style="text-align:right">{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="label">Balance due</div>
            <div class="total">{{ number_format($balance, 2) }} {{ $currency }}</div>

            <div class="actions">
                @if($canPay && $payUrl)
                    <a class="btn btn-primary" href="{{ $payUrl }}">Pay now</a>
                @endif
                <a class="btn btn-secondary" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Download PDF</a>
                <a class="btn btn-wa" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Share on WhatsApp</a>
            </div>

            @if(!$payConfigured && $canPay)
                <p class="muted" style="margin-top:16px">Online payment is not enabled. Please use bank transfer details on the PDF or contact {{ $company['name'] ?? 'us' }}.</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
