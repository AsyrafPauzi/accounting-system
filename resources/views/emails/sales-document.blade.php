<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc_title }} {{ $doc_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background-color: #000000; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">{{ $doc_title }}</h1>
        </div>
        <div style="padding: 40px;">
            <p>Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>
                Please find {{ strtolower($doc_title) }} <strong>{{ $doc_number }}</strong>
                @isset($amount)
                    for {{ number_format($amount, 2) }} {{ strtoupper($currency ?? 'MYR') }}
                @endisset
                attached as a PDF.
            </p>
            <p style="color: #64748b; font-size: 14px; margin-top: 24px;">From {{ $company['name'] ?? config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
