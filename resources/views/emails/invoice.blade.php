<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background-color: #f8fafc; padding: 24px; color: #0f172a;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0;">
        <h1 style="font-size: 20px; margin: 0 0 8px 0;">
            {{ $company['name'] ?? config('app.name') }}
        </h1>
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #64748b;">
            Invoice #{{ $invoice->invoice_number }}
        </p>

        <p style="font-size: 14px; margin-bottom: 12px;">
            Dear {{ $customer->name }},
        </p>

        <p style="font-size: 14px; margin-bottom: 12px;">
            {{ config('invoice.email.intro_text') }}
        </p>

        <p style="font-size: 14px; margin-bottom: 16px;">
            <strong>Amount:</strong>
            RM {{ number_format($invoice->total_amount, 2) }}
        </p>

        @if(!empty($company['email']))
            <p style="font-size: 13px; margin-bottom: 8px; color: #64748b;">
                If you have any questions about this invoice, reply to this email at
                <a href="mailto:{{ $company['email'] }}" style="color: #2563eb; text-decoration: none;">
                    {{ $company['email'] }}
                </a>.
            </p>
        @endif

        <p style="font-size: 13px; margin-top: 24px; color: #64748b;">
            {{ config('invoice.email.footer_text') }}
        </p>

        <p style="font-size: 13px; margin-top: 4px; color: #64748b;">
            {{ $company['name'] ?? config('app.name') }}
        </p>
    </div>
</body>
</html>

