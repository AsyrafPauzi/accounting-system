<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription renewal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background-color: #000000; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 18px; margin: 0;">Subscription renewal</h1>
        </div>
        <div style="padding: 36px;">
            <p>Your <strong>{{ $planName }}</strong> plan ({{ $interval }}) is due for renewal.</p>
            <p style="font-size: 24px; font-weight: 800;">RM {{ $amount }}</p>
            @if($dueAt)
                <p>Please pay by <strong>{{ $dueAt }}</strong> to keep uninterrupted access (7-day grace after that).</p>
            @endif
            @if($paymentUrl)
                <div style="text-align: center; margin: 28px 0;">
                    <a href="{{ $paymentUrl }}" style="display: inline-block; background-color: #c45c26; color: #ffffff; padding: 14px 32px; border-radius: 8px; font-weight: 700; text-decoration: none;">Pay now</a>
                </div>
            @endif
            <p style="font-size: 13px; color: #64748b;">If you already paid, you can ignore this email.</p>
        </div>
    </div>
</body>
</html>
