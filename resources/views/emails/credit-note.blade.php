<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Credit note {{ $creditNote->cn_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background-color: #000000; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">Credit Note</h1>
        </div>
        <div style="padding: 40px;">
            <p>Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>We have issued credit note <strong>{{ $creditNote->cn_number }}</strong> for {{ number_format($creditNote->total_amount, 2) }} {{ strtoupper($creditNote->currency ?? 'MYR') }}.</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $download_url }}" style="display: inline-block; background-color: #000000; color: #ffffff; padding: 16px 36px; border-radius: 8px; font-weight: 700; text-decoration: none;">Download PDF</a>
            </div>
        </div>
    </div>
</body>
</html>
