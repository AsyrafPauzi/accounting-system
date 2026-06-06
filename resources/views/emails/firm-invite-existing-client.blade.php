<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $firm->name }} would like to manage your books</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #0f172a; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
        <div style="background-color: #c1502c; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 2px;">
                Accountant invitation
            </h1>
        </div>

        <div style="padding: 40px;">
            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 18px 0;">
                Hello,
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 18px 0;">
                <strong style="color:#0f172a;">{{ $firm->name }}</strong>
                @if (!empty($inviterName))
                    (sent by {{ $inviterName }})
                @endif
                would like to manage your books on <strong>{{ $appName }}</strong>.
            </p>

            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 30px 0;">
                If this matches an arrangement you've made with them, log in to your
                {{ $appName }} account and accept the invite. If you don't recognise
                this firm, simply decline &mdash; nothing happens to your data either way.
            </p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $acceptUrl }}"
                   style="background-color: #c1502c; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-block; text-transform: uppercase; letter-spacing: 1px;">
                    Review the invite
                </a>
            </div>

            <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0 0 8px 0;">
                Or paste this URL into your browser:
            </p>
            <p style="font-size: 13px; color: #334155; word-break: break-all; margin: 0 0 25px 0;">
                {{ $acceptUrl }}
            </p>

            @if ($expiresAt)
                <p style="font-size: 12px; color: #94a3b8; margin: 0 0 4px 0;">
                    This invite expires {{ $expiresAt->format('j F Y') }}.
                </p>
            @endif
            <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                Accepting links the firm to your account. You stay in control &mdash; you can unlink them at any time from your settings.
            </p>
        </div>

        <div style="background-color: #f8fafc; padding: 18px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 11px; color: #94a3b8; margin: 0;">
                Sent automatically by {{ $appName }} on behalf of {{ $firm->name }}.
                If you weren't expecting this, no action is needed.
            </p>
        </div>
    </div>
</body>
</html>
