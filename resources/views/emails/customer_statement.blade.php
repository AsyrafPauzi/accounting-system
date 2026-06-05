<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account statement</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
        <div style="background-color: #000000; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">
                Account Statement
            </h1>
        </div>

        <div style="padding: 40px;">
            <div style="margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;">
                <h2 style="font-size: 18px; font-weight: 800; margin: 0 0 5px 0; color: #000000; text-transform: uppercase;">
                    {{ $company['name'] ?? config('app.name') }}
                </h2>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">
                    {{ \Carbon\Carbon::parse($statement['from'])->toFormattedDateString() }}
                    &mdash;
                    {{ \Carbon\Carbon::parse($statement['to'])->toFormattedDateString() }}
                </div>
            </div>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 25px;">
                Hello <strong>{{ $customer->name }}</strong>,
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 30px;">
                Please find your account statement attached. Below is a quick summary; the PDF has the full detail.
            </p>

            <div style="background-color: #f8fafc; border-radius: 12px; padding: 25px; margin-bottom: 35px; border: 1px solid #e2e8f0;">
                <table style="width: 100%; border-collapse: collapse;" cellpadding="6">
                    <tr>
                        <td style="font-size: 13px; color: #64748b; font-weight: 600;">Opening balance</td>
                        <td style="font-size: 13px; color: #0f172a; text-align: right; font-family: 'DejaVu Sans Mono', monospace;">{{ number_format($statement['opening_balance'], 2) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #64748b; font-weight: 600;">Charges in period</td>
                        <td style="font-size: 13px; color: #0f172a; text-align: right; font-family: 'DejaVu Sans Mono', monospace;">+ {{ number_format($statement['total_charges'], 2) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #64748b; font-weight: 600;">Payments received</td>
                        <td style="font-size: 13px; color: #16a34a; text-align: right; font-family: 'DejaVu Sans Mono', monospace;">- {{ number_format($statement['total_payments'], 2) }}</td>
                    </tr>
                    @if ($statement['total_credits'] > 0)
                        <tr>
                            <td style="font-size: 13px; color: #64748b; font-weight: 600;">Credit notes</td>
                            <td style="font-size: 13px; color: #16a34a; text-align: right; font-family: 'DejaVu Sans Mono', monospace;">- {{ number_format($statement['total_credits'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="2" style="border-top: 2px solid #e2e8f0;"></td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; color: #000000; font-weight: 800;">Closing balance</td>
                        <td style="font-size: 18px; color: #000000; text-align: right; font-weight: 800; font-family: 'DejaVu Sans Mono', monospace;">{{ number_format($statement['closing_balance'], 2) }}</td>
                    </tr>
                </table>
            </div>

            <p style="font-size: 13px; line-height: 1.5; color: #64748b; margin-bottom: 0;">
                If anything looks off, please reply to this email and we'll look into it right away.
            </p>
        </div>
    </div>
</body>
</html>
