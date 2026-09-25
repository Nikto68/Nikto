<?php

declare(strict_types=1);

final class CardPalette
{
    public const BG_TOP       = [16, 16, 18];
    public const BG_BOTTOM    = [0, 0, 0];

    public const PROFIT        = [34, 197, 94];
    public const LOSS          = [246, 70, 93];
    public const BRAND_GREEN   = [46, 230, 128];
    public const BRAND_CYAN    = [120, 245, 205];

    public const WHITE        = [255, 255, 255];
    public const TEXT         = [246, 247, 249];
    public const SOFT         = [198, 200, 206];
    public const MUTED        = [138, 141, 148];
    public const DIM          = [84, 87, 94];
    public const GLASS        = [255, 255, 255];

    public const NEON_GREEN   = self::WHITE;
    public const NEON_CYAN    = self::WHITE;
    public const NEON_PINK    = self::WHITE;
    public const NEON_AMBER   = self::WHITE;
    public const NEON_VIOLET  = self::WHITE;
    public const GREEN        = self::WHITE;
    public const GREEN_DEEP   = self::DIM;
    public const YELLOW       = self::WHITE;
    public const YELLOW_DEEP  = self::DIM;
    public const AMBER        = self::WHITE;
    public const TEAL         = self::WHITE;

    public static function forDirection(string $direction): array
    {
        return self::WHITE;
    }

    public static function complementFor(array $accent): array
    {
        return self::WHITE;
    }
}

final class CardTheme
{
    public const BACKGROUND     = CardPalette::BG_BOTTOM;
    public const PANEL          = CardPalette::BG_TOP;
    public const PRIMARY_GREEN  = CardPalette::BRAND_GREEN;
    public const DARK_GREEN     = [6, 61, 37];
    public const WHITE          = CardPalette::WHITE;
    public const TEXT_PRIMARY   = CardPalette::TEXT;
    public const TEXT_SECONDARY = CardPalette::MUTED;
    public const TEXT_DIM       = CardPalette::DIM;
    public const RED            = CardPalette::LOSS;

    public const BORDER         = CardPalette::BRAND_GREEN;
    public const GLOW           = CardPalette::BRAND_GREEN;

    public static function directionAccent(bool $bullish): array
    {
        return $bullish ? self::PRIMARY_GREEN : self::RED;
    }

    public const FONT_BRAND  = 15.0;
    public const FONT_TITLE  = 30.0;
    public const FONT_SYMBOL = 52.0;
    public const FONT_HERO   = 96.0;
    public const FONT_LABEL  = 15.0;
    public const FONT_VALUE  = 26.0;
    public const FONT_META   = 14.0;
}

final class CardFormat
{
    public static function formatPercent(float $value, int $decimals = 2): string
    {
        return ($value > 0 ? '+' : '') . number_format($value, $decimals) . '%';
    }

    public static function formatUSD(float $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $abs = abs($value);
        return match (true) {
            $abs >= 1_000_000_000 => $sign . '$' . number_format($abs / 1_000_000_000, 2) . 'B',
            $abs >= 1_000_000 => $sign . '$' . number_format($abs / 1_000_000, 2) . 'M',
            $abs >= 1_000 => $sign . '$' . number_format($abs / 1_000, 1) . 'K',
            default => $sign . '$' . number_format($abs, 2),
        };
    }

    public static function formatNumber(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals);
    }

    public static function orNA(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? 'N/A' : $value;
    }

    public static function tryParseNumber(string $value): ?float
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-' || $trimmed === '—') {
            return null;
        }
        $clean = str_replace([',', '%', '$', '+'], '', $trimmed);
        return is_numeric($clean) ? (float) $clean : null;
    }
}

final class VectorFont
{
    public const TRACKING = 2.0;

    private static ?array $glyphs = null;

