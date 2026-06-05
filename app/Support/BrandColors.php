<?php

namespace App\Support;

/**
 * Tiny helper that turns a single brand hex (e.g. "#C0492E") into the three
 * RGB triplets that Tailwind's CSS-variable theme expects:
 *  - base   → --color-terracotta
 *  - dark   → --color-terracotta-dark   (used by hover:bg-terracotta-dark)
 *  - light  → --color-terracotta-light  (used by dark-mode hover states)
 *
 * Variants are derived in HSL space so the perceived hue stays put while
 * lightness shifts ±12 %. This is what people intuitively expect from a
 * "darker / lighter" version of the same color, and it costs us roughly
 * ~50 µs per call — negligible at the request scale.
 *
 * The class is stateless and side-effect-free; it's safe to call from a
 * Blade template or a controller without worrying about caching.
 */
final class BrandColors
{
    private const HEX_PATTERN = '/^#[A-Fa-f0-9]{6}$/';

    /** How much to shift lightness for the -dark / -light variants (0..1). */
    private const SHIFT = 0.12;

    /**
     * @return array{base:string,dark:string,light:string}|null
     *         RGB triplets like "192 73 46", or null if $hex is invalid.
     */
    public static function variants(?string $hex): ?array
    {
        if (! $hex || ! preg_match(self::HEX_PATTERN, $hex)) {
            return null;
        }

        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        return [
            'base'  => "$r $g $b",
            'dark'  => self::hslToTriplet($h, $s, max($l - self::SHIFT, 0.05)),
            // Slightly desaturate the light variant to avoid neon hover states
            // when the base is already vivid.
            'light' => self::hslToTriplet($h, max($s - 0.05, 0), min($l + self::SHIFT, 0.95)),
        ];
    }

    /**
     * Plain hex → triplet ("#C0492E" → "192 73 46"). Returns null on bad input.
     */
    public static function hexToTriplet(?string $hex): ?string
    {
        if (! $hex || ! preg_match(self::HEX_PATTERN, $hex)) {
            return null;
        }

        return hexdec(substr($hex, 1, 2)) . ' '
             . hexdec(substr($hex, 3, 2)) . ' '
             . hexdec(substr($hex, 5, 2));
    }

    /**
     * @return array{0:float,1:float,2:float} [h,s,l] in 0..1.
     */
    private static function rgbToHsl(int $r, int $g, int $b): array
    {
        $rN = $r / 255;
        $gN = $g / 255;
        $bN = $b / 255;
        $max = max($rN, $gN, $bN);
        $min = min($rN, $gN, $bN);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match (true) {
            $max === $rN => ($gN - $bN) / $d + ($gN < $bN ? 6 : 0),
            $max === $gN => ($bN - $rN) / $d + 2,
            default      => ($rN - $gN) / $d + 4,
        };
        $h /= 6;

        return [$h, $s, $l];
    }

    private static function hslToTriplet(float $h, float $s, float $l): string
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);
            return "$v $v $v";
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $hue2rgb = static function (float $p, float $q, float $t): float {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };

        $r = (int) round($hue2rgb($p, $q, $h + 1 / 3) * 255);
        $g = (int) round($hue2rgb($p, $q, $h) * 255);
        $b = (int) round($hue2rgb($p, $q, $h - 1 / 3) * 255);

        return "$r $g $b";
    }
}
