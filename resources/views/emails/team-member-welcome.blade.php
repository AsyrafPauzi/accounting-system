<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>You've been added to {{ $companyName }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
        <div style="background-color: #c1502c; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">
                Welcome to {{ $appName }}
            </h1>
        </div>

        <div style="padding: 40px;">
            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 18px 0;">
                Hello {{ $name }},
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 18px 0;">
                @if (!empty($inviterName))
                    <strong style="color:#0f172a;">{{ $inviterName }}</strong>
                    added you
                @else
                    You were added
                @endif
                to <strong style="color:#0f172a;">{{ $companyName }}</strong> on <strong>{{ $appName }}</strong>.
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 24px 0;">
                Your role is <strong>{{ ucfirst($role) }}</strong>. Use the button below to set your own password and sign in.
            </p>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin: 0 0 28px 0;">
                <p style="font-size: 13px; color:#64748b; margin:0 0 4px 0;">Login email</p>
                <p style="font-size: 15px; color:#0f172a; margin:0; font-weight:700;">{{ $email }}</p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $resetUrl }}"
                   style="background-color: #c1502c; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-block; text-transform: uppercase; letter-spacing: 1px;">
                    Set your password
                </a>
            </div>

            <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0 0 8px 0;">
                Or paste this URL into your browser:
            </p>
            <p style="font-size: 13px; color: #334155; word-break: break-all; margin: 0 0 25px 0;">
                {{ $resetUrl }}
            </p>

            <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                If you were not expecting this invitation, you can ignore this email.
            </p>
        </div>

        <div style="background-color: #f8fafc; padding: 18px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 11px; color: #94a3b8; margin: 0;">
                Sent automatically by {{ $appName }}.
            </p>
        </div>
    </div>
</body>
</html>
