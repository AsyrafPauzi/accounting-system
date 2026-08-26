<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice reminder {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background-color: #000000; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 18px; margin: 0; text-transform: uppercase;">Payment reminder</h1>
        </div>
        <div style="padding: 36px;">
            <p>Hello <strong>{{ $customer->name ?? 'there' }}</strong>,</p>
            <p>Invoice <strong>{{ $invoice->invoice_number }}</strong> from {{ $company['name'] ?? '' }} is <strong>{{ $when }}</strong>.</p>
            <p style="font-size: 24px; font-weight: 800;">
                {{ number_format($invoice->balance_due, 2) }} {{ strtoupper($invoice->currency ?? 'MYR') }}
            </p>
            <div style="text-align: center; margin: 28px 0;">
                <a href="{{ $download_url }}" style="display: inline-block; background-color: #000000; color: #ffffff; padding: 14px 32px; border-radius: 8px; font-weight: 700; text-decoration: none;">View invoice</a>
            </div>
        </div>
    </div>
</body>
</html>
