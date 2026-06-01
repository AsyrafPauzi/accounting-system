<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <title inertia>{{ config('app.product_name', config('app.name', 'BukuCloud')) }}</title>

        {{-- BukuCloud brand fonts: Fraunces (display), Inter (body), JetBrains Mono (numbers) --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700|inter:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

        {{-- Theme bootstrap (FOUC prevention) — must run before React paints --}}
        <script>
            (function () {
                try {
                    var pref = @json(auth()->user()?->theme_preference ?? 'light');
                    var actual = pref;
                    if (pref === 'system') {
                        actual = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    }
                    if (actual === 'dark') {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) { /* default to light on any error */ }
            })();
        </script>

        {{-- Self-hosted brand override (only renders if APP_DEPLOYMENT_MODE=self_hosted and at least one custom color is set) --}}
        @if (config('deployment.mode') === 'self_hosted')
            @php
                $brand = \Schema::hasTable('brand_settings') ? \App\Models\BrandSettings::current() : null;
                $hexToRgb = fn($hex) => $hex
                    ? collect(str_split(ltrim($hex, '#'), 2))->map(fn($c) => hexdec($c))->implode(' ')
                    : null;
            @endphp
            @if ($brand && ($brand->color_terracotta || $brand->color_forest || $brand->color_mustard))
                <style>
                    :root {
                        @if ($brand->color_terracotta) --color-terracotta: {{ $hexToRgb($brand->color_terracotta) }}; @endif
                        @if ($brand->color_forest) --color-forest: {{ $hexToRgb($brand->color_forest) }}; @endif
                        @if ($brand->color_mustard) --color-mustard: {{ $hexToRgb($brand->color_mustard) }}; @endif
                    }
                </style>
            @endif
        @endif

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
