<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment received</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; max-width: 420px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Thank you</h1>
        @if($invoice)
            <p>Payment for invoice <strong>{{ $invoice->invoice_number }}</strong> is being confirmed.</p>
        @else
            <p>Payment received. You can close this window.</p>
        @endif
    </div>
</body>
</html>