    private static function glyphs(): array
    {
        if (self::$glyphs !== null) {
            return self::$glyphs;
        }

        $ring = static function (float $cx, float $cy, float $r): array {
            $pts = [];
            for ($i = 0; $i <= 8; $i++) {
                $a = ($i / 8) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        $dot = static fn(float $x, float $y): array => [[$x, $y], [$x + 0.01, $y]];

        return self::$glyphs = [
            ' ' => [3.6, []],

            'A' => [6.0, [[[0, 10], [3, 0], [6, 10]], [[1.0, 6.7], [5.0, 6.7]]]],
            'B' => [6.0, [
                [[0, 0], [0, 10]],
                [[0, 0], [4.0, 0], [5.6, 1.4], [5.6, 3.6], [4.0, 5.0], [0, 5.0]],
                [[0, 5.0], [4.3, 5.0], [6.0, 6.5], [6.0, 8.5], [4.3, 10], [0, 10]],
            ]],
            'C' => [6.0, [[[6.0, 2.0], [4.6, 0.5], [3.0, 0], [1.4, 0.7], [0, 3.0], [0, 7.0], [1.4, 9.3], [3.0, 10], [4.6, 9.5], [6.0, 8.0]]]],
            'D' => [6.0, [
                [[0, 0], [0, 10]],
                [[0, 0], [3.0, 0], [5.2, 1.8], [6.0, 5.0], [5.2, 8.2], [3.0, 10], [0, 10]],
            ]],
            'E' => [5.8, [[[5.8, 0], [0, 0], [0, 10], [5.8, 10]], [[0, 5.0], [4.4, 5.0]]]],
            'F' => [5.6, [[[5.6, 0], [0, 0], [0, 10]], [[0, 5.0], [4.4, 5.0]]]],
            'G' => [6.2, [[[6.2, 2.0], [4.8, 0.5], [3.0, 0], [1.4, 0.7], [0, 3.0], [0, 7.0], [1.4, 9.3], [3.0, 10], [4.8, 9.4], [6.2, 7.6], [6.2, 5.6], [3.6, 5.6]]]],
            'H' => [6.0, [[[0, 0], [0, 10]], [[6, 0], [6, 10]], [[0, 5.0], [6, 5.0]]]],
            'I' => [4.0, [[[0, 0], [4, 0]], [[2, 0], [2, 10]], [[0, 10], [4, 10]]]],
            'J' => [5.4, [[[1.2, 0], [5.4, 0]], [[3.6, 0], [3.6, 7.6], [2.6, 9.6], [1.0, 10], [0, 8.4]]]],
            'K' => [6.0, [[[0, 0], [0, 10]], [[6.0, 0], [0.6, 5.6]], [[2.2, 4.2], [6.0, 10]]]],
            'L' => [5.4, [[[0, 0], [0, 10], [5.4, 10]]]],
            'M' => [7.2, [[[0, 10], [0, 0], [3.6, 5.6], [7.2, 0], [7.2, 10]]]],
            'N' => [6.4, [[[0, 10], [0, 0], [6.4, 10], [6.4, 0]]]],
            'O' => [6.4, [[[3.2, 0], [5.2, 1.0], [6.4, 3.2], [6.4, 6.8], [5.2, 9.0], [3.2, 10], [1.2, 9.0], [0, 6.8], [0, 3.2], [1.2, 1.0], [3.2, 0]]]],
            'P' => [6.0, [[[0, 10], [0, 0], [4.0, 0], [6.0, 1.8], [6.0, 4.0], [4.0, 5.8], [0, 5.8]]]],
            'Q' => [6.6, [
                [[3.2, 0], [5.2, 1.0], [6.4, 3.2], [6.4, 6.8], [5.2, 9.0], [3.2, 10], [1.2, 9.0], [0, 6.8], [0, 3.2], [1.2, 1.0], [3.2, 0]],
                [[4.0, 7.0], [6.6, 10.4]],
            ]],
            'R' => [6.2, [
                [[0, 10], [0, 0], [4.0, 0], [6.0, 1.8], [6.0, 3.8], [4.0, 5.6], [0, 5.6]],
                [[3.2, 5.6], [6.2, 10]],
            ]],
            'S' => [6.0, [[[6.0, 1.6], [4.4, 0.2], [2.0, 0], [0.4, 1.0], [0, 2.8], [0.8, 4.2], [5.0, 5.8], [6.0, 7.2], [5.6, 9.2], [3.6, 10], [1.4, 9.8], [0, 8.4]]]],
            'T' => [6.0, [[[0, 0], [6, 0]], [[3, 0], [3, 10]]]],
            'U' => [6.0, [[[0, 0], [0, 7.0], [1.6, 9.6], [3.0, 10], [4.4, 9.6], [6.0, 7.0], [6.0, 0]]]],
            'V' => [6.0, [[[0, 0], [3, 10], [6, 0]]]],
            'W' => [8.4, [[[0, 0], [1.7, 10], [4.2, 3.6], [6.7, 10], [8.4, 0]]]],
            'X' => [6.0, [[[0, 0], [6, 10]], [[6, 0], [0, 10]]]],
            'Y' => [6.0, [[[0, 0], [3, 5.2], [6, 0]], [[3, 5.2], [3, 10]]]],
            'Z' => [6.0, [[[0, 0], [6, 0], [0, 10], [6, 10]]]],

            '0' => [6.0, [
                [[3.0, 0], [4.9, 1.0], [6.0, 3.2], [6.0, 6.8], [4.9, 9.0], [3.0, 10], [1.1, 9.0], [0, 6.8], [0, 3.2], [1.1, 1.0], [3.0, 0]],
                [[1.1, 8.2], [4.9, 1.8]],
            ]],
            '1' => [4.2, [[[0, 2.2], [2.2, 0], [2.2, 10]], [[0.2, 10], [4.2, 10]]]],
            '2' => [6.0, [[[0, 2.2], [1.6, 0.3], [3.6, 0], [5.4, 1.0], [6.0, 3.0], [5.0, 4.8], [0, 10], [6.0, 10]]]],
            '3' => [6.0, [
                [[0.2, 1.0], [2.0, 0], [4.4, 0], [6.0, 1.4], [6.0, 3.4], [4.2, 4.8], [2.4, 4.8]],
                [[2.4, 4.8], [4.6, 5.0], [6.0, 6.4], [6.0, 8.6], [4.4, 10], [2.0, 10], [0.2, 9.0]],
            ]],
            '4' => [6.0, [[[4.4, 10], [4.4, 0], [0, 7.2], [6.0, 7.2]]]],
            '5' => [6.0, [[[5.6, 0], [0.8, 0], [0.2, 4.2], [2.0, 3.2], [4.0, 3.2], [6.0, 5.0], [6.0, 8.0], [4.2, 10], [2.0, 10], [0, 9.0]]]],
            '6' => [6.0, [[[5.2, 0.4], [3.0, 0], [1.0, 1.6], [0, 4.8], [0, 7.6], [1.6, 9.8], [3.6, 10], [5.4, 8.8], [6.0, 6.8], [4.8, 4.8], [2.6, 4.4], [0.6, 5.6]]]],
            '7' => [6.0, [[[0, 0], [6, 0], [2.2, 10]]]],
            '8' => [6.0, [
                [[3.0, 4.7], [1.0, 3.8], [0.6, 2.0], [2.0, 0.2], [4.0, 0.2], [5.4, 2.0], [5.0, 3.8], [3.0, 4.7]],
                [[3.0, 4.7], [5.4, 5.8], [6.0, 7.8], [4.4, 10], [1.6, 10], [0, 7.8], [0.6, 5.8], [3.0, 4.7]],
            ]],
            '9' => [6.0, [[[0.8, 9.6], [3.0, 10], [5.0, 8.4], [6.0, 5.2], [6.0, 2.4], [4.4, 0.2], [2.4, 0], [0.6, 1.2], [0, 3.2], [1.2, 5.2], [3.4, 5.6], [5.4, 4.4]]]],

            '.' => [2.0, [$dot(1.0, 9.4)]],
            ',' => [2.4, [[[1.3, 9.2], [0.4, 11.4]]]],
            ':' => [2.0, [$dot(1.0, 3.4), $dot(1.0, 9.4)]],
            ';' => [2.4, [$dot(1.3, 3.4), [[1.3, 9.2], [0.4, 11.4]]]],
            '-' => [4.8, [[[0.4, 5.4], [4.4, 5.4]]]],
            '_' => [6.0, [[[0, 10.8], [6, 10.8]]]],
            '+' => [6.0, [[[3, 2.0], [3, 8.0]], [[0, 5.0], [6, 5.0]]]],
            '=' => [6.0, [[[0, 3.6], [6, 3.6]], [[0, 7.0], [6, 7.0]]]],
            '/' => [5.0, [[[5.0, -0.6], [0, 10.6]]]],
            '\\' => [5.0, [[[0, -0.6], [5.0, 10.6]]]],
            '|' => [1.4, [[[0.7, -0.6], [0.7, 10.6]]]],
            '(' => [3.2, [[[3.2, -0.6], [0.6, 3.0], [0.6, 7.0], [3.2, 10.6]]]],
            ')' => [3.2, [[[0, -0.6], [2.6, 3.0], [2.6, 7.0], [0, 10.6]]]],
            '[' => [3.0, [[[3.0, -0.4], [0.6, -0.4], [0.6, 10.4], [3.0, 10.4]]]],
            ']' => [3.0, [[[0, -0.4], [2.4, -0.4], [2.4, 10.4], [0, 10.4]]]],
            '<' => [5.0, [[[4.4, 1.0], [0.4, 5.4], [4.4, 9.8]]]],
            '>' => [5.0, [[[0.6, 1.0], [4.6, 5.4], [0.6, 9.8]]]],
            '!' => [1.8, [[[0.9, 0], [0.9, 7.0]], $dot(0.9, 9.4)]],
            '?' => [5.6, [[[0.2, 2.0], [1.8, 0.2], [3.8, 0.2], [5.4, 1.8], [5.2, 3.8], [2.8, 5.6], [2.8, 7.0]], $dot(2.8, 9.4)]],
            '*' => [6.0, [[[3, 1.0], [3, 7.0]], [[0.6, 2.4], [5.4, 6.0]], [[5.4, 2.4], [0.6, 6.0]]]],
            '#' => [6.8, [[[2.2, 0], [1.2, 10]], [[5.2, 0], [4.2, 10]], [[0, 3.2], [6.4, 3.2]], [[0, 6.8], [6.4, 6.8]]]],
            '%' => [8.0, [[[8.0, 0], [0, 10]], $ring(1.7, 1.7, 1.5), $ring(6.3, 8.3, 1.5)]],
            '$' => [6.0, [
                [[6.0, 1.6], [4.4, 0.2], [2.0, 0], [0.4, 1.0], [0, 2.8], [0.8, 4.2], [5.0, 5.8], [6.0, 7.2], [5.6, 9.2], [3.6, 10], [1.4, 9.8], [0, 8.4]],
                [[3.0, -1.4], [3.0, 11.4]],
            ]],
            '~' => [6.4, [[[0, 6.2], [1.6, 4.6], [3.2, 6.2], [4.8, 7.8], [6.4, 6.2]]]],
            '°' => [3.6, [$ring(1.8, 1.8, 1.6)]],
            '•' => [4.2, [$dot(2.1, 5.2)]],
            '×' => [5.6, [[[0.8, 2.6], [4.8, 7.4]], [[4.8, 2.6], [0.8, 7.4]]]],
            '→' => [9.2, [[[0, 5.2], [8.4, 5.2]], [[5.8, 2.4], [8.8, 5.2], [5.8, 8.0]]]],
            '↑' => [8.0, [[[4.0, 10], [4.0, 0.6]], [[1.0, 3.6], [4.0, 0.6], [7.0, 3.6]]]],
            '↓' => [8.0, [[[4.0, 0.6], [4.0, 10]], [[1.0, 7.0], [4.0, 10], [7.0, 7.0]]]],
        ];
    }

    public static function keys(string $text): array
    {
        $glyphs = self::glyphs();
        $chars = preg_split('//u', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($chars as $ch) {
            if (isset($glyphs[$ch])) {
                $out[] = $ch;
            } elseif ($ch === "\u{2192}" || $ch === '>') {
                $out[] = '→';
            } elseif (trim($ch) === '') {
                $out[] = ' ';
            }
        }
        return $out;
    }

    public static function glyph(string $key): array
    {
        return self::glyphs()[$key] ?? [3.6, []];
    }
}

final class PersianShaper
{
    private const FORMS = [
        'ا' => ["\u{FE8D}", "\u{FE8E}", "\u{FE8D}", "\u{FE8E}"],
        'آ' => ["\u{FE81}", "\u{FE82}", "\u{FE81}", "\u{FE82}"],
        'أ' => ["\u{FE83}", "\u{FE84}", "\u{FE83}", "\u{FE84}"],
        'إ' => ["\u{FE87}", "\u{FE88}", "\u{FE87}", "\u{FE88}"],
        'ب' => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        'پ' => ["\u{FB56}", "\u{FB57}", "\u{FB58}", "\u{FB59}"],
        'ت' => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        'ث' => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        'ج' => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        'چ' => ["\u{FB7A}", "\u{FB7B}", "\u{FB7C}", "\u{FB7D}"],
        'ح' => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        'خ' => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        'د' => ["\u{FEA9}", "\u{FEAA}", "\u{FEA9}", "\u{FEAA}"],
        'ذ' => ["\u{FEAB}", "\u{FEAC}", "\u{FEAB}", "\u{FEAC}"],
        'ر' => ["\u{FEAD}", "\u{FEAE}", "\u{FEAD}", "\u{FEAE}"],
        'ز' => ["\u{FEAF}", "\u{FEB0}", "\u{FEAF}", "\u{FEB0}"],
        'ژ' => ["\u{FB8A}", "\u{FB8B}", "\u{FB8A}", "\u{FB8B}"],
        'س' => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        'ش' => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        'ص' => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        'ض' => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        'ط' => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        'ظ' => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        'ع' => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        'غ' => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        'ف' => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        'ق' => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        'ک' => ["\u{FB8E}", "\u{FB8F}", "\u{FB90}", "\u{FB91}"],
        'ك' => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        'گ' => ["\u{FB92}", "\u{FB93}", "\u{FB94}", "\u{FB95}"],
        'ل' => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        'م' => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        'ن' => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        'ه' => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        'و' => ["\u{FEED}", "\u{FEEE}", "\u{FEED}", "\u{FEEE}"],
        'ی' => ["\u{FBFC}", "\u{FBFD}", "\u{FBFE}", "\u{FBFF}"],
        'ي' => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
        'ئ' => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        'ء' => ["\u{FE80}", "\u{FE80}", "\u{FE80}", "\u{FE80}"],
        'ة' => ["\u{FE93}", "\u{FE94}", "\u{FE93}", "\u{FE94}"],
    ];

    private const NON_CONNECTING = ['ا', 'آ', 'أ', 'إ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'و', 'ء', 'ة'];

    private const LIGATURES = [
        'لا' => ["\u{FEFB}", "\u{FEFC}"],
        'لآ' => ["\u{FEF5}", "\u{FEF6}"],
        'لأ' => ["\u{FEF7}", "\u{FEF8}"],
        'لإ' => ["\u{FEF9}", "\u{FEFA}"],
    ];

    public static function containsPersian(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public static function shape(string $text): string
    {
        if (!self::containsPersian($text)) {
            return $text;
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $merged = [];
        for ($i = 0; $i < count($chars); $i++) {
            $pair = $chars[$i] . ($chars[$i + 1] ?? '');
            if (isset(self::LIGATURES[$pair])) {
                $merged[] = ['lig' => self::LIGATURES[$pair]];
                $i++;
                continue;
            }
            $merged[] = ['ch' => $chars[$i]];
        }

        $shaped = [];
        $count = count($merged);
        for ($i = 0; $i < $count; $i++) {
            $prevJoins = self::joinsForward($merged[$i - 1] ?? null);
            $item = $merged[$i];

            if (isset($item['lig'])) {
                $shaped[] = $prevJoins ? $item['lig'][1] : $item['lig'][0];
                continue;
            }

            $ch = $item['ch'];
            if (!isset(self::FORMS[$ch])) {
                $shaped[] = $ch;
                continue;
            }
            $nextJoins = self::joinsBackward($merged[$i + 1] ?? null);
            $selfJoins = !in_array($ch, self::NON_CONNECTING, true);
            $form = match (true) {
                $prevJoins && $nextJoins && $selfJoins => 3,
                $prevJoins && (!$nextJoins || !$selfJoins) => 1,
                !$prevJoins && $nextJoins && $selfJoins => 2,
                default => 0,
            };
            $shaped[] = self::FORMS[$ch][$form];
        }

        return self::toVisualOrder($shaped);
    }

    private static function joinsForward(?array $prev): bool
    {
        if ($prev === null) {
            return false;
        }
        if (isset($prev['lig'])) {
            return false;
        }
        return isset(self::FORMS[$prev['ch']]) && !in_array($prev['ch'], self::NON_CONNECTING, true);
    }

    private static function joinsBackward(?array $next): bool
    {
        if ($next === null) {
            return false;
        }
        if (isset($next['lig'])) {
            return true;
        }
        return isset(self::FORMS[$next['ch']]);
    }

    private static function toVisualOrder(array $shaped): string
    {
        $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '<' => '>', '>' => '<'];
        $runs = [];
        $ltrRun = [];

        foreach ($shaped as $ch) {
            $isLtr = (bool) preg_match('/[0-9A-Za-z%\$\.,:\-\+\/\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch);
            if ($isLtr) {
                $ltrRun[] = $ch;
                continue;
            }
            if (!empty($ltrRun)) {
                $runs[] = implode('', $ltrRun);
                $ltrRun = [];
            }
            $runs[] = $mirror[$ch] ?? $ch;
        }
        if (!empty($ltrRun)) {
            $runs[] = implode('', $ltrRun);
        }

        return implode('', array_reverse($runs));
    }
}

final class CardCanvas
{
    private \GdImage $im;
    private int $scale;
    private array $colorCache = [];

    private static array $ttfCalibration = [];

    public function __construct(private int $width, private int $height, ?int $scale = null)
    {
        $this->scale = max(1, min(4, $scale ?? CardConfig::renderScale()));
        $im = imagecreatetruecolor($this->width * $this->scale, $this->height * $this->scale);
        if ($im === false) {
            throw new RuntimeException('imagecreatetruecolor failed');
        }
        $this->im = $im;
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
    }

    public function destroy(): void
    {
        imagedestroy($this->im);
    }

    private function color(array $rgb, float $opacity = 1.0): int
    {
        $alpha = (int) round((1.0 - max(0.0, min(1.0, $opacity))) * 127);
        $key = $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2] . ',' . $alpha;
        if (isset($this->colorCache[$key])) {
            return $this->colorCache[$key];
        }
        $c = imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
        return $this->colorCache[$key] = ($c === false ? 0 : $c);
    }

    public static function mix(array $fg, array $bg, float $amount): array
    {
        $t = max(0.0, min(1.0, $amount));
        return [
            (int) round($fg[0] * $t + $bg[0] * (1 - $t)),
            (int) round($fg[1] * $t + $bg[1] * (1 - $t)),
            (int) round($fg[2] * $t + $bg[2] * (1 - $t)),
        ];
    }

    public function backdrop(array $top, array $bottom, array $glows): void
    {
        $bw = max(120, (int) round($this->width / 2));
        $bh = max(120, (int) round($this->height / 2));
        $bg = imagecreatetruecolor($bw, $bh);
        if ($bg === false) {
            return;
        }
        imagealphablending($bg, true);
        imagesavealpha($bg, false);

        for ($y = 0; $y < $bh; $y++) {
            $t = $bh > 1 ? $y / ($bh - 1) : 0.0;
            $m = self::mix($bottom, $top, $t);
            $c = imagecolorallocate($bg, $m[0], $m[1], $m[2]);
            imagefilledrectangle($bg, 0, $y, $bw, $y, $c === false ? 0 : $c);
        }

        $k = $bw / $this->width;
        foreach ($glows as [$cx, $cy, $radius, $rgb, $strength]) {
            $steps = 40;

            $per = 1 - pow(1 - max(0.0, min(1.0, $strength)), 1 / $steps);
            $col = imagecolorallocatealpha($bg, $rgb[0], $rgb[1], $rgb[2], (int) round((1 - $per) * 127));
            if ($col === false) {
                continue;
            }
            for ($i = $steps; $i >= 1; $i--) {
                $d = (int) round($radius * ($i / $steps) * $k * 2);
                imagefilledellipse($bg, (int) round($cx * $k), (int) round($cy * $k), $d, $d, $col);
            }
        }

        imagecopyresampled(
            $this->im,
            $bg,
            0, 0, 0, 0,
            $this->width * $this->scale,
            $this->height * $this->scale,
            $bw,
            $bh
        );
        imagedestroy($bg);
    }

    private function roundRectPoints(float $x, float $y, float $w, float $h, float $r): array
    {
        $s = $this->scale;
        $r = max(0.0, min($r, min($w, $h) / 2));
        $x *= $s; $y *= $s; $w *= $s; $h *= $s; $r *= $s;

        $pts = [];
        $corners = [
            [$x + $w - $r, $y + $r, -M_PI / 2, 0.0],
            [$x + $w - $r, $y + $h - $r, 0.0, M_PI / 2],
            [$x + $r, $y + $h - $r, M_PI / 2, M_PI],
            [$x + $r, $y + $r, M_PI, 3 * M_PI / 2],
        ];
        $seg = 10;
        foreach ($corners as [$cx, $cy, $a0, $a1]) {
            for ($i = 0; $i <= $seg; $i++) {
                $a = $a0 + ($a1 - $a0) * ($i / $seg);
                $pts[] = $cx + cos($a) * $r;
                $pts[] = $cy + sin($a) * $r;
            }
        }
        return array_map(static fn($v) => (float) $v, $pts);
    }

    public function roundRect(float $x, float $y, float $w, float $h, float $r, array $rgb, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $pts = array_map('intval', array_map('round', $this->roundRectPoints($x, $y, $w, $h, $r)));
        imagefilledpolygon($this->im, $pts, $this->color($rgb, $opacity));
    }

    public function glassPanel(
        float $x,
        float $y,
        float $w,
        float $h,
        float $r,
        array $tint = CardPalette::GLASS,
        float $fillOpacity = 0.07,
        float $borderOpacity = 0.20,
        float $borderWidth = 1.6,
        bool $topHighlight = true,
        float $knockout = 0.0,
    ): void {
        $this->roundRect($x, $y, $w, $h, $r, $tint, $borderOpacity);
        if ($knockout > 0.0) {
            $this->roundRect(
                $x + $borderWidth,
                $y + $borderWidth,
                $w - 2 * $borderWidth,
                $h - 2 * $borderWidth,
                max(0.0, $r - $borderWidth),
                CardPalette::BG_BOTTOM,
                $knockout
            );
        }
        $this->roundRect(
            $x + $borderWidth,
            $y + $borderWidth,
            $w - 2 * $borderWidth,
            $h - 2 * $borderWidth,
            max(0.0, $r - $borderWidth),
            $tint,
            $fillOpacity
        );
        if ($topHighlight && $h > 12) {
            $inset = $borderWidth + 1.5;
            $this->roundRect($x + $r * 0.6, $y + $inset, max(0.0, $w - $r * 1.2), 1.4, 0.7, CardPalette::WHITE, 0.16);
        }
    }

    public function insetPanel(float $x, float $y, float $w, float $h, float $r, array $rim = CardPalette::GLASS, float $darkness = 0.34, float $rimOpacity = 0.16, float $rimWidth = 1.5, ?array $tint = null): void
    {
        $this->roundRect($x, $y, $w, $h, $r, $rim, $rimOpacity);

        $fill = $tint === null ? CardPalette::BG_BOTTOM : self::mix(CardPalette::BG_BOTTOM, $tint, 0.86);
        $this->roundRect($x + $rimWidth, $y + $rimWidth, $w - 2 * $rimWidth, $h - 2 * $rimWidth, max(0.0, $r - $rimWidth), $fill, $darkness);
    }

    public function halo(float $cx, float $cy, float $radius, array $rgb, float $strength = 0.30, int $steps = 22): void
    {
        $s = $this->scale;
        $per = 1 - pow(1 - max(0.0, min(1.0, $strength)), 1 / max(1, $steps));
        $color = $this->color($rgb, $per);
        for ($i = $steps; $i >= 1; $i--) {
            $d = (int) round($radius * ($i / $steps) * $s * 2);
            imagefilledellipse($this->im, (int) round($cx * $s), (int) round($cy * $s), $d, $d, $color);
        }
    }

    public function icon(string $name, float $x, float $y, float $size, array $rgb, float $weight = 0.11, float $opacity = 1.0): void
    {
        $paths = CardIcons::paths($name);
        if (empty($paths)) {
            return;
        }
        $s = $this->scale;
        $unit = ($size / 10) * $s;
        $color = $this->color($rgb, $opacity);
        $stroke = max(1.0, $size * $weight * $s);
        foreach ($paths as $path) {
            $pts = [];
            foreach ($path as $p) {
                $pts[] = [($x * $s) + $p[0] * $unit, ($y * $s) + $p[1] * $unit];
            }
            $this->strokePath($pts, $stroke, $color);
        }
    }

    public function image(string $path, float $x, float $y, float $boxW, float $boxH, float $opacity = 1.0): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $src = self::loadImage($path);
        if ($src === false) {
            return false;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            imagedestroy($src);
            return false;
        }

        $s = $this->scale;
        $ratio = min(($boxW * $s) / $sw, ($boxH * $s) / $sh);
        $dw = max(1, (int) round($sw * $ratio));
        $dh = max(1, (int) round($sh * $ratio));
        $dx = (int) round($x * $s + (($boxW * $s) - $dw) / 2);
        $dy = (int) round($y * $s + (($boxH * $s) - $dh) / 2);

        imagealphablending($src, true);
        if ($opacity >= 1.0) {
            imagecopyresampled($this->im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        } else {
            $tmp = imagecreatetruecolor($dw, $dh);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefilledrectangle($tmp, 0, 0, $dw, $dh, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
            for ($py = 0; $py < $dh; $py++) {
                for ($px = 0; $px < $dw; $px++) {
                    $c = imagecolorat($tmp, $px, $py);
                    $a = ($c >> 24) & 0x7F;
                    $faded = min(127, (int) round($a + (127 - $a) * (1 - $opacity)));
                    imagesetpixel($tmp, $px, $py, ($faded << 24) | ($c & 0xFFFFFF));
                }
            }
            imagealphablending($this->im, true);
            imagecopy($this->im, $tmp, $dx, $dy, 0, 0, $dw, $dh);
            imagedestroy($tmp);
        }
        imagedestroy($src);
        return true;
    }

    private static function loadImage(string $path)
    {
        $info = @getimagesize($path);
        $type = is_array($info) ? ($info[2] ?? null) : null;
        $im = match ($type) {
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($im === false) {
            return false;
        }
        imagealphablending($im, true);
        imagesavealpha($im, true);
        return $im;
    }

    public function polyline(array $points, float $width, array $rgb, float $opacity = 1.0): void
    {
        if (count($points) < 2) {
            return;
        }
        $s = $this->scale;
        $pts = [];
        foreach ($points as $p) {
            $pts[] = [$p[0] * $s, $p[1] * $s];
        }
        $this->strokePath($pts, max(1.0, $width * $s), $this->color($rgb, $opacity));
    }

    public function filledPolygon(array $points, array $rgb, float $opacity = 1.0): void
    {
        if (count($points) < 3) {
            return;
        }
        $s = $this->scale;
        $pts = [];
        foreach ($points as $p) {
            $pts[] = (int) round($p[0] * $s);
            $pts[] = (int) round($p[1] * $s);
        }
        imagefilledpolygon($this->im, $pts, $this->color($rgb, $opacity));
    }

    public function dashedRule(float $x, float $y, float $w, array $rgb, float $opacity = 0.20, float $dash = 7.0, float $gap = 6.0, float $thickness = 1.6): void
    {
        for ($cx = $x; $cx < $x + $w; $cx += $dash + $gap) {
            $this->roundRect($cx, $y, min($dash, $x + $w - $cx), $thickness, $thickness / 2, $rgb, $opacity);
        }
    }

    public function rule(float $x, float $y, float $w, array $rgb, float $opacity = 0.12, float $thickness = 1.4): void
    {
        $this->roundRect($x, $y, $w, $thickness, $thickness / 2, $rgb, $opacity);
    }

    public function text(
        string $text,
        float $x,
        float $y,
        float $size,
        array $rgb,
        string $align = 'left',
        float $weight = 0.115,
        float $tracking = 1.0,
    ): float {
        $width = $this->textWidth($text, $size, $tracking, $weight);
        $startX = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };

        $ttf = CardConfig::fontPath($this->isBold($weight));
        if ($ttf !== null) {
            $this->drawTtf($text, $startX, $y, $size, $rgb, $ttf);
            return $width;
        }
        $this->drawVector($text, $startX, $y, $size, $rgb, $weight, $tracking);
        return $width;
    }

    public function textWidth(string $text, float $size, float $tracking = 1.0, float $weight = 0.115): float
    {
        $ttf = CardConfig::fontPath($this->isBold($weight));
        if ($ttf !== null) {
            $box = @imagettfbbox($this->ttfSize($size, $ttf), 0, $ttf, $this->ttfText($text));
            return $box === false ? 0.0 : (float) abs($box[2] - $box[0]);
        }

        $unit = $size / 10;
        $keys = VectorFont::keys($text);
        $w = 0.0;
        foreach ($keys as $i => $key) {
            [$adv] = VectorFont::glyph($key);
            $w += $adv * $unit;
            if ($i < count($keys) - 1) {
                $w += VectorFont::TRACKING * $tracking * $unit;
            }
        }
        return $w;
    }

    public function fitTextSize(string $text, float $size, float $maxWidth, float $minSize = 16.0, float $tracking = 1.0, float $weight = 0.115, float $step = 2.0): float
    {
        while ($size > $minSize && $this->textWidth($text, $size, $tracking, $weight) > $maxWidth) {
            $size -= $step;
        }
        return max($minSize, $size);
    }

    public function wrapText(string $text, float $size, float $maxWidth, int $maxLines = 2, float $tracking = 1.0, float $weight = 0.115): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $words = array_values(array_filter($words, static fn($w) => $w !== ''));
        $lines = [];
        $current = '';
        $i = 0;
        $n = count($words);

        while ($i < $n) {
            $word = $words[$i];
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current === '' || $this->textWidth($candidate, $size, $tracking, $weight) <= $maxWidth) {
                $current = $candidate;
                $i++;
                continue;
            }
            $lines[] = $current;
            $current = '';
            if (count($lines) >= $maxLines) {
                break;
            }
        }

        $truncated = $i < $n;
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        } elseif ($current !== '') {
            $truncated = true;
        }

        if ($truncated && !empty($lines)) {
            $last = $lines[count($lines) - 1];
            while ($last !== '' && $this->textWidth($last . '…', $size, $tracking, $weight) > $maxWidth) {
                $last = mb_substr($last, 0, mb_strlen($last) - 1);
            }
            $lines[count($lines) - 1] = rtrim($last) . '…';
        }
        return empty($lines) ? [''] : $lines;
    }

    public function wrapTextFit(string $text, float $size, float $maxWidth, int $maxLines, float $minSize = 22.0, float $tracking = 1.0, float $weight = 0.115, float $step = 2.0, float $maxHeight = INF, float $lineHeight = 1.2): array
    {
        while (true) {
            $lines = $this->wrapText($text, $size, $maxWidth, $maxLines, $tracking, $weight);
            $last = $lines[count($lines) - 1] ?? '';
            $truncated = str_ends_with($last, '…');
            $blockHeight = count($lines) * $size * $lineHeight;
            if ((!$truncated && $blockHeight <= $maxHeight) || $size <= $minSize) {
                return [$lines, $size];
            }
            $size -= $step;
        }
    }

    private function isBold(float $weight): bool
    {
        return true;
    }

    private function drawVector(string $text, float $x, float $y, float $size, array $rgb, float $weight, float $tracking): void
    {
        $s = $this->scale;
        $unit = ($size / 10) * $s;
        $color = $this->color($rgb, 1.0);
        $strokeWidth = max(1.0, $size * $weight * $s);
        $penX = $x * $s;
        $originY = $y * $s;

        foreach (VectorFont::keys($text) as $key) {
            [$adv, $strokes] = VectorFont::glyph($key);
            foreach ($strokes as $stroke) {
                $pts = [];
                foreach ($stroke as $p) {
                    $pts[] = [$penX + $p[0] * $unit, $originY + $p[1] * $unit];
                }
                $this->strokePath($pts, $strokeWidth, $color);
            }
            $penX += ($adv + VectorFont::TRACKING * $tracking) * $unit;
        }
    }

    private function strokePath(array $pts, float $width, int $color): void
    {
        $half = $width / 2;
        $n = count($pts);
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $pts[$i];
            [$x2, $y2] = $pts[$i + 1];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len = sqrt($dx * $dx + $dy * $dy);
            if ($len < 0.0001) {
                continue;
            }
            $nx = -$dy / $len * $half;
            $ny = $dx / $len * $half;
            imagefilledpolygon($this->im, [
                (int) round($x1 + $nx), (int) round($y1 + $ny),
                (int) round($x2 + $nx), (int) round($y2 + $ny),
                (int) round($x2 - $nx), (int) round($y2 - $ny),
                (int) round($x1 - $nx), (int) round($y1 - $ny),
            ], $color);
        }
        $d = (int) round($width);
        foreach ($pts as [$px, $py]) {
            imagefilledellipse($this->im, (int) round($px), (int) round($py), $d, $d, $color);
        }
    }

    private function drawTtf(string $text, float $x, float $y, float $size, array $rgb, string $ttf): void
    {
        $s = $this->scale;
        @imagettftext(
            $this->im,
            $this->ttfSize($size, $ttf) * $s,
            0,
            (int) round($x * $s),
            (int) round(($y + $size) * $s),
            $this->color($rgb, 1.0),
            $ttf,
            $this->ttfText($text)
        );
    }

    private function ttfText(string $text): string
    {
        return PersianShaper::shape($text);
    }

    private function ttfSize(float $capHeightPx, string $ttf): float
    {
        if (!isset(self::$ttfCalibration[$ttf])) {
            $box = @imagettfbbox(100.0, 0, $ttf, 'H');
            $measured = $box === false ? 70.0 : (float) abs($box[7] - $box[1]);
            self::$ttfCalibration[$ttf] = $measured > 0 ? $measured : 70.0;
        }
        return $capHeightPx * 100.0 / self::$ttfCalibration[$ttf];
    }

    public function toPng(): string
    {
        $out = $this->im;
        if ($this->scale > 1) {
            $scaled = imagescale($this->im, $this->width, $this->height, IMG_BICUBIC);
            if ($scaled !== false) {
                $out = $scaled;
            }
        }
        ob_start();
        imagepng($out, null, 6);
        $png = (string) ob_get_clean();
        if ($out !== $this->im) {
            imagedestroy($out);
        }
        return $png;
    }
}

final class CardConfig
{
    private static array $fontCache = [];

    private static function env(string $key, ?string $default = null): ?string
    {
        return class_exists('Env') ? Env::get($key, $default) : $default;
    }

    public static function enabled(): bool
    {
        $v = strtolower((string) (self::env('SIGNAL_CARD_ENABLED', 'true') ?? 'true'));
        return self::available() && in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function available(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagefilledpolygon')
            && function_exists('imagepng');
    }

    public static function renderScale(): int
    {
        $v = (int) (self::env('CARD_RENDER_SCALE', '2') ?? '2');
        return max(1, min(4, $v ?: 2));
    }

    public static function fontPath(bool $bold = false): ?string
    {
        $key = $bold ? 'bold' : 'regular';
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }

        if (!function_exists('imagettftext') || !function_exists('imagettfbbox')) {
            return self::$fontCache[$key] = null;
        }

        $candidates = $bold
            ? [self::env('CARD_FONT_PATH_BOLD', ''), __DIR__ . '/Vazirmatn-Bold.ttf', __DIR__ . '/fonts/Vazirmatn-Bold.ttf']
            : [self::env('CARD_FONT_PATH', ''), __DIR__ . '/Vazirmatn-Regular.ttf', __DIR__ . '/fonts/Vazirmatn-Regular.ttf'];

        foreach ($candidates as $path) {
            $path = (string) ($path ?? '');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return self::$fontCache[$key] = $path;
            }
        }

        return self::$fontCache[$key] = ($bold ? self::fontPath(false) : null);
    }

    public static function supportsPersian(): bool
    {
        return self::fontPath() !== null;
    }

    public static function logoPath(): ?string
    {
        $candidates = [self::env('CARD_LOGO_PATH', '')];

        foreach (['png', 'PNG', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
            $candidates[] = __DIR__ . '/logo.' . $ext;
        }
        foreach ($candidates as $path) {
            $path = (string) ($path ?? '');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function handle(): string
    {
        return trim((string) (self::env('CARD_HANDLE', '') ?? ''));
    }

    public static function brand(): string
    {
        return (string) (self::env('CARD_BRAND', 'AUTO TRADE MARKET') ?? 'AUTO TRADE MARKET');
    }

    public static function resetFontCache(): void
    {
        self::$fontCache = [];
    }
}

final class CardIcons
{
    public static function paths(string $name): array
    {
        $ring = static function (float $cx, float $cy, float $r, int $seg = 12): array {
            $pts = [];
            for ($i = 0; $i <= $seg; $i++) {
                $a = ($i / $seg) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        return match ($name) {
            'entry' => [$ring(5, 5, 3.0), [[5, 0.6], [5, 2.2]], [[5, 7.8], [5, 9.4]], [[0.6, 5], [2.2, 5]], [[7.8, 5], [9.4, 5]]],
            'stop' => [[[5, 0.8], [9, 2.4], [9, 5.4], [5, 9.2], [1, 5.4], [1, 2.4], [5, 0.8]]],
            'target' => [$ring(5, 5, 4.0), $ring(5, 5, 2.0), [[5, 4.6], [5, 5.4]]],
            'up' => [[[5, 9.2], [5, 1.2]], [[1.6, 4.6], [5, 1.2], [8.4, 4.6]]],
            'down' => [[[5, 0.8], [5, 8.8]], [[1.6, 5.4], [5, 8.8], [8.4, 5.4]]],
            'bolt' => [[[6.4, 0.6], [2.6, 5.4], [5.2, 5.4], [3.8, 9.4], [7.6, 4.4], [5.0, 4.4], [6.4, 0.6]]],
            'lock' => [[[2.2, 4.6], [7.8, 4.6], [7.8, 9.2], [2.2, 9.2], [2.2, 4.6]], [[3.4, 4.6], [3.4, 2.8], [6.6, 2.8], [6.6, 4.6]]],
            'pulse' => [[[0.8, 5.0], [3.0, 5.0], [4.0, 2.2], [5.8, 8.0], [6.9, 5.0], [9.2, 5.0]]],
            'check' => [[[1.4, 5.2], [4.0, 7.8], [8.8, 2.2]]],
            'cross' => [[[2.0, 2.0], [8.0, 8.0]], [[8.0, 2.0], [2.0, 8.0]]],
            'bell' => [
                [[5.0, 0.6], [5.0, 1.6]],
                [[2.2, 7.4], [2.2, 4.4], [3.4, 2.0], [5.0, 1.6], [6.6, 2.0], [7.8, 4.4], [7.8, 7.4], [9.0, 8.4], [1.0, 8.4], [2.2, 7.4]],
                [[4.0, 9.2], [6.0, 9.2]],
            ],
            'drop' => [[[5.0, 0.8], [8.4, 5.6], [8.4, 7.2], [6.8, 9.0], [3.2, 9.0], [1.6, 7.2], [1.6, 5.6], [5.0, 0.8]]],
            'clock' => [$ring(5, 5, 4.0), [[5, 5], [5, 2.3]], [[5, 5], [7.1, 6.1]]],

            'mark' => [
                [[2.6, 0.6], [7.4, 0.6], [9.4, 2.6], [9.4, 7.4], [7.4, 9.4], [2.6, 9.4], [0.6, 7.4], [0.6, 2.6], [2.6, 0.6]],
                [[3.4, 7.4], [3.4, 4.6]], [[3.4, 3.2], [3.4, 2.2]], [[2.6, 4.6], [4.2, 4.6], [4.2, 3.2], [2.6, 3.2], [2.6, 4.6]],
                [[6.0, 7.6], [6.0, 3.0]], [[4.6, 5.4], [6.0, 3.0], [7.4, 5.4]],
            ],
            default => [],
        };
    }

    public static function has(string $name): bool
    {
        return !empty(self::paths($name));
    }
}

final class CardLabels
{
    private const LABELS = [
        'signal'      => ['سیگنال جدید', 'SIGNAL'],
        'market'      => ['ورود بازار', 'MARKET'],
        'market_note' => ['ورود در قیمت بازار', 'ENTER AT MARKET'],
        'entry'       => ['نقطه ورود', 'ENTRY'],
        'stop'        => ['حد ضرر', 'STOP LOSS'],
        'tp1'         => ['تارگت ۱', 'TARGET 1'],
        'tp2'         => ['تارگت ۲', 'TARGET 2'],
        'exit'        => ['خروج', 'EXIT'],
        'entry_short' => ['ورود', 'ENTRY'],
        'move'        => ['حرکت قیمت', 'PRICE MOVE'],
        'score'       => ['امتیاز', 'SCORE'],
        'leverage'    => ['اهرم', ''],
        'risk_free'   => ['ریسک فری', 'RISK FREE'],
        'closed'      => ['بسته شد', 'CLOSED'],
        'no_loss'     => ['بدون ضرر', 'NO LOSS'],
        'tp1_hit'     => ['تارگت ۱', 'TP1 HIT'],
        'tp2_hit'     => ['تارگت ۲', 'TP2 HIT'],
        'tp3_hit'     => ['تارگت ۳', 'TP3 HIT'],
        'tp4_hit'     => ['تارگت ۴', 'TP4 HIT'],
        'trail_hit'   => ['سود قفل شد', 'PROFIT LOCKED'],
        'sl_hit'      => ['حد ضرر', 'SL HIT'],
        'title_tp1'   => ['سود گرفته شد', 'PROFIT SHOT'],
        'title_tp2'   => ['تارگت ۲ فعال شد', 'TARGET 2 HIT'],
        'title_tp3'   => ['تارگت ۳ فعال شد', 'TARGET 3 HIT'],
        'title_tp4'   => ['تارگت ۴ فعال شد — بسته شد', 'TARGET 4 HIT'],
        'title_trail' => ['با سود قفل‌شده بسته شد', 'CLOSED WITH LOCKED PROFIT'],
        'title_sl'    => ['حد ضرر فعال شد', 'STOP LOSS'],
        'title_be'    => ['بدون سود و ضرر', 'BREAK EVEN'],
        'roi_with'    => ['سود با اهرم', 'ROI WITH'],
        'result_with' => ['نتیجه با اهرم', 'RESULT WITH'],

        'footer_1'    => ['Trade with AUTO TRADE.', 'Trade with AUTO TRADE.'],
        'footer_2'    => ['Automatic signals, every single day.', 'Automatic signals, every single day.'],

        'news'          => ['خبر مهم', 'MAJOR NEWS'],
        'impact_high'   => ['تأثیر بالا', 'HIGH IMPACT'],
        'impact_medium' => ['تأثیر متوسط', 'MEDIUM IMPACT'],
        'impact_low'    => ['تأثیر کم', 'LOW IMPACT'],
        'previous'      => ['قبلی', 'PREVIOUS'],
        'forecast'      => ['پیش‌بینی', 'FORECAST'],
        'actual'        => ['واقعی', 'ACTUAL'],

        'liquidations'   => ['لیکویید بیت‌کوین', 'BTC LIQUIDATIONS'],
        'total_liq'      => ['کل لیکویید شده', 'TOTAL LIQUIDATED'],
        'long_liq'       => ['لانگ لیکویید شده', 'LONGS LIQUIDATED'],
        'short_liq'      => ['شورت لیکویید شده', 'SHORTS LIQUIDATED'],
        'dominance'      => ['غلبه لیکویید', 'LIQUIDATION DOMINANCE'],
        'liq_insight_long'  => ['فشار اصلی لیکوییدیشن روی معاملات لانگ بوده است.', 'Liquidation pressure was concentrated on long positions.'],
        'liq_insight_short' => ['فشار اصلی لیکوییدیشن روی معاملات شورت بوده است.', 'Liquidation pressure was concentrated on short positions.'],
        'liq_insight_even'  => ['فشار لیکوییدیشن بین لانگ و شورت تقریباً برابر بوده است.', 'Liquidation pressure was roughly balanced between longs and shorts.'],
        'liq_no_history'    => ['داده تاریخچه زنده در دسترس نیست', 'LIVE HISTORY DATA UNAVAILABLE'],
    ];

    public static function get(string $key): string
    {
        $pair = self::LABELS[$key] ?? [$key, $key];
        return CardConfig::supportsPersian() ? $pair[0] : $pair[1];
    }

    public static function en(string $key): string
    {
        $pair = self::LABELS[$key] ?? [$key, $key];
        return $pair[1];
    }

    public static function leverage(string $leverage): string
    {
        return CardConfig::supportsPersian()
            ? self::get('leverage') . ' ' . strtolower($leverage)
            : strtoupper($leverage);
    }

    public static function score(string $score): string
    {
        return self::get('score') . ' ' . $score;
    }

    public static function roi(string $leverage, bool $profitable): string
    {
        $lead = self::get($profitable ? 'roi_with' : 'result_with');
        return CardConfig::supportsPersian()
            ? $lead . ' ' . strtolower($leverage)
            : $lead . ' ' . strtoupper($leverage) . ' LEVERAGE';
    }
}

final class CardChrome
{
    public static function backdrop(CardCanvas $c, int $w, int $h, array $accent, float $margin, float $radius): void
    {
        $c->backdrop(CardPalette::BG_TOP, CardPalette::BG_BOTTOM, [
            [$w * -0.06, $h * -0.18, $w * 0.38, CardPalette::WHITE, 0.13],
            [$w * 1.06, $h * 1.18, $w * 0.32, CardPalette::WHITE, 0.09],
        ]);

        $c->glassPanel($margin, $margin, $w - 2 * $margin, $h - 2 * $margin, $radius, CardPalette::GLASS, 0.020, 0.16, 1.6, true, 0.88);

        $c->roundRect($margin + $radius * 0.7, $margin + 1.5, $w - 2 * $margin - $radius * 1.4, 2.0, 1.0, $accent, 0.55);
    }

    public static function logo(CardCanvas $c, float $x, float $y, float $size, array $rgb, float $opacity = 1.0): void
    {
        $path = CardConfig::logoPath();
        if ($path !== null && $c->image($path, $x, $y, $size, $size, $opacity)) {
            return;
        }
        self::brandMark($c, $x, $y, $size, $opacity);
    }

    public static function brandMark(CardCanvas $c, float $x, float $y, float $size, float $opacity = 1.0, bool $tile = true): void
    {
        $u = $size / 100;
        $cyan = CardPalette::BRAND_CYAN;

        if ($tile) {
            $c->roundRect($x, $y, $size, $size, $size * 0.26, CardPalette::BG_BOTTOM, 0.85 * $opacity);
            $c->glassPanel($x, $y, $size, $size, $size * 0.26, $cyan, 0.05 * $opacity, 0.55 * $opacity, max(1.0, $u * 4), false);
        }

        $c->polyline([
            [$x + 24 * $u, $y + 68 * $u],
            [$x + 50 * $u, $y + 32 * $u],
            [$x + 76 * $u, $y + 68 * $u],
        ], $u * 9, $cyan, $opacity);
    }

    public static function grid(CardCanvas $c, float $x, float $y, float $w, float $h, int $cols, int $rows, array $rgb, float $opacity): void
    {
        for ($i = 1; $i < $cols; $i++) {
            $c->roundRect($x + $w * $i / $cols, $y, 0.6, $h, 0.3, $rgb, $opacity);
        }
        for ($j = 1; $j < $rows; $j++) {
            $c->roundRect($x, $y + $h * $j / $rows, $w, 0.6, 0.3, $rgb, $opacity);
        }
    }

    public static function liquidationChart(CardCanvas $c, float $x, float $y, float $w, float $h, array $history, string $longKey = 'long', string $shortKey = 'short'): void
    {
        $axisY = $y + $h / 2;
        $rows = array_values($history);
        $n = count($rows);
        if ($n === 0) {
            $c->roundRect($x, $axisY - 0.6, $w, 1.2, 0.6, CardTheme::TEXT_SECONDARY, 0.25);
            return;
        }
        $max = 0.0;
        foreach ($rows as $row) {
            $max = max($max, (float) ($row[$longKey] ?? 0), (float) ($row[$shortKey] ?? 0));
        }
        if ($max <= 0.0) {
            $c->roundRect($x, $axisY - 0.6, $w, 1.2, 0.6, CardTheme::TEXT_SECONDARY, 0.25);
            return;
        }

        $c->roundRect($x, $axisY - $h * 0.30, $w, 1.0, 0.5, CardTheme::TEXT_SECONDARY, 0.06);
        $c->roundRect($x, $axisY + $h * 0.30, $w, 1.0, 0.5, CardTheme::TEXT_SECONDARY, 0.06);
        $c->roundRect($x, $axisY - 0.6, $w, 1.2, 0.6, CardTheme::TEXT_SECONDARY, 0.30);

        $halfH = $h / 2 - 3.0;
        $stepX = $n > 1 ? $w / ($n - 1) : 0.0;
        $longPts = [];
        $shortPts = [];
        foreach ($rows as $i => $row) {
            $cx = $n > 1 ? $x + $i * $stepX : $x + $w / 2;
            $long = max(0.0, (float) ($row[$longKey] ?? 0));
            $short = max(0.0, (float) ($row[$shortKey] ?? 0));
            $longPts[] = [$cx, $axisY - $halfH * ($long / $max)];
            $shortPts[] = [$cx, $axisY + $halfH * ($short / $max)];
        }

        self::areaLine($c, $longPts, $axisY, CardPalette::PROFIT);
        self::areaLine($c, $shortPts, $axisY, CardPalette::LOSS);
    }

    private static function areaLine(CardCanvas $c, array $points, float $baselineY, array $rgb): void
    {
        if (count($points) < 2) {
            if (count($points) === 1) {
                $c->roundRect($points[0][0] - 2.5, $points[0][1] - 2.5, 5, 5, 2.5, $rgb, 0.9);
            }
            return;
        }
        $area = $points;
        $area[] = [$points[count($points) - 1][0], $baselineY];
        $area[] = [$points[0][0], $baselineY];
        $c->filledPolygon($area, $rgb, 0.16);
        $c->polyline($points, 2.6, $rgb, 0.85);
    }

    public static function brandLockup(CardCanvas $c, float $x, float $y, float $height): float
    {
        $path = CardConfig::logoPath();
        if ($path !== null) {
            if ($c->image($path, $x, $y, $height * 6.5, $height, 1.0)) {
                return $height * 6.5;
            }
        }

        self::brandMark($c, $x, $y, $height, 1.0);
        $textSize = $height * 0.52;
        $gap = $height * 0.34;
        $label = CardConfig::brand();
        $c->text($label, $x + $height + $gap, $y + ($height - $textSize) / 2, $textSize, CardPalette::TEXT, 'left', 0.14, 2.6);
        return $height + $gap + $c->textWidth($label, $textSize, 2.6, 0.14);
    }

    public static function chipWidth(CardCanvas $c, string $label, ?string $icon, float $textSize, float $padding, float $tracking = 1.4, float $weight = 0.125): float
    {
        $w = $c->textWidth($label, $textSize, $tracking, $weight) + $padding * 2;
        if ($icon !== null && CardIcons::has($icon)) {
            $w += $textSize * 1.05 + 9;
        }
        return $w;
    }

    public static function chip(
        CardCanvas $c,
        float $x,
        float $y,
        string $label,
        array $rgb,
        ?string $icon = null,
        float $textSize = 17,
        float $height = 38,
        float $padding = 18,
        float $fillOpacity = 0.0,
        float $borderOpacity = 0.55,
        float $tracking = 1.4,
        float $weight = 0.125,
        bool $solid = false,
    ): float {
        $w = self::chipWidth($c, $label, $icon, $textSize, $padding, $tracking, $weight);

        $ink = $rgb;
        if ($solid) {
            $c->roundRect($x, $y, $w, $height, $height / 2, $rgb, 1.0);
            $ink = CardPalette::BG_BOTTOM;
        } else {
            $c->glassPanel($x, $y, $w, $height, $height / 2, $rgb, $fillOpacity, $borderOpacity, 1.4, false, 0.94);
        }

        $tx = $x + $padding;
        if ($icon !== null && CardIcons::has($icon)) {
            $size = $textSize * 1.05;
            $c->icon($icon, $tx, $y + ($height - $size) / 2, $size, $ink, 0.15);
            $tx += $size + 9;
        }
        $c->text($label, $tx, $y + ($height - $textSize) / 2, $textSize, $ink, 'left', $weight, $tracking);
        return $w;
    }

    public static function chipRow(
        CardCanvas $c,
        float $centerX,
        float $y,
        array $chips,
        float $textSize = 17,
        float $height = 38,
        float $padding = 18,
        float $fillOpacity = 0.11,
        float $borderOpacity = 0.42,
    ): void {
        $gap = 12.0;
        $chips = array_values(array_filter($chips, static fn($ch) => trim((string) $ch[0]) !== ''));
        if (empty($chips)) {
            return;
        }

        $total = -$gap;
        foreach ($chips as $ch) {
            $total += self::chipWidth($c, (string) $ch[0], $ch[2] ?? null, $textSize, $padding) + $gap;
        }

        $x = $centerX - $total / 2;
        foreach ($chips as $ch) {
            $x += self::chip(
                $c,
                $x,
                $y,
                (string) $ch[0],
                $ch[1],
                $ch[2] ?? null,
                $textSize,
                $height,
                $padding,
                $fillOpacity,
                $borderOpacity,
                1.4,
                0.125,
                (bool) ($ch[3] ?? false)
            ) + $gap;
        }
    }

    public static function stat(
        CardCanvas $c,
        float $x,
        float $y,
        float $w,
        float $h,
        string $icon,
        string $label,
        string $value,
        array $rgb,
        float $labelSize = 17,
        float $valueSize = 26,
    ): void {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.94);

        $soft = CardCanvas::mix(CardPalette::TEXT, CardPalette::BG_BOTTOM, 0.82);

        $c->glassPanel($x, $y, $w, $h, 20, CardPalette::GLASS, 0.045, 0.18, 1.4, true, 0.90);

        $c->roundRect($x + 2.0, $y + $h * 0.20, 3.0, $h * 0.60, 1.5, $rgb, 0.85);

        $pad = 22.0;
        $iconSize = $labelSize * 1.15;
        $c->icon($icon, $x + $pad, $y + 20 + ($labelSize - $iconSize) / 2, $iconSize, $rgb, 0.13);
        $c->text($label, $x + $pad + $iconSize + 10, $y + 20, $labelSize, $muted, 'left', 0.11, 1.8);

        $maxWidth = $w - $pad * 2;
        while ($valueSize > 16 && $c->textWidth($value, $valueSize, 1.0, 0.125) > $maxWidth) {
            $valueSize -= 2;
        }
        $c->text($value, $x + $pad, $y + $h - 26 - $valueSize, $valueSize, $soft, 'left', 0.125, 1.0);
    }

    public static function footer(CardCanvas $c, float $left, float $right, float $y, string $time, array $accent, float $size = 16): void
    {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.90);
        $c->rule($left, $y, $right - $left, CardPalette::GLASS, 0.09);
        $c->roundRect($left, $y, 64, 2.0, 1.0, $accent, 0.7);
        $c->text(CardConfig::brand(), $left, $y + $size + 4, $size, CardCanvas::mix(CardPalette::TEXT, CardPalette::BG_BOTTOM, 0.72), 'left', 0.11, 2.8);
        $c->text($time, $right, $y + $size + 4, $size, $muted, 'right', 0.11, 1.5);
    }

    public static function dataGrid(CardCanvas $c, float $x, float $y, float $w, float $h, array $columns, float $labelSize = 15, float $valueSize = 26): void
    {
        $n = count($columns);
        if ($n === 0) {
            return;
        }
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.92);
        $c->glassPanel($x, $y, $w, $h, 20, CardPalette::GLASS, 0.035, 0.16, 1.4, true, 0.92);

        $colW = $w / $n;
        $pad = 26.0;
        foreach ($columns as $i => $col) {
            [$label, $value, $rgb] = $col;
            $size = $col[3] ?? $valueSize;
            $colX = $x + $i * $colW;

            $c->text($label, $colX + $pad, $y + 22, $labelSize, $muted, 'left', 0.12, 2.2);

            $maxWidth = $colW - $pad * 1.6;
            while ($size > 15 && $c->textWidth($value, $size, 1.1, 0.135) > $maxWidth) {
                $size -= 2;
            }
            $c->text($value, $colX + $pad, $y + $h - 28 - $size, $size, $rgb, 'left', 0.135, 1.1);

            if ($i > 0) {
                $c->roundRect($colX, $y + 16, 1.2, $h - 32, 0.6, CardPalette::GLASS, 0.12);
            }
        }
    }

    public static function meterBar(CardCanvas $c, float $x, float $y, float $w, float $h, float $pct, array $rgb, float $trackOpacity = 0.10): void
    {
        $r = $h / 2;
        $c->roundRect($x, $y, $w, $h, $r, CardPalette::GLASS, $trackOpacity);
        $pctClamped = max(0.0, min(1.0, $pct / 100));

        if ($w * $pctClamped > $h * 0.3) {
            $fillW = max($h, $w * $pctClamped);
            $c->roundRect($x, $y, $fillW, $h, $r, $rgb, 0.88);
        }
    }

    public static function ratioBar(CardCanvas $c, float $x, float $y, float $w, float $h, float $leftPct, array $leftColor, array $rightColor): void
    {
        $r = $h / 2;
        $leftPct = max(0.0, min(100.0, $leftPct));
        $c->roundRect($x, $y, $w, $h, $r, $rightColor, 0.30);
        $leftW = $w * ($leftPct / 100);
        if ($leftW > $h * 0.3) {
            $c->roundRect($x, $y, $leftW, $h, $r, $leftColor, 0.88);
        }
    }
}

final class SignalCard
{
    private const W = 1600;
    private const H = 900;
    private const MARGIN = 90.0;
    private const RADIUS = 46.0;

    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';

            $accent = $isLong ? CardPalette::PROFIT : CardPalette::LOSS;
            $white = CardPalette::WHITE;
            $muted = CardCanvas::mix($white, CardPalette::BG_BOTTOM, 0.46);
            $soft = CardCanvas::mix($white, CardPalette::BG_BOTTOM, 0.86);

            $c = new CardCanvas(self::W, self::H);
            $c->backdrop(CardPalette::BG_TOP, CardPalette::BG_BOTTOM, [
                [self::W * 0.5, self::H * 0.32, self::W * 0.55, $accent, 0.10],
            ]);

            $left = self::MARGIN;
            $top = self::MARGIN;
            $w = self::W - self::MARGIN * 2;
            $h = self::H - self::MARGIN * 2;
            $right = self::W - $left;
            $cx = self::W / 2;

            $c->halo($cx, self::H * 0.5, self::W * 0.30, $accent, 0.08, 30);
            $c->glassPanel($left, $top, $w, $h, self::RADIUS, $white, 0.03, 0.20, 1.6, true, 0.92);
            self::cornerMark($c, $left + 44, $top + 40, 1, $accent);
            self::cornerMark($c, self::W - $left - 44, self::H - $top - 40, -1, $accent);

            $innerLeft = $left + 50.0;
            $innerRight = $right - 50.0;
            $innerTop = $top + 42.0;

            CardChrome::brandLockup($c, $innerLeft, $innerTop, 28);
            $lev = strtoupper((string) ($d['leverage'] ?? ''));
            $head = trim(((string) ($d['symbol'] ?? '')) . ($lev !== '' ? "  •  {$lev}" : ''));
            $c->text($head, $innerRight, $innerTop + 8, 20, $muted, 'right', 0.12, 1.8);

            $heroY = self::H * 0.30;
            $chevSize = 92.0;
            $c->icon($isLong ? 'up' : 'down', $cx - $chevSize / 2, $heroY - $chevSize - 14, $chevSize, $accent, 0.20, 1.0);

            $symbol = (string) ($d['symbol'] ?? '');
            $symSize = 56.0;
            while ($symSize > 30 && $c->textWidth($symbol, $symSize, 0.6, 0.20) > $w * 0.6) {
                $symSize -= 2;
            }
            $c->text($symbol, $cx, $heroY + 18, $symSize, $white, 'center', 0.20, 0.6);

            $pillLabel = $isLong ? 'LONG' : 'SHORT';
            $pillW = $c->textWidth($pillLabel, 22, 1.4, 0.14) + 60;
            $pillY = $heroY + 18 + $symSize + 26;
            $c->roundRect($cx - $pillW / 2, $pillY, $pillW, 46, 23, $accent, 1.0);
            $c->text($pillLabel, $cx, $pillY + 12, 22, CardPalette::BG_BOTTOM, 'center', 0.14, 1.4);

            $labelY = $pillY + 46 + 44;
            $c->text('ENTRY  •  MARKET', $cx, $labelY, 17, $muted, 'center', 0.13, 3.0);

            $entryStr = CardFormat::orNA($d['entry'] ?? null);
            $numY = $labelY + 44;
            $numSize = 64.0;
            while ($numSize > 34 && $c->textWidth($entryStr, $numSize, 0.5, 0.20) > $w * 0.7) {
                $numSize -= 2;
            }
            $c->halo($cx, $numY + $numSize * 0.35, $numSize * 1.6, $accent, 0.20);
            $c->text($entryStr, $cx, $numY, $numSize, $white, 'center', 0.20, 0.5);

            $gridY = $numY + $numSize + 46;
            $gridH = min(120.0, $top + $h - 66.0 - $gridY);
            CardChrome::dataGrid($c, $innerLeft, $gridY, $innerRight - $innerLeft, $gridH, [
                ['STOP LOSS', CardFormat::orNA($d['sl'] ?? null), $muted],
                ['TARGET 1', CardFormat::orNA($d['tp1'] ?? null), $accent],
                ['TARGET 2', CardFormat::orNA($d['tp2'] ?? null), $accent],
            ], 15, 28);

            CardChrome::footer($c, $innerLeft, $innerRight, self::H - self::MARGIN - 66.0, (string) ($d['time'] ?? ''), $accent);

            $png = $c->toPng();
            $c->destroy();
            return $png;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'signal card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }

    private static function cornerMark(CardCanvas $c, float $x, float $y, int $dir, array $rgb): void
    {
        $len = 58.0;
        $gap = 22.0;
        foreach ([0, 1] as $i) {
            $ox = $dir * $i * $gap;
            $c->polyline([
                [$x + $ox, $y + $dir * $len],
                [$x + $ox + $dir * $len, $y],
            ], 10.0, $rgb, 0.05);
        }
    }
}

final class ResultCard
{
    private const PAD = 66.0;

    public static function render(array $d, string $format = 'post'): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $W = 960;
            $H = 500;
            $pad = 34.0;

            $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
            $headline = trim((string) ($d['headline'] ?? ''));
            $losing = $kind === 'sl' || str_starts_with($headline, '-');
            $accent = $losing ? CardPalette::LOSS : CardPalette::PROFIT;

            $amber = [245, 176, 66];
            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
            $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.9);
            $faint = CardCanvas::mix(CardPalette::TEXT, CardPalette::BG_BOTTOM, 0.28);
            $soft = CardCanvas::mix(CardPalette::TEXT, CardPalette::BG_BOTTOM, 0.82);

            $c = new CardCanvas($W, $H);
            $c->backdrop(CardPalette::BG_TOP, CardPalette::BG_BOTTOM, [
                [$W * 0.5, $H * 0.32, $W * 0.65, $accent, $losing ? 0.09 : 0.12],
                [$W * 0.92, $H * 0.08, $W * 0.22, $amber, 0.10],
            ]);

            $left = $pad;
            $right = $W - $pad;
            $cx = $W / 2;

            CardChrome::brandLockup($c, $left, 22, 18);
            $badgeLabel = $losing ? 'RESULT' : 'PROFIT SHOT';
            $badgeSize = $c->textWidth($badgeLabel, 12, 1.8, 0.14) + 26;
            $c->glassPanel($right - $badgeSize, 18, $badgeSize, 26, 13, $accent, 0.10, 0.55, 1.2, false, 0.90);
            $c->text($badgeLabel, $right - $badgeSize / 2, 25, 12, $accent, 'center', 0.14, 1.8);

            $chipY = 62.0;
            $symbol = self::displaySymbol((string) ($d['symbol'] ?? ''));
            $x = $left;
            $x += CardChrome::chip($c, $x, $chipY, $symbol, $soft, null, 14, 30, 13, 0.06, 0.30, 1.1, 0.13, false) + 8;
            $x += CardChrome::chip($c, $x, $chipY, $isLong ? 'LONG' : 'SHORT', $accent, $isLong ? 'up' : 'down', 13, 30, 12, 0.12, 0.6, 1.2, 0.13, true) + 8;
            CardChrome::chip($c, $x, $chipY, strtoupper((string) ($d['leverage'] ?? '')), $amber, 'bolt', 13, 30, 12, 0.12, 0.55, 1.2, 0.13, false);

            $rising = !$losing;
            $heroY = 118.0;
            $chevSize = 38.0;
            $c->halo($cx, $heroY + 10, $W * 0.30, $accent, $losing ? 0.14 : 0.20, 22);
            $c->icon($rising ? 'up' : 'down', $cx - $chevSize / 2, $heroY - $chevSize - 6, $chevSize, $accent, 0.20, 1.0);

            $labelY = $heroY + 12;
            $c->text('TOTAL PROFIT', $cx, $labelY, 12, $muted, 'center', 0.13, 3.0);

            $numY = $labelY + 26;
            $numSize = 40.0;
            while ($numSize > 22 && $c->textWidth($headline, $numSize, 0.5, 0.22) > $W * 0.7) {
                $numSize -= 2;
            }
            $c->text($headline, $cx, $numY, $numSize, $accent, 'center', 0.22, 0.5);

            $moveY = $numY + $numSize + 20;
            $c->text('PRICE MOVE ' . (string) ($d['move'] ?? '-'), $cx, $moveY, 11, $muted, 'center', 0.12, 1.4);

            $gridY = $moveY + 100;

            $gridH = 86.0;
            CardChrome::dataGrid($c, $left, $gridY, $right - $left, $gridH, [
                ['ENTRY', CardFormat::orNA($d['entry'] ?? null), $soft],
                ['EXIT', CardFormat::orNA($d['exit'] ?? null), $soft],
                ['DURATION', CardFormat::orNA($d['duration'] ?? null), $soft],
            ], 10, 16);

            $resY = $gridY + $gridH + 14;
            $checkSize = 15.0;
            $c->icon('check', $left, $resY, $checkSize, $accent, 0.30);
            $c->text(
                'TRADE CLOSED  •  ' . ($kind === 'timeout' ? 'TIME LIMIT' : ($losing ? 'STOP HONOURED' : 'PROFIT SECURED')),
                $left + $checkSize + 10,
                $resY - 1,
                12,
                $soft,
                'left',
                0.13,
                1.2
            );

            $footY = $H - $pad - 16.0;
            CardChrome::footer($c, $left, $right, $footY, (string) ($d['time'] ?? ''), $accent, 9);

            $png = $c->toPng();
            $c->destroy();
            return $png;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'result card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }

    private static function displaySymbol(string $symbol): string
    {
        $symbol = strtoupper(trim($symbol));
        foreach (['-', '_'] as $sep) {
            $symbol = str_replace($sep, '/', $symbol);
        }
        if (!str_contains($symbol, '/')) {
            foreach (['USDT', 'USDC', 'BUSD', 'FDUSD'] as $q) {
                if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                    $symbol = substr($symbol, 0, -strlen($q)) . '/' . $q;
                    break;
                }
            }
        }
        return $symbol;
    }
}
