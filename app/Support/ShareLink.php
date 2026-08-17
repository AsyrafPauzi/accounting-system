<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class ShareLink
{
    public static function whatsapp(string $text): string
    {
        return 'https://wa.me/?text='.rawurlencode($text);
    }

    /**
     * @return array{public_url: string, whatsapp_url: string}
     */
    public static function publicSigned(string $route, array $params, string $label): array
    {
        $public = rtrim((string) (config('app.public_url') ?: config('app.url')), '/');
        $previous = config('app.url');
        URL::forceRootUrl($public);
        URL::forceScheme(str_starts_with($public, 'https://') ? 'https' : 'http');
        try {
            $url = URL::temporarySignedRoute($route, now()->addDays(30), $params);
        } finally {
            URL::forceRootUrl($previous);
            URL::forceScheme(parse_url((string) $previous, PHP_URL_SCHEME) ?: 'http');
        }

        return [
            'public_url'   => $url,
            'whatsapp_url' => self::whatsapp($label.' '.$url),
        ];
    }
}
