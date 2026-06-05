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

        {{-- Platform-wide brand override. Renders whenever the super-admin has
             saved at least one custom accent color (or in self-hosted mode for
             white-labelling). The brand_settings row lives in the CENTRAL db
             and is shared across every tenant, so we check the table existence
             on the central connection (otherwise mid-tenant requests would
             never resolve the table and the override would silently no-op).

             We inject not just the base color but also the -dark and -light
             variants (auto-derived in HSL space) so hover states like
             `hover:bg-terracotta-dark` follow the chosen accent rather than
             reverting to the BukuCloud default red. --}}
        @php
            $brand = null;
            try {
                if (\Schema::connection(config('tenancy.database.central_connection', 'mysql'))->hasTable('brand_settings')) {
                    $brand = \App\Models\BrandSettings::current();
                }
            } catch (\Throwable $e) {
                // First-boot or migration in flight — silently fall back to defaults.
                $brand = null;
            }

            $accentVars = [];
            if ($brand) {
                foreach (['terracotta', 'forest', 'mustard'] as $key) {
                    $variants = \App\Support\BrandColors::variants($brand->{"color_{$key}"} ?? null);
                    if (! $variants) continue;
                    $accentVars["--color-{$key}"] = $variants['base'];
                    // Mustard is currently a single shade in the design system
                    // (no -dark / -light utilities reference it), so we only
                    // emit those for terracotta + forest.
                    if ($key !== 'mustard') {
                        $accentVars["--color-{$key}-dark"]  = $variants['dark'];
                        $accentVars["--color-{$key}-light"] = $variants['light'];
                    }
                }
            }
        @endphp
        @if (! empty($accentVars))
            <style id="bukucloud-brand-vars">
                :root {
                    @foreach ($accentVars as $name => $value) {{ $name }}: {{ $value }}; @endforeach
                }
            </style>
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
