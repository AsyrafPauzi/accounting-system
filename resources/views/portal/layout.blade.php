<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Customer portal')</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .wrap { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15,23,42,.06); margin-bottom: 20px; }
        .hero { background: {{ $company['brand_color'] ?? '#0f172a' }}; color: #fff; padding: 28px 24px; }
        .hero h1 { margin: 0 0 6px; font-size: 1.4rem; }
        .hero p { margin: 0; opacity: .9; font-size: .95rem; }
        .body { padding: 24px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: .9rem; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; }
        .text-right { text-align: right; }
        .muted { color: #64748b; font-size: .875rem; }
        .stat { font-size: 1.75rem; font-weight: 800; }
    </style>
</head>
<body>
<div class="wrap">
    @yield('content')
    <p class="muted" style="text-align:center;margin-top:24px;">&copy; {{ date('Y') }} {{ $company['name'] ?? config('app.name') }}</p>
</div>
</body>
</html>
