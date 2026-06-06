<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Estimate {{ $estimate->estimate_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
        <div style="background-color: #0f172a; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">
                Estimate Issued
            </h1>
            <div style="color: #94a3b8; font-size: 11px; margin-top: 6px; letter-spacing: 1.5px; text-transform: uppercase;">
                Quotation &middot; Not a tax invoice
            </div>
        </div>

        <div style="padding: 40px;">
            <div style="margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;">
                <h2 style="font-size: 18px; font-weight: 800; margin: 0 0 5px 0; color: #000000; text-transform: uppercase;">
                    {{ $company['name'] ?? 'Estimate' }}
                </h2>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">
                    Estimate #{{ $estimate->estimate_number }}
                    @if($estimate->expiry_date)
                        &middot; valid until {{ \Carbon\Carbon::parse($estimate->expiry_date)->format('d M Y') }}
                    @endif
                </div>
            </div>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 25px;">
                Hello <strong>{{ $customer->name }}</strong>,
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 30px;">
                Please find attached the estimate for your review. The full breakdown is available
                in the PDF; the headline figures are below.
            </p>

            <div style="background-color: #f8fafc; border-radius: 12px; padding: 25px; margin-bottom: 35px; border: 1px solid #e2e8f0; text-align: center;">
                <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 4px;">Estimated total</span>
                <span style="font-size: 28px; font-weight: 800; color: #0f172a;">
                    {{ number_format($estimate->total_amount, 2) }} {{ strtoupper($estimate->currency ?? 'MYR') }}
                </span>
            </div>

            <div style="text-align: center; margin-bottom: 40px;">
                <a href="{{ $download_url }}" style="display: inline-block; background-color: #0f172a; color: #ffffff; padding: 16px 36px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 15px;">
                    Download PDF Estimate
                </a>
            </div>

            <div style="background-color: #fef3c7; border-radius: 8px; padding: 16px 20px; margin-bottom: 35px; border: 1px dashed #d97706;">
                <p style="margin: 0; font-size: 13px; color: #78350f; line-height: 1.5;">
                    <strong>How to accept:</strong> simply reply to this email to confirm. We&apos;ll
                    then convert this quote into a tax invoice for payment.
                </p>
            </div>

            <div style="padding-top: 30px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #64748b; line-height: 1.6;">
                <p style="margin: 0 0 10px 0; font-weight: 700; color: #0f172a;">Contact us</p>
                @if(!empty($company['phone'])) <div style="margin-bottom: 4px;">Tel: {{ $company['phone'] }}</div> @endif
                @if(!empty($company['email'])) <div style="margin-bottom: 4px;">Email: {{ $company['email'] }}</div> @endif
                @if(!empty($company['website'])) <div>Website: {{ $company['website'] }}</div> @endif
            </div>
        </div>

        <div style="background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #f1f5f9;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                &copy; {{ date('Y') }} {{ $company['name'] ?? config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
