<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
        <!-- Header Bar -->
        <div style="background-color: #000000; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">
                Invoice Issued
            </h1>
        </div>
        
        <div style="padding: 40px;">
            <div style="margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;">
                <h2 style="font-size: 18px; font-weight: 800; margin: 0 0 5px 0; color: #000000; text-transform: uppercase;">
                    {{ $company['name'] }}
                </h2>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">
                    Invoice #{{ $invoice->invoice_number }}
                </div>
            </div>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 25px;">
                Hello <strong>{{ $customer->name }}</strong>,
            </p>
            
            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 30px;">
                We have issued a new invoice for your recent transaction. Please find the summary below:
            </p>

            <!-- Summary Box -->
            <div style="background-color: #f8fafc; border-radius: 12px; padding: 25px; margin-bottom: 35px; border: 1px solid #e2e8f0; text-align: center;">
                <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 4px;">Amount Due</span>
                <span style="font-size: 28px; font-weight: 800; color: #000000;">{{ number_format($invoice->balance_due, 2) }} {{ strtoupper($invoice->currency ?? 'MYR') }}</span>
            </div>

            <!-- Buttons -->
            <div style="text-align: center; margin-bottom: 40px;">
                @if(!empty($view_pay_url))
                <a href="{{ $view_pay_url }}" style="display: inline-block; background-color: #000000; color: #ffffff; padding: 16px 36px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 15px; margin-bottom: 12px;">
                    View &amp; Pay Invoice
                </a>
                <br>
                @endif
                <a href="{{ $download_url }}" style="display: inline-block; background-color: #ffffff; color: #000000; padding: 14px 32px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px; border: 2px solid #000000;">
                    Download PDF
                </a>
                @if(!empty($portal_url))
                <br><br>
                <a href="{{ $portal_url }}" style="display: inline-block; background-color: #0f766e; color: #ffffff; padding: 14px 32px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px;">
                    View all invoices (customer portal)
                </a>
                @endif
            </div>

            <div style="padding-top: 30px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #64748b; line-height: 1.6;">
                <p style="margin: 0 0 10px 0; font-weight: 700; color: #0f172a;">Contact Us</p>
                @if($company['phone']) <div style="margin-bottom: 4px;">Tel: {{ $company['phone'] }}</div> @endif
                @if($company['email']) <div style="margin-bottom: 4px;">Email: {{ $company['email'] }}</div> @endif
                <div>Website: {{ $company['website'] }}</div>
            </div>
        </div>

        <div style="background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #f1f5f9;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                &copy; {{ date('Y') }} {{ $company['name'] }}. All rights reserved.
            </p>
            @if(!empty($pixel_url))
                <img src="{{ $pixel_url }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />
            @endif
        </div>
    </div>
</body>
</html>
