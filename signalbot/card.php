<?php

declare(strict_types=1);

final class CardPalette
{
    public const BG      = [3, 7, 11];
    public const BG_2    = [6, 11, 18];
    public const OUTSIDE = [1, 3, 5];
    public const WHITE   = [244, 247, 250];
    public const MUTED   = [139, 150, 163];
    public const RED     = [255, 23, 79];
    public const PINK    = [255, 59, 107];
    public const CYAN    = [0, 245, 200];
    public const TEAL    = [0, 206, 170];
    public const LINE    = [23, 36, 49];
    public const DIVIDER = [42, 59, 76];
    public const STEEL   = [104, 122, 142];
    public const STEEL_2 = [70, 86, 104];
    public const INK     = [3, 7, 11];
}

final class CardFormat
{
    public static function orNA(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? 'N/A' : $value;
    }

    public static function number(string $value): ?float
    {
        $clean = str_replace([',', '%', '$', '+', ' '], '', trim($value));
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
    private float $ox = 0.0;
    private float $oy = 0.0;
    private array $colorCache = [];

    private static array $metrics = [];
    private static array $shifts = [];

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
        $r = self::channel($rgb[0]);
        $g = self::channel($rgb[1]);
        $b = self::channel($rgb[2]);
        $key = $r . ',' . $g . ',' . $b . ',' . $alpha;
        if (isset($this->colorCache[$key])) {
            return $this->colorCache[$key];
        }
        $c = imagecolorallocatealpha($this->im, $r, $g, $b, $alpha);
        return $this->colorCache[$key] = ($c === false ? 0 : $c);
    }

    private static function channel(float|int $v): int
    {
        return max(0, min(255, (int) round($v)));
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

    private function fx(float $x): float
    {
        return ($x - $this->ox) * $this->scale;
    }

    private function fy(float $y): float
    {
        return ($y - $this->oy) * $this->scale;
    }

    private function px(float $x): int
    {
        return (int) round($this->fx($x));
    }

    private function py(float $y): int
    {
        return (int) round($this->fy($y));
    }

    public function paint(callable $colorAt, int $step = 8): void
    {
        $lw = (int) ceil($this->width / $step);
        $lh = (int) ceil($this->height / $step);
        $low = imagecreatetruecolor($lw, $lh);
        if ($low === false) {
            return;
        }
        for ($y = 0; $y < $lh; $y++) {
            for ($x = 0; $x < $lw; $x++) {
                $rgb = $colorAt(($x + 0.5) * $step, ($y + 0.5) * $step);
                imagesetpixel($low, $x, $y, (self::channel($rgb[0]) << 16) | (self::channel($rgb[1]) << 8) | self::channel($rgb[2]));
            }
        }
        $up = imagescale($low, $this->width * $this->scale, $this->height * $this->scale, IMG_BILINEAR_FIXED);
        imagedestroy($low);
        if ($up === false) {
            return;
        }
        imagedestroy($this->im);
        $this->im = $up;
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $x1 = $this->px($x);
        $y1 = $this->py($y);
        imagefilledrectangle($this->im, $x1, $y1, max($x1, $this->px($x + $w) - 1), max($y1, $this->py($y + $h) - 1), $this->color($rgb, $opacity));
    }

    public function fadeLine(float $x1, float $x2, float $y, float $thickness, array $rgb, float $a0, float $a1, float $step = 2.0): void
    {
        $len = $x2 - $x1;
        if ($len <= 0) {
            return;
        }
        for ($x = $x1; $x < $x2; $x += $step) {
            $w = min($step, $x2 - $x);
            $t = ($x + $w / 2 - $x1) / $len;
            $a = $a0 + ($a1 - $a0) * $t;
            if ($a > 0.004) {
                $this->rect($x, $y, $w, $thickness, $rgb, $a);
            }
        }
    }

    public static function roundRectPath(float $x, float $y, float $w, float $h, float $r, int $seg = 12): array
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $pts = [];
        $corners = [
            [$x + $w - $r, $y + $r, -M_PI / 2],
            [$x + $w - $r, $y + $h - $r, 0.0],
            [$x + $r, $y + $h - $r, M_PI / 2],
            [$x + $r, $y + $r, M_PI],
        ];
        foreach ($corners as [$cx, $cy, $a0]) {
            for ($i = 0; $i <= $seg; $i++) {
                $a = $a0 + (M_PI / 2) * ($i / $seg);
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
        }
        return $pts;
    }

    public static function arcPath(float $cx, float $cy, float $r, float $a0, float $a1, int $seg = 48): array
    {
        $pts = [];
        for ($i = 0; $i <= $seg; $i++) {
            $a = deg2rad($a0 + ($a1 - $a0) * ($i / $seg));
            $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
        }
        return $pts;
    }

    public function roundRect(float $x, float $y, float $w, float $h, float $r, array $rgb, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $this->filledPolygon(self::roundRectPath($x, $y, $w, $h, $r), $rgb, $opacity);
    }

    public function strokeRoundRect(float $x, float $y, float $w, float $h, float $r, float $width, array $rgb, float $opacity = 1.0): void
    {
        $this->gradientPolyline(
            self::roundRectPath($x, $y, $w, $h, $r, 16),
            $width,
            static fn(): array => [$rgb, $opacity],
            true,
            8.0,
            $opacity >= 1.0
        );
    }

    public function shadeRoundRect(float $x, float $y, float $w, float $h, float $r, callable $colorAt, float $chunk = 24.0): void
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $top = $this->py($y);
        $bottom = $this->py($y + $h) - 1;
        $cache = [];
        $cacheRow = null;
        for ($row = $top; $row <= $bottom; $row++) {
            $yy = $this->oy + ($row + 0.5) / $this->scale;
            $cssRow = (int) floor($yy);
            if ($cssRow !== $cacheRow) {
                $cache = [];
                $cacheRow = $cssRow;
            }
            $dy = min($yy - $y, $y + $h - $yy);
            $inset = $dy < $r ? $r - sqrt(max(0.0, $r * $r - ($r - $dy) ** 2)) : 0.0;
            $left = $x + $inset;
            $right = $x + $w - $inset;
            $last = (int) floor(($right - $x) / $chunk);
            for ($k = (int) floor(($left - $x) / $chunk); $k <= $last; $k++) {
                $x0 = max($left, $x + $k * $chunk);
                $x1 = min($right, $x + ($k + 1) * $chunk);
                if ($x1 <= $x0) {
                    continue;
                }
                $cache[$k] ??= $this->color($colorAt($x + ($k + 0.5) * $chunk, $cssRow + 0.5));
                $p0 = $this->px($x0);
                imagefilledrectangle($this->im, $p0, $row, max($p0, $this->px($x1) - 1), $row, $cache[$k]);
            }
        }
    }

    public function shadePolygon(array $points, callable $rgbaAt, float $chunk = 24.0): void
    {
        $n = count($points);
        if ($n < 3) {
            return;
        }
        $ys = array_column($points, 1);
        $top = $this->py(min($ys));
        $bottom = $this->py(max($ys)) - 1;
        for ($row = $top; $row <= $bottom; $row++) {
            $yy = $this->oy + ($row + 0.5) / $this->scale;
            $xs = [];
            for ($i = 0; $i < $n; $i++) {
                [$x1, $y1] = $points[$i];
                [$x2, $y2] = $points[($i + 1) % $n];
                if (($y1 <= $yy && $y2 > $yy) || ($y2 <= $yy && $y1 > $yy)) {
                    $xs[] = $x1 + ($yy - $y1) * ($x2 - $x1) / ($y2 - $y1);
                }
            }
            if (count($xs) < 2) {
                continue;
            }
            $right = max($xs);
            for ($cx = min($xs); $cx < $right; $cx += $chunk) {
                $cw = min($chunk, $right - $cx);
                [$rgb, $a] = $rgbaAt($cx + $cw / 2, $yy);
                if ($a < 0.004) {
                    continue;
                }
                $x1 = $this->px($cx);
                imagefilledrectangle($this->im, $x1, $row, max($x1, $this->px($cx + $cw) - 1), $row, $this->color($rgb, $a));
            }
        }
    }

    public function chamfer(float $x, float $y, float $w, float $h, float $cut, array $from, array $to): void
    {
        $x0 = $this->px($x);
        $x1 = $this->px($x + $w) - 1;
        for ($col = $x0; $col <= $x1; $col++) {
            $u = $this->ox + ($col + 0.5) / $this->scale - $x;
            $top = $u < $cut ? $y + ($cut - $u) : $y;
            $bottom = $u > $w - $cut ? $y + $h - ($u - ($w - $cut)) : $y + $h;
            $rgb = self::mix($to, $from, $w > 0 ? $u / $w : 0.0);
            imagefilledrectangle($this->im, $col, $this->py($top), $col, max($this->py($top), $this->py($bottom) - 1), $this->color($rgb));
        }
    }

    public function disc(float $cx, float $cy, float $r, array $rgb, float $opacity = 1.0): void
    {
        $d = max(1, (int) round(2 * $r * $this->scale));
        imagefilledellipse($this->im, $this->px($cx), $this->py($cy), $d, $d, $this->color($rgb, $opacity));
    }

    public function radialDisc(float $cx, float $cy, float $r, array $inner, array $outer, float $shiftY = 0.0, int $steps = 48): void
    {
        for ($i = $steps; $i >= 1; $i--) {
            $k = $i / $steps;
            $this->disc($cx, $cy + $shiftY * (1 - $k), $r * $k, self::mix($outer, $inner, $k));
        }
    }

    public function halo(float $cx, float $cy, float $radius, array $rgb, float $strength = 0.30, int $steps = 24): void
    {
        $per = 1 - pow(1 - max(0.0, min(1.0, $strength)), 1 / max(1, $steps));
        $color = $this->color($rgb, $per);
        for ($i = $steps; $i >= 1; $i--) {
            $d = (int) round($radius * ($i / $steps) * $this->scale * 2);
            imagefilledellipse($this->im, $this->px($cx), $this->py($cy), $d, $d, $color);
        }
    }

    public function icon(string $name, float $x, float $y, float $size, array $rgb, float $weight = 0.11): void
    {
        $paths = CardIcons::paths($name);
        if (empty($paths)) {
            return;
        }
        $unit = $size / 10;
        $color = $this->color($rgb);
        $stroke = max(1.0, $size * $weight * $this->scale);
        foreach ($paths as $path) {
            $pts = [];
            foreach ($path as $p) {
                $pts[] = [$this->fx($x + $p[0] * $unit), $this->fy($y + $p[1] * $unit)];
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
        $dx = (int) round($this->fx($x) + (($boxW * $s) - $dw) / 2);
        $dy = (int) round($this->fy($y) + (($boxH * $s) - $dh) / 2);

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

    public function polyline(array $points, float $width, array $rgb): void
    {
        if (count($points) < 2) {
            return;
        }
        $pts = [];
        foreach ($points as $p) {
            $pts[] = [$this->fx($p[0]), $this->fy($p[1])];
        }
        $this->strokePath($pts, max(1.0, $width * $this->scale), $this->color($rgb));
    }

    public function gradientPolyline(array $points, float $width, callable $colorAt, bool $closed = false, float $step = 4.0, bool $caps = true): void
    {
        if ($closed && count($points) > 1) {
            $points[] = $points[0];
        }
        $n = count($points);
        if ($n < 2) {
            return;
        }
        $total = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $total += hypot($points[$i + 1][0] - $points[$i][0], $points[$i + 1][1] - $points[$i][1]);
        }
        $stroke = max(1.0, $width * $this->scale);
        $walked = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[$i + 1];
            $len = hypot($x2 - $x1, $y2 - $y1);
            $parts = max(1, (int) ceil($len / $step));
            for ($j = 0; $j < $parts; $j++) {
                $ta = $j / $parts;
                $tb = ($j + 1) / $parts;
                $ax = $x1 + ($x2 - $x1) * $ta;
                $ay = $y1 + ($y2 - $y1) * $ta;
                $bx = $x1 + ($x2 - $x1) * $tb;
                $by = $y1 + ($y2 - $y1) * $tb;
                $t = $total > 0 ? ($walked + $len * ($ta + $tb) / 2) / $total : 0.0;
                $spec = $colorAt(($ax + $bx) / 2, ($ay + $by) / 2, $t);
                [$rgb, $a] = isset($spec[0]) && is_array($spec[0]) ? $spec : [$spec, 1.0];
                if ($a < 0.004) {
                    continue;
                }
                $seg = [[$this->fx($ax), $this->fy($ay)], [$this->fx($bx), $this->fy($by)]];
                $this->strokePath($seg, $stroke, $this->color($rgb, $a), $caps);
            }
            $walked += $len;
        }
    }

    public function filledPolygon(array $points, array $rgb, float $opacity = 1.0): void
    {
        if (count($points) < 3) {
            return;
        }
        $pts = [];
        foreach ($points as $p) {
            $pts[] = $this->px($p[0]);
            $pts[] = $this->py($p[1]);
        }
        imagefilledpolygon($this->im, $pts, $this->color($rgb, $opacity));
    }

    private function strokePath(array $pts, float $width, int $color, bool $caps = true): void
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
        if (!$caps) {
            return;
        }
        $d = (int) round($width);
        foreach ($pts as [$px, $py]) {
            imagefilledellipse($this->im, (int) round($px), (int) round($py), $d, $d, $color);
        }
    }

    public function glow(array $box, float $blur, callable $draw, float $strength = 1.0, array $base = [0, 0, 0]): void
    {
        [$x, $y, $w, $h] = $box;
        $pad = $blur * 2.5;
        $x -= $pad;
        $y -= $pad;
        $f = max(2, (int) round($blur / 2.5));
        $lw = (int) (ceil(($w + 2 * $pad) / $f) * $f);
        $lh = (int) (ceil(($h + 2 * $pad) / $f) * $f);
        $layer = imagecreatetruecolor($lw, $lh);
        if ($layer === false) {
            return;
        }
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $lw - 1, $lh - 1, imagecolorallocatealpha($layer, self::channel($base[0]), self::channel($base[1]), self::channel($base[2]), 127));
        imagealphablending($layer, true);

        $saved = [$this->im, $this->scale, $this->ox, $this->oy];
        [$this->im, $this->scale, $this->ox, $this->oy] = [$layer, 1, $x, $y];
        try {
            $draw($this);
        } finally {
            [$this->im, $this->scale, $this->ox, $this->oy] = $saved;
        }

        $sw = intdiv($lw, $f);
        $sh = intdiv($lh, $f);
        $small = imagecreatetruecolor($sw, $sh);
        if ($small === false) {
            imagedestroy($layer);
            return;
        }
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $layer, 0, 0, 0, 0, $sw, $sh, $lw, $lh);
        imagedestroy($layer);
        self::soften($small, $blur / $f, $strength);

        $ratio = $f * $this->scale;
        $shift = self::scaleShift($ratio);
        $tw = $sw * $ratio;
        $th = $sh * $ratio;
        if ($shift === null) {
            $up = imagecreatetruecolor($tw, $th);
            if ($up !== false) {
                imagealphablending($up, false);
                imagesavealpha($up, true);
                imagecopyresampled($up, $small, 0, 0, 0, 0, $tw, $th, $sw, $sh);
            }
            $shift = [0, 0];
        } else {
            $up = imagescale($small, $tw, $th, IMG_BILINEAR_FIXED);
        }
        imagedestroy($small);
        if ($up === false) {
            return;
        }
        imagealphablending($this->im, true);
        imagecopy($this->im, $up, $this->px($x) + $shift[0], $this->py($y) + $shift[1], 0, 0, $tw, $th);
        imagedestroy($up);
    }

    private static function scaleShift(int $ratio): ?array
    {
        if (array_key_exists($ratio, self::$shifts)) {
            return self::$shifts[$ratio];
        }
        $n = 9;
        $probe = imagecreatetruecolor($n, $n);
        if ($probe === false) {
            return self::$shifts[$ratio] = null;
        }
        imagealphablending($probe, false);
        imagesavealpha($probe, true);
        imagefilledrectangle($probe, 0, 0, $n - 1, $n - 1, imagecolorallocatealpha($probe, 255, 255, 255, 127));
        imagesetpixel($probe, 4, 4, imagecolorallocatealpha($probe, 255, 255, 255, 0));
        $up = imagescale($probe, $n * $ratio, $n * $ratio, IMG_BILINEAR_FIXED);
        imagedestroy($probe);
        if ($up === false) {
            return self::$shifts[$ratio] = null;
        }
        $size = $n * $ratio;
        $sx = 0.0;
        $sy = 0.0;
        $sum = 0.0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $a = 127 - ((imagecolorat($up, $x, $y) >> 24) & 0x7F);
                if ($a > 0) {
                    $sx += $x * $a;
                    $sy += $y * $a;
                    $sum += $a;
                }
            }
        }
        $corner = (imagecolorat($up, 0, 0) >> 24) & 0x7F;
        imagedestroy($up);
        if ($sum <= 0.0 || $corner < 120) {
            return self::$shifts[$ratio] = null;
        }
        $center = 4.5 * $ratio - 0.5;
        return self::$shifts[$ratio] = [(int) round($center - $sx / $sum), (int) round($center - $sy / $sum)];
    }

    private static function soften(\GdImage $im, float $sigma, float $strength): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $n = $w * $h;
        $ch = [array_fill(0, $n, 0.0), array_fill(0, $n, 0.0), array_fill(0, $n, 0.0), array_fill(0, $n, 0.0)];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $a = (127 - (($c >> 24) & 0x7F)) / 127;
                if ($a <= 0.0) {
                    continue;
                }
                $i = $y * $w + $x;
                $ch[0][$i] = (($c >> 16) & 0xFF) * $a;
                $ch[1][$i] = (($c >> 8) & 0xFF) * $a;
                $ch[2][$i] = ($c & 0xFF) * $a;
                $ch[3][$i] = $a;
            }
        }
        $radius = max(0, (int) round((sqrt(4 * $sigma * $sigma + 1) - 1) / 2));
        if ($radius > 0) {
            foreach ($ch as $k => $plane) {
                for ($pass = 0; $pass < 3; $pass++) {
                    $plane = self::boxBlur($plane, $w, $h, $radius, 1, $w);
                    $plane = self::boxBlur($plane, $h, $w, $radius, $w, 1);
                }
                $ch[$k] = $plane;
            }
        }
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;
                $a = $ch[3][$i];
                if ($a <= 0.0005) {
                    imagesetpixel($im, $x, $y, 127 << 24);
                    continue;
                }
                $alpha = 127 - (int) round(min(1.0, $a * $strength) * 127);
                imagesetpixel($im, $x, $y, ($alpha << 24) | (self::channel($ch[0][$i] / $a) << 16) | (self::channel($ch[1][$i] / $a) << 8) | self::channel($ch[2][$i] / $a));
            }
        }
    }

    private static function boxBlur(array $src, int $len, int $lines, int $r, int $step, int $lineStep): array
    {
        $out = $src;
        $norm = 1.0 / (2 * $r + 1);
        for ($line = 0; $line < $lines; $line++) {
            $base = $line * $lineStep;
            $sum = 0.0;
            for ($k = -$r; $k <= $r; $k++) {
                $sum += $src[$base + max(0, min($len - 1, $k)) * $step];
            }
            for ($i = 0; $i < $len; $i++) {
                $out[$base + $i * $step] = $sum * $norm;
                $sum += $src[$base + min($len - 1, $i + $r + 1) * $step] - $src[$base + max(0, $i - $r) * $step];
            }
        }
        return $out;
    }

    private static function ttfBox(string $font, string $text): array
    {
        $box = @imagettfbbox(75.0, 0, $font, $text);
        return $box === false ? [0, 0, 0, 0, 0, 0, 0, 0] : $box;
    }

    private static function capRatio(string $font): float
    {
        if (!isset(self::$metrics[$font]['cap'])) {
            $box = self::ttfBox($font, 'H');
            $h = abs($box[7] - $box[1]);
            self::$metrics[$font]['cap'] = $h > 0 ? $h / 100.0 : 0.7;
        }
        return self::$metrics[$font]['cap'];
    }

    private static function advance(string $font, string $text): float
    {
        $key = 'a:' . $text;
        if (!isset(self::$metrics[$font][$key])) {
            $a = self::ttfBox($font, 'H' . $text . 'H');
            $b = self::ttfBox($font, 'HH');
            self::$metrics[$font][$key] = max(0.0, (($a[2] - $a[0]) - ($b[2] - $b[0])) / 100.0);
        }
        return self::$metrics[$font][$key];
    }

    public function capHeight(float $size, string $role = 'display', string $text = 'H'): float
    {
        $font = CardConfig::fontFor($role, $text);
        return $font === null ? $size * 0.72 : $size * self::capRatio($font);
    }

    public function measure(string $text, float $size, string $role = 'display', float $tracking = 0.0): float
    {
        $font = CardConfig::fontFor($role, $text);
        if ($font === null) {
            $cap = $size * 0.72;
            $keys = VectorFont::keys($text);
            $w = 0.0;
            foreach ($keys as $key) {
                $w += VectorFont::glyph($key)[0] * $cap / 10;
            }
            return $w + max(0, count($keys) - 1) * (VectorFont::TRACKING * $cap / 10 + $tracking * $size);
        }
        $shaped = PersianShaper::shape($text);
        if (abs($tracking) < 0.0001 || PersianShaper::containsPersian($text)) {
            return self::advance($font, $shaped) * $size;
        }
        $chars = mb_str_split($shaped);
        $w = 0.0;
        foreach ($chars as $ch) {
            $w += self::advance($font, $ch) * $size;
        }
        return $w + $tracking * $size * max(0, count($chars) - 1);
    }

    public function fit(string $text, float $size, float $maxWidth, string $role = 'display', float $tracking = 0.0, float $minSize = 16.0): float
    {
        while ($size > $minSize && $this->measure($text, $size, $role, $tracking) > $maxWidth) {
            $size -= 2.0;
        }
        return max($minSize, $size);
    }

    public function write(string $text, float $x, float $capTop, float $size, array $rgb, string $role = 'display', string $align = 'left', float $tracking = 0.0): float
    {
        $width = $this->measure($text, $size, $role, $tracking);
        $start = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };
        $font = CardConfig::fontFor($role, $text);
        $color = $this->color($rgb);
        if ($font === null) {
            $cap = $size * 0.72;
            $this->drawVector($text, $start, $capTop, $cap, VectorFont::TRACKING * $cap / 10 + $tracking * $size, $color);
            return $width;
        }
        $shaped = PersianShaper::shape($text);
        $baseline = $this->py($capTop + $size * self::capRatio($font));
        $pt = $size * 0.75 * $this->scale;
        if (abs($tracking) < 0.0001 || PersianShaper::containsPersian($text)) {
            @imagettftext($this->im, $pt, 0, $this->px($start), $baseline, $color, $font, $shaped);
            return $width;
        }
        $pen = $start;
        foreach (mb_str_split($shaped) as $ch) {
            @imagettftext($this->im, $pt, 0, $this->px($pen), $baseline, $color, $font, $ch);
            $pen += self::advance($font, $ch) * $size + $tracking * $size;
        }
        return $width;
    }

    private function drawVector(string $text, float $x, float $capTop, float $cap, float $gap, int $color): void
    {
        $unit = ($cap / 10) * $this->scale;
        $stroke = max(1.0, $cap * 0.14 * $this->scale);
        $penX = $this->fx($x);
        $originY = $this->fy($capTop);
        foreach (VectorFont::keys($text) as $key) {
            [$adv, $strokes] = VectorFont::glyph($key);
            foreach ($strokes as $path) {
                $pts = [];
                foreach ($path as $p) {
                    $pts[] = [$penX + $p[0] * $unit, $originY + $p[1] * $unit];
                }
                $this->strokePath($pts, $stroke, $color);
            }
            $penX += $adv * $unit + $gap * $this->scale;
        }
    }

    public function clipCorners(float $r, array $rgb): void
    {
        $color = $this->color($rgb);
        $maxX = $this->width * $this->scale - 1;
        $maxY = $this->height * $this->scale - 1;
        $rows = (int) ceil($r * $this->scale);
        for ($row = 0; $row < $rows; $row++) {
            $dy = $r - ($row + 0.5) / $this->scale;
            $cut = (int) round(($r - sqrt(max(0.0, $r * $r - $dy * $dy))) * $this->scale) - 1;
            if ($cut < 0) {
                continue;
            }
            imagefilledrectangle($this->im, 0, $row, $cut, $row, $color);
            imagefilledrectangle($this->im, $maxX - $cut, $row, $maxX, $row, $color);
            imagefilledrectangle($this->im, 0, $maxY - $row, $cut, $maxY - $row, $color);
            imagefilledrectangle($this->im, $maxX - $cut, $maxY - $row, $maxX, $maxY - $row, $color);
        }
    }

    public function toPng(): string
    {
        $out = $this->im;
        if ($this->scale > 1) {
            $scaled = imagecreatetruecolor($this->width, $this->height);
            if ($scaled !== false) {
                imagecopyresampled($scaled, $this->im, 0, 0, 0, 0, $this->width, $this->height, $this->width * $this->scale, $this->height * $this->scale);
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
    private const FONTS = [
        'display' => ['Oxanium-ExtraBold.ttf'],
        'label' => ['SpaceGrotesk-Bold.ttf'],
        'meta' => ['SpaceGrotesk-SemiBold.ttf', 'SpaceGrotesk-Bold.ttf'],
    ];

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
            && function_exists('imagescale')
            && function_exists('imagepng');
    }

    public static function renderScale(): int
    {
        $v = (int) (self::env('CARD_RENDER_SCALE', '2') ?? '2');
        return max(1, min(4, $v ?: 2));
    }

    private static function ttfSupported(): bool
    {
        return function_exists('imagettftext') && function_exists('imagettfbbox');
    }

    public static function fontPath(bool $bold = false): ?string
    {
        $key = $bold ? 'bold' : 'regular';
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }

        if (!self::ttfSupported()) {
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

    public static function font(string $role): ?string
    {
        $key = 'role:' . $role;
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }
        if (!self::ttfSupported()) {
            return self::$fontCache[$key] = null;
        }
        foreach (self::FONTS[$role] ?? [] as $file) {
            foreach ([__DIR__ . '/' . $file, __DIR__ . '/fonts/' . $file] as $path) {
                if (is_file($path) && is_readable($path)) {
                    return self::$fontCache[$key] = $path;
                }
            }
        }
        return self::$fontCache[$key] = self::fontPath(true);
    }

    public static function fontFor(string $role, string $text): ?string
    {
        return PersianShaper::containsPersian($text) ? self::fontPath(true) : self::font($role);
    }

    public static function hasCardFonts(): bool
    {
        foreach (array_keys(self::FONTS) as $role) {
            $path = self::font($role);
            if ($path === null || !in_array(basename($path), self::FONTS[$role], true)) {
                return false;
            }
        }
        return true;
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

    public static function coinLogo(string $base): ?string
    {
        $base = preg_replace('/[^A-Za-z0-9]/', '', $base) ?? '';
        if ($base === '') {
            return null;
        }
        foreach ([strtoupper($base), strtolower($base)] as $name) {
            foreach (['png', 'webp', 'jpg'] as $ext) {
                $path = __DIR__ . '/coins/' . $name . '.' . $ext;
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }
        }
        return null;
    }

    public static function brand(): string
    {
        return (string) (self::env('CARD_BRAND', 'AUTO TRADE MARKET') ?? 'AUTO TRADE MARKET');
    }

    public static function tagline(): string
    {
        return (string) (self::env('CARD_TAGLINE', 'TRADE SMARTER • NOT HARDER') ?? '');
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
        $ring = static function (float $cx, float $cy, float $r, int $seg = 28): array {
            $pts = [];
            for ($i = 0; $i <= $seg; $i++) {
                $a = ($i / $seg) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        return match ($name) {
            'up' => [[[5, 8.8], [5, 1.6]], [[2.1, 4.6], [5, 1.6], [7.9, 4.6]]],
            'down' => [[[5, 1.2], [5, 8.4]], [[2.1, 5.4], [5, 8.4], [7.9, 5.4]]],
            'stop' => [$ring(5, 5, 4.2), [[3.3, 3.3], [6.7, 6.7]], [[6.7, 3.3], [3.3, 6.7]]],
            'target' => [$ring(5, 5, 4.2), $ring(5, 5, 1.95, 18), [[4.9, 5.0], [5.1, 5.0]]],
            'entry' => [$ring(5, 5, 3.0), [[5, 0.6], [5, 2.2]], [[5, 7.8], [5, 9.4]], [[0.6, 5], [2.2, 5]], [[7.8, 5], [9.4, 5]]],
            'clock' => [$ring(5, 5, 4.25), [[5, 2.6], [5, 5], [6.6, 6.0]]],
            'check' => [[[1.8, 5.3], [4.1, 7.5], [8.4, 2.7]]],
            'cross' => [[[2.4, 2.4], [7.6, 7.6]], [[7.6, 2.4], [2.4, 7.6]]],
            'dash' => [[[2.2, 5.0], [7.8, 5.0]]],
            default => [],
        };
    }
}

final class NeonChrome
{
    public const W = 1600;
    public const H = 900;
    public const PAD = 48.0;
    public const RADIUS = 30.0;

    private const GHOST_CANDLES = [
        [120, 560, 640, 576, 44], [170, 548, 616, 560, 34], [220, 530, 600, 544, 40], [270, 540, 622, 552, 52],
        [320, 520, 590, 532, 36], [370, 506, 576, 516, 42], [420, 516, 598, 530, 50], [470, 498, 560, 508, 32],
        [520, 490, 566, 500, 46], [570, 506, 584, 520, 44], [620, 520, 598, 532, 48], [670, 512, 580, 524, 36],
        [720, 530, 612, 544, 52], [770, 548, 620, 558, 42], [820, 536, 600, 546, 36], [870, 552, 630, 564, 48],
        [920, 570, 640, 582, 40], [970, 560, 626, 570, 36], [1020, 578, 652, 590, 44], [1070, 596, 664, 606, 40],
        [1120, 588, 656, 598, 36], [1170, 604, 680, 616, 46], [1220, 620, 690, 630, 42], [1270, 612, 676, 622, 36],
        [1320, 630, 704, 640, 46], [1370, 646, 716, 656, 42], [1420, 640, 706, 650, 38], [1470, 656, 730, 668, 44],
    ];

    private const CANDLES = [
        [22, 28, 92, 42, 36, 's'], [54, 22, 96, 34, 48, 'p'], [86, 60, 132, 74, 44, 'q'], [118, 84, 138, 96, 26, 's'],
        [150, 78, 164, 92, 56, 'p'], [182, 128, 196, 140, 40, 'q'], [214, 150, 200, 162, 22, 's'], [246, 146, 238, 160, 62, 'p'],
        [278, 200, 266, 212, 40, 'q'], [310, 226, 300, 240, 46, 'q'], [342, 258, 330, 270, 44, 'p'],
    ];

    private static array $glows = [];

    public static function begin(array $rightGlow, float $rightStrength): CardCanvas
    {
        self::$glows = [
            [self::W * 0.17, self::H * 0.44, 760.0, 560.0, CardPalette::CYAN, 0.11],
            [self::W * 0.85, self::H * 0.42, 760.0, 600.0, $rightGlow, $rightStrength],
            [self::W * 0.50, self::H * 0.36, 900.0, 420.0, CardPalette::WHITE, 0.035],
        ];
        $c = new CardCanvas(self::W, self::H);
        $c->paint(static fn(float $x, float $y): array => self::bgAt($x, $y));
        self::grid($c);
        self::panels($c, $rightGlow);
        self::ghostCandles($c);
        return $c;
    }

    public static function finish(CardCanvas $c): string
    {
        self::border($c);
        $c->clipCorners(self::RADIUS, CardPalette::OUTSIDE);
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    public static function bgAt(float $x, float $y): array
    {
        $t = $y / self::H;
        $base = $t < 0.62 ? CardCanvas::mix(CardPalette::BG, CardPalette::BG_2, $t / 0.62) : CardPalette::BG;
        $c = [(float) $base[0], (float) $base[1], (float) $base[2]];
        foreach (self::$glows as [$gx, $gy, $rx, $ry, $rgb, $alpha]) {
            $d = sqrt((($x - $gx) / $rx) ** 2 + (($y - $gy) / $ry) ** 2);
            $k = $alpha * max(0.0, 1.0 - $d / 0.62);
            if ($k > 0.0) {
                $c = [
                    $c[0] * (1 - $k) + $rgb[0] * $k,
                    $c[1] * (1 - $k) + $rgb[1] * $k,
                    $c[2] * (1 - $k) + $rgb[2] * $k,
                ];
            }
        }
        return $c;
    }

    private static function stop(array $stops, float $t): array
    {
        $t = max(0.0, min(1.0, $t));
        for ($i = 1; $i < count($stops); $i++) {
            [$t1, $c1, $a1] = $stops[$i];
            if ($t <= $t1) {
                [$t0, $c0, $a0] = $stops[$i - 1];
                $k = $t1 > $t0 ? ($t - $t0) / ($t1 - $t0) : 1.0;
                return [CardCanvas::mix($c1, $c0, $k), $a0 + ($a1 - $a0) * $k];
            }
        }
        $last = $stops[count($stops) - 1];
        return [$last[1], $last[2]];
    }

    private static function grid(CardCanvas $c): void
    {
        $mask = static function (float $x, float $y): float {
            $d = sqrt((($x - self::W * 0.5) / (self::W * 0.72)) ** 2 + (($y - self::H * 0.46) / (self::H * 0.70)) ** 2);
            return $d <= 0.18 ? 1.0 : max(0.0, 1.0 - ($d - 0.18) / 0.70);
        };
        $seg = 25.0;
        for ($x = 49.0; $x < self::W; $x += 50.0) {
            for ($y = 0.0; $y < self::H; $y += $seg) {
                $a = 0.34 * $mask($x, $y + $seg / 2);
                if ($a >= 0.01) {
                    $c->rect($x, $y, 1.0, $seg, CardPalette::LINE, $a);
                }
            }
        }
        for ($y = 24.0; $y < self::H; $y += 50.0) {
            for ($x = 0.0; $x < self::W; $x += $seg) {
                $a = 0.34 * $mask($x + $seg / 2, $y);
                if ($a >= 0.01) {
                    $c->rect($x, $y, $seg, 1.0, CardPalette::LINE, $a);
                }
            }
        }
    }

    private static function panels(CardCanvas $c, array $rightTint): void
    {
        $c->shadePolygon(
            [[0, 178], [404, 178], [476, 250], [476, 574], [0, 574]],
            static fn(float $x, float $y): array => [CardPalette::CYAN, 0.07 * (1 - ($x / 476 + ($y - 178) / 396) / 2)]
        );
        $c->shadePolygon(
            [[1124, 250], [1196, 178], [1600, 178], [1600, 574], [1124, 574]],
            static fn(float $x, float $y): array => [$rightTint, 0.08 * (1 - ((1600 - $x) / 476 + ($y - 178) / 396) / 2)]
        );
        $c->polyline([[0, 178], [404, 178], [476, 250], [476, 574]], 1.0, CardPalette::LINE);
        $c->polyline([[1600, 178], [1196, 178], [1124, 250], [1124, 574]], 1.0, CardPalette::LINE);
        $c->filledPolygon([[560, 0], [600, 26], [1000, 26], [1040, 0]], CardPalette::LINE, 0.35);
        $c->polyline([[560, 0], [600, 26], [1000, 26], [1040, 0]], 1.0, CardPalette::LINE);
    }

    private static function ghostCandles(CardCanvas $c): void
    {
        foreach (self::GHOST_CANDLES as [$x, $top, $bottom, $bodyY, $bodyH]) {
            $t = $x / self::W;
            $fade = $t < 0.25 ? $t / 0.25 : ($t > 0.75 ? (1 - $t) / 0.25 : 1.0);
            $a = 0.06 * $fade;
            if ($a < 0.01) {
                continue;
            }
            $c->rect($x - 0.5, $top, 1.0, $bodyY - $top, CardPalette::MUTED, $a);
            $c->rect($x - 6, $bodyY, 12.0, $bodyH, CardPalette::MUTED, $a);
            $c->rect($x - 0.5, $bodyY + $bodyH, 1.0, $bottom - $bodyY - $bodyH, CardPalette::MUTED, $a);
        }
    }

    private static function border(CardCanvas $c): void
    {
        $stops = [
            [0.00, CardPalette::CYAN, 1.0],
            [0.26, CardPalette::CYAN, 0.55],
            [0.47, CardPalette::LINE, 0.9],
            [0.53, CardPalette::LINE, 0.9],
            [0.74, CardPalette::PINK, 0.6],
            [1.00, CardPalette::PINK, 1.0],
        ];
        $dx = sin(deg2rad(115));
        $dy = -cos(deg2rad(115));
        $len = abs(self::W * $dx) + abs(self::H * $dy);
        $at = static fn(float $x, float $y): array => self::stop($stops, (($x - self::W / 2) * $dx + ($y - self::H / 2) * $dy) / $len + 0.5);

        foreach ([[11.0, 0.03], [9.0, 0.05], [7.0, 0.08], [5.0, 0.13], [3.0, 0.20]] as [$inset, $k]) {
            $c->gradientPolyline(
                CardCanvas::roundRectPath($inset, $inset, self::W - 2 * $inset, self::H - 2 * $inset, self::RADIUS - $inset, 16),
                2.0,
                static function (float $x, float $y) use ($at, $k): array {
                    [$rgb, $a] = $at($x, $y);
                    return [$rgb, $a * $k];
                },
                true,
                6.0,
                false
            );
        }
        $c->gradientPolyline(
            CardCanvas::roundRectPath(0.75, 0.75, self::W - 1.5, self::H - 1.5, self::RADIUS - 0.75, 16),
            1.5,
            $at,
            true,
            6.0,
            false
        );
    }

    public static function header(CardCanvas $c, array $items): void
    {
        $cy = 70.0;
        self::brand($c, self::PAD, $cy);
        self::pill($c, self::W - self::PAD, $cy, $items);
    }

    private static function brand(CardCanvas $c, float $x, float $cy): void
    {
        $logo = CardConfig::logoPath();
        if ($logo !== null) {
            $info = @getimagesize($logo);
            $ratio = is_array($info) && ($info[1] ?? 0) > 0 ? $info[0] / $info[1] : 1.0;
            $h = 44.0;
            if ($c->image($logo, $x, $cy - $h / 2, min(320.0, $h * $ratio), $h)) {
                return;
            }
        }
        $size = 40.0;
        self::hexMark($c, $x, $cy - $size / 2, $size);
        $brand = CardConfig::brand();
        $c->write($brand, $x + $size + 14, $cy - $c->capHeight(17.0, 'label', $brand) / 2, 17.0, CardPalette::WHITE, 'label', 'left', 0.2);
    }

    private static function hexMark(CardCanvas $c, float $x, float $y, float $size): void
    {
        $u = $size / 44;
        $pt = static fn(float $px, float $py): array => [$x + $px * $u, $y + $py * $u];
        $hex = [$pt(22, 2), $pt(39.3, 12), $pt(39.3, 32), $pt(22, 42), $pt(4.7, 32), $pt(4.7, 12)];
        $grad = static fn(float $px, float $py): array => CardCanvas::mix(CardPalette::PINK, CardPalette::CYAN, (($px - $x) + ($py - $y)) / (2 * $size));
        $c->filledPolygon($hex, CardPalette::BG_2);
        $c->gradientPolyline($hex, 2.0 * $u, $grad, true, 2.0);
        $c->gradientPolyline([$pt(13, 29), $pt(22, 13), $pt(31, 29)], 3.0 * $u, $grad, false, 2.0);
        $c->polyline([$pt(17, 24), $pt(27, 24)], 2.5 * $u, CardPalette::WHITE);
    }

    public static function row(CardCanvas $c, float $x, float $cy, array $items, string $align = 'left', float $gap = 12.0): float
    {
        $width = -$gap;
        foreach ($items as $item) {
            $width += $gap + ($item[0] === '•' ? 5.0 : $c->measure($item[0], $item[2], $item[3], $item[4]));
        }
        $pen = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };
        foreach ($items as $item) {
            [$text, $rgb, $size, $role, $tracking] = $item;
            if ($text === '•') {
                $c->halo($pen + 2.5, $cy, 7.0, $rgb, 0.35, 8);
                $c->disc($pen + 2.5, $cy, 2.5, $rgb);
                $pen += 5.0 + $gap;
                continue;
            }
            $pen += $c->write($text, $pen, $cy - $c->capHeight($size, $role, $text) / 2, $size, $rgb, $role, 'left', $tracking) + $gap;
        }
        return $width;
    }

    private static function pill(CardCanvas $c, float $right, float $cy, array $items): void
    {
        $row = [];
        foreach ($items as $i => $item) {
            if ($i > 0) {
                $row[] = ['•', CardPalette::PINK, 0, '', 0];
            }
            $row[] = $item;
        }
        $width = -12.0;
        foreach ($row as $item) {
            $width += 12.0 + ($item[0] === '•' ? 5.0 : $c->measure($item[0], $item[2], $item[3], $item[4]));
        }
        $h = 42.0;
        $w = $width + 40.0;
        $x = $right - $w;
        $c->roundRect($x, $cy - $h / 2, $w, $h, 12.0, CardPalette::BG_2, 0.7);
        $c->strokeRoundRect($x + 0.5, $cy - $h / 2 + 0.5, $w - 1, $h - 1, 12.0, 1.0, CardPalette::LINE);
        self::row($c, $x + 20.0, $cy, $row);
    }

    public static function caption(CardCanvas $c, float $cx, float $capTop, string $text, float $size = 15.0): float
    {
        $tracking = 0.42;
        $w = $c->measure($text, $size, 'meta', $tracking);
        $cap = $c->capHeight($size, 'meta', $text);
        $c->write($text, $cx, $capTop, $size, CardPalette::MUTED, 'meta', 'center', $tracking);
        $ly = $capTop + $cap / 2;
        $c->fadeLine($cx - $w / 2 - 52, $cx - $w / 2 - 16, $ly, 1.0, CardPalette::MUTED, 0.0, 1.0);
        $c->fadeLine($cx + $w / 2 + 16, $cx + $w / 2 + 52, $ly, 1.0, CardPalette::MUTED, 1.0, 0.0);
        return $capTop + $cap;
    }

    public static function title(CardCanvas $c, float $cx, float $capTop, string $text, float $size, array $rgb, array $glowRgb, float $glowStrength, float $blur): float
    {
        $cap = $c->capHeight($size, 'display', $text);
        $w = $c->measure($text, $size);
        $c->glow([$cx - $w / 2, $capTop, $w, $cap], $blur, static function (CardCanvas $g) use ($text, $cx, $capTop, $size, $glowRgb): void {
            $g->write($text, $cx, $capTop, $size, $glowRgb, 'display', 'center');
        }, $glowStrength, $glowRgb);
        $c->write($text, $cx, $capTop, $size, $rgb, 'display', 'center');
        return $capTop + $cap;
    }

    public static function badge(CardCanvas $c, float $cx, float $y, string $label, string $icon, array $from, array $to, array $ink): float
    {
        $h = 50.0;
        $cut = 14.0;
        $size = 21.0;
        $tracking = 0.22;
        $iconSize = 22.0;
        $w = 22.0 + $iconSize + 12.0 + $c->measure($label, $size, 'display', $tracking) + 28.0;
        $x = $cx - $w / 2;
        $c->glow([$x, $y, $w, $h], 10.0, static function (CardCanvas $g) use ($x, $y, $w, $h, $cut, $from, $to): void {
            $g->chamfer($x, $y, $w, $h, $cut, $from, $to);
        }, 0.85, $from);
        $c->chamfer($x, $y, $w, $h, $cut, $from, $to);
        $c->rect($x + $cut, $y, $w - $cut, 1.0, CardPalette::WHITE, 0.32);
        $c->icon($icon, $x + 22.0, $y + ($h - $iconSize) / 2, $iconSize, $ink, 0.13);
        $c->write($label, $x + 22.0 + $iconSize + 12.0, $y + ($h - $c->capHeight($size, 'display', $label)) / 2, $size, $ink, 'display', 'left', $tracking);
        $ly = $y + $h / 2 - 1;
        $c->fadeLine($x - 82, $x - 18, $ly, 2.0, $from, 0.0, 1.0);
        $c->fadeLine($x + $w + 18, $x + $w + 82, $ly, 2.0, $from, 1.0, 0.0);
        return $y + $h;
    }

    public static function emblem(CardCanvas $c, float $cx, float $cy, string $base, float $k = 0.9): void
    {
        $at = static fn(float $deg, float $r): array => [$cx + cos(deg2rad($deg)) * $r * $k, $cy + sin(deg2rad($deg)) * $r * $k];
        $c->halo($cx, $cy, 160 * $k, CardPalette::CYAN, 0.16, 30);
        $c->gradientPolyline(CardCanvas::arcPath($cx, $cy, 150 * $k, 0, 360, 120), 1.0, static fn(): array => [CardPalette::LINE, 1.0], false, 8.0, false);
        for ($i = 0; $i < 80; $i++) {
            $deg = $i * 4.5;
            $c->gradientPolyline([$at($deg, 137), $at($deg, 143)], 1.5 * $k, static fn(): array => [CardPalette::CYAN, 0.35], false, 8.0, false);
        }

        $discR = 124 * $k;
        $ringStops = [[0.0, CardPalette::CYAN, 1.0], [0.5, CardPalette::CYAN, 0.25], [1.0, CardPalette::PINK, 1.0]];
        $ringAt = static function (float $x, float $y) use ($cx, $cy, $discR, $ringStops): array {
            [$rgb, $a] = self::stop($ringStops, (($x - $cx + $discR) + ($y - $cy + $discR)) / (4 * $discR));
            return CardCanvas::mix($rgb, [5, 10, 16], $a);
        };
        $logo = CardConfig::coinLogo($base);
        $mono = self::monogram($base);
        $monoSize = $c->fit($mono, 58.0, 150 * $k, 'display', 0.0, 20.0);
        $monoTop = $cy - $c->capHeight($monoSize, 'display', $mono) / 2;

        $c->glow([$cx - 160 * $k, $cy - 160 * $k, 320 * $k, 320 * $k], 5.0, static function (CardCanvas $g) use ($cx, $cy, $k, $at, $discR, $ringAt, $logo, $mono, $monoSize, $monoTop): void {
            $g->gradientPolyline(CardCanvas::arcPath($cx, $cy, 150 * $k, 225, 270, 24), 2.5 * $k, static fn(): array => CardPalette::CYAN);
            $g->gradientPolyline(CardCanvas::arcPath($cx, $cy, 150 * $k, 45, 90, 24), 2.5 * $k, static fn(): array => CardPalette::PINK);
            $g->gradientPolyline(CardCanvas::arcPath($cx, $cy, $discR, 0, 360, 120), 3.0 * $k, $ringAt, false, 4.0);
            [$nx, $ny] = $at(225, 150);
            $g->disc($nx, $ny, 4 * $k, CardPalette::CYAN);
            [$nx, $ny] = $at(45, 150);
            $g->disc($nx, $ny, 4 * $k, CardPalette::PINK);
            if ($logo === null) {
                $g->write($mono, $cx, $monoTop, $monoSize, CardPalette::WHITE, 'display', 'center');
            }
        }, 1.0);

        $c->radialDisc($cx, $cy, $discR, [14, 26, 38], [5, 10, 16], -0.12 * $discR);
        $c->gradientPolyline(CardCanvas::arcPath($cx, $cy, $discR, 0, 360, 120), 3.0 * $k, $ringAt, false, 4.0);
        $c->gradientPolyline(CardCanvas::arcPath($cx, $cy, 112 * $k, 0, 360, 120), 1.0, static fn(): array => [CardPalette::WHITE, 0.06], false, 6.0, false);
        $c->gradientPolyline(CardCanvas::arcPath($cx, $cy, 150 * $k, 225, 270, 24), 2.5 * $k, static fn(): array => CardPalette::CYAN);
        $c->gradientPolyline(CardCanvas::arcPath($cx, $cy, 150 * $k, 45, 90, 24), 2.5 * $k, static fn(): array => CardPalette::PINK);
        [$nx, $ny] = $at(225, 150);
        $c->disc($nx, $ny, 4 * $k, CardPalette::CYAN);
        [$nx, $ny] = $at(45, 150);
        $c->disc($nx, $ny, 4 * $k, CardPalette::PINK);

        if ($logo !== null && $c->image($logo, $cx - 62 * $k, $cy - 62 * $k, 124 * $k, 124 * $k)) {
            return;
        }
        $c->write($mono, $cx, $monoTop, $monoSize, CardPalette::WHITE, 'display', 'center');
    }

    private static function monogram(string $base): string
    {
        $base = strtoupper(trim($base));
        $clean = preg_replace('/^(1000000|100000|10000|1000|100)(?=[A-Z])/', '', $base) ?? $base;
        return $clean !== '' ? $clean : ($base !== '' ? $base : '?');
    }

    public static function chart(CardCanvas $c, float $cx, float $cy, bool $rising, array $tones, array $counter, float $k = 0.9): void
    {
        $x0 = $cx - 190 * $k;
        $y0 = $cy - 170 * $k;
        $map = static fn(float $x, float $y): array => [$x0 + $x * $k, $y0 + ($rising ? 340 - $y : $y) * $k];

        foreach ([40, 110, 180, 250, 320] as $gy) {
            for ($gx = 0; $gx < 380; $gx += 9) {
                [$lx, $ly] = $map($gx, $gy);
                $c->rect($lx, $ly - 0.5, 3 * $k, 1.0, CardPalette::LINE);
            }
        }

        $drawCandles = static function (CardCanvas $g) use ($map, $k, $rising, $tones, $counter): void {
            foreach (self::CANDLES as [$x, $top, $bottom, $bodyY, $bodyH, $kind]) {
                $rgb = match ($kind) {
                    'p' => $tones[0],
                    'q' => $tones[1],
                    default => $counter,
                };
                [$wx, $wy1] = $map($x, $top);
                [, $wy2] = $map($x, $bottom);
                $g->rect($wx - $k, min($wy1, $wy2), 2 * $k, abs($wy2 - $wy1), $rgb);
                [$bx, $by1] = $map($x - 8, $bodyY);
                [, $by2] = $map($x - 8, $bodyY + $bodyH);
                $g->roundRect($bx, min($by1, $by2), 16 * $k, abs($by2 - $by1), 2 * $k, $rgb);
            }
        };
        $box = [$x0, $y0, 380 * $k, 340 * $k];
        $c->glow($box, 3.0, $drawCandles, 0.9);
        $drawCandles($c);

        $shaft = array_map(static fn(array $p): array => $map($p[0], $p[1]), [[6, 30], [118, 112], [176, 86], [300, 232]]);
        $head = array_map(static fn(array $p): array => $map($p[0], $p[1]), [[336, 274], [266, 256], [322, 204]]);
        $arrowStops = [[0.0, $tones[0], 0.15], [0.35, $tones[0], 1.0], [1.0, $tones[1], 1.0]];
        $c->glow($box, 9.0, static function (CardCanvas $g) use ($shaft, $head, $k, $tones): void {
            $g->polyline($shaft, 14 * $k, $tones[0]);
            $g->filledPolygon($head, $tones[1]);
        }, 1.0, $tones[0]);
        $c->gradientPolyline($shaft, 14 * $k, static function (float $x, float $y, float $t) use ($arrowStops): array {
            [$rgb, $a] = self::stop($arrowStops, $t);
            return CardCanvas::mix($rgb, self::bgAt($x, $y), $a);
        }, false, 3.0);
        $c->filledPolygon($head, $tones[1]);
    }

    public static function statsPanel(CardCanvas $c, float $y, float $h, array $columns): void
    {
        $x = self::PAD;
        $w = self::W - 2 * self::PAD;
        $r = 22.0;
        $c->glow([$x, $y + 24, $w, $h], 30.0, static function (CardCanvas $g) use ($x, $y, $w, $h, $r): void {
            $g->roundRect($x, $y + 24, $w, $h, $r, [0, 0, 0]);
        }, 0.45);
        $c->shadeRoundRect($x, $y, $w, $h, $r, static function (float $px, float $py) use ($y, $h): array {
            $a = 0.055 + (0.018 - 0.055) * (($py - $y) / $h);
            return CardCanvas::mix(CardPalette::WHITE, self::bgAt($px, $py), $a);
        });
        $c->strokeRoundRect($x + 0.5, $y + 0.5, $w - 1, $h - 1, $r, 1.0, CardCanvas::mix(CardPalette::MUTED, self::bgAt($x + $w / 2, $y + $h), 0.18));
        $c->rect($x + $r, $y + 1, $w - 2 * $r, 1.0, CardPalette::WHITE, 0.07);

        $n = count($columns);
        $colW = $w / max(1, $n);
        foreach (array_values($columns) as $i => [$label, $value, $accent, $icon, $valueRgb]) {
            $cx = $x + $colW * ($i + 0.5);
            if ($i > 0) {
                $c->rect($x + $colW * $i, $y + 1, 1.0, $h - 2, CardPalette::MUTED, 0.14);
            }
            $c->glow([$cx - 36, $y, 72, 3], 6.0, static function (CardCanvas $g) use ($cx, $y, $accent): void {
                $g->rect($cx - 36, $y, 72, 3, $accent);
            }, 1.0, $accent);
            $c->roundRect($cx - 36, $y - 1, 72, 4, 1.5, $accent);

            $labelSize = 14.0;
            $tracking = 0.28;
            $iconSize = 16.0;
            $labelW = $c->measure($label, $labelSize, 'meta', $tracking);
            $rowW = $iconSize + 9.0 + $labelW;
            $labelCap = $c->capHeight($labelSize, 'meta', $label);
            $valueSize = $c->fit($value, 44.0, $colW - 64, 'display', 0.0, 22.0);
            $valueCap = $c->capHeight($valueSize, 'display', $value);
            $top = $y + ($h - ($labelCap + 20.0 + $valueCap)) / 2;

            $c->icon($icon, $cx - $rowW / 2, $top + $labelCap / 2 - $iconSize / 2, $iconSize, $accent, 0.10);
            $c->write($label, $cx - $rowW / 2 + $iconSize + 9.0, $top, $labelSize, CardPalette::MUTED, 'meta', 'left', $tracking);
            $valueTop = $top + $labelCap + 20.0;
            if ($valueRgb !== CardPalette::WHITE) {
                $vw = $c->measure($value, $valueSize);
                $c->glow([$cx - $vw / 2, $valueTop, $vw, $valueCap], 13.0, static function (CardCanvas $g) use ($value, $cx, $valueTop, $valueSize, $valueRgb): void {
                    $g->write($value, $cx, $valueTop, $valueSize, $valueRgb, 'display', 'center');
                }, 0.5, $valueRgb);
            }
            $c->write($value, $cx, $valueTop, $valueSize, $valueRgb, 'display', 'center');
        }
    }

    public static function footer(CardCanvas $c, string $time): void
    {
        $cy = self::H - 58.0;
        $size = 13.0;
        $items = [[CardConfig::brand(), CardPalette::WHITE, $size, 'meta', 0.22]];
        $width = self::row($c, self::PAD, $cy, $items, 'left', 14.0);
        $x = self::PAD + $width + 14.0;
        $parts = array_values(array_filter(array_map('trim', explode('•', CardConfig::tagline())), static fn(string $p): bool => $p !== ''));
        if (!empty($parts)) {
            $c->rect($x, $cy - 7.5, 1.0, 15.0, CardPalette::DIVIDER);
            $tag = [];
            foreach ($parts as $i => $part) {
                if ($i > 0) {
                    $tag[] = ['•', CardPalette::PINK, 0, '', 0];
                }
                $tag[] = [$part, CardPalette::MUTED, $size, 'meta', 0.22];
            }
            self::row($c, $x + 15.0, $cy, $tag, 'left', 14.0);
        }

        $time = trim($time);
        if ($time !== '') {
            $tw = self::row($c, self::W - self::PAD, $cy, [[$time, CardPalette::MUTED, $size, 'meta', 0.14]], 'right');
            $c->icon('clock', self::W - self::PAD - $tw - 10.0 - 15.0, $cy - 7.5, 15.0, CardPalette::CYAN, 0.1);
        }
    }

    public static function pair(string $symbol): string
    {
        $symbol = strtoupper(trim($symbol));
        foreach (['-', '_'] as $sep) {
            $symbol = str_replace($sep, '/', $symbol);
        }
        if (!str_contains($symbol, '/')) {
            foreach (['USDT', 'USDC', 'BUSD', 'FDUSD'] as $q) {
                if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                    return substr($symbol, 0, -strlen($q)) . '/' . $q;
                }
            }
        }
        return $symbol;
    }

    public static function baseAsset(string $pair): string
    {
        $parts = explode('/', $pair);
        return $parts[0] !== '' ? $parts[0] : $pair;
    }
}

final class SignalCard
{
    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
            $symbol = NeonChrome::pair((string) ($d['symbol'] ?? ''));
            $leverage = strtoupper(trim((string) ($d['leverage'] ?? '')));
            $entry = CardFormat::orNA($d['entry'] ?? null);

            $c = NeonChrome::begin($isLong ? CardPalette::CYAN : CardPalette::RED, $isLong ? 0.12 : 0.16);

            $pill = [[$symbol, CardPalette::WHITE, 16.0, 'label', 0.12]];
            if ($leverage !== '') {
                $pill[] = [$leverage, CardPalette::PINK, 16.0, 'display', 0.04];
            }
            NeonChrome::header($c, $pill);

            $cx = NeonChrome::W / 2;
            $top = 172.0;
            $titleSize = $c->fit($symbol, 84.0, 600.0, 'display', 0.0, 40.0);
            $titleBottom = NeonChrome::title($c, $cx, $top, $symbol, $titleSize, CardPalette::WHITE, CardPalette::WHITE, 0.12, 24.0);

            $badgeBottom = $isLong
                ? NeonChrome::badge($c, $cx, $titleBottom + 32.0, 'LONG', 'up', CardPalette::CYAN, CardPalette::TEAL, CardPalette::INK)
                : NeonChrome::badge($c, $cx, $titleBottom + 32.0, 'SHORT', 'down', CardPalette::RED, CardPalette::PINK, CardPalette::WHITE);

            $captionBottom = NeonChrome::caption($c, $cx, $badgeBottom + 40.0, 'ENTRY PRICE');

            $priceSize = $c->fit($entry, 132.0, 620.0, 'display', 0.0, 56.0);
            $bottom = NeonChrome::title($c, $cx, $captionBottom + 26.0, $entry, $priceSize, CardPalette::WHITE, CardPalette::WHITE, 0.14, 30.0);

            $mid = ($top + $bottom) / 2;
            NeonChrome::emblem($c, 272.0, $mid, NeonChrome::baseAsset($symbol));
            NeonChrome::chart(
                $c,
                NeonChrome::W - 272.0,
                $mid,
                $isLong,
                $isLong ? [CardPalette::CYAN, CardPalette::TEAL] : [CardPalette::PINK, CardPalette::RED],
                $isLong ? CardPalette::PINK : CardPalette::CYAN
            );

            NeonChrome::statsPanel($c, 612.0, 144.0, [
                ['STOP LOSS', CardFormat::orNA($d['sl'] ?? null), CardPalette::RED, 'stop', CardPalette::RED],
                ['TARGET 1', CardFormat::orNA($d['tp1'] ?? null), CardPalette::CYAN, 'target', CardPalette::CYAN],
                ['TARGET 2', CardFormat::orNA($d['tp2'] ?? null), CardPalette::CYAN, 'target', CardPalette::CYAN],
            ]);

            NeonChrome::footer($c, (string) ($d['time'] ?? ''));

            return NeonChrome::finish($c);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'signal card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}

final class ResultCard
{
    private const STATUS = [
        'tp1' => ['PROFIT SHOT', 'TARGET 1 HIT'],
        'tp2' => ['PROFIT SHOT', 'TARGET 2 HIT'],
        'tp3' => ['PROFIT SHOT', 'TARGET 3 HIT'],
        'tp4' => ['PROFIT SHOT', 'ALL TARGETS HIT'],
        'trail' => ['PROFIT SHOT', 'PROFIT LOCKED'],
        'be' => ['BREAK EVEN', 'RISK FREE EXIT'],
        'sl' => ['STOP LOSS', 'TRADE CLOSED'],
        'timeout' => ['TIME LIMIT', 'TRADE CLOSED'],
    ];

    public static function render(array $d, string $format = 'post'): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
            $headline = trim((string) ($d['headline'] ?? ''));
            $move = trim((string) ($d['move'] ?? ''));
            $pnl = CardFormat::number($headline) ?? 0.0;
            $tone = $pnl > 0.004 ? 'profit' : ($pnl < -0.004 ? 'loss' : 'flat');
            if ($tone === 'flat' && $kind === 'sl') {
                $tone = 'loss';
            }
            [$badgeText, $status] = self::STATUS[$kind] ?? ['RESULT', 'TRADE CLOSED'];
            if ($tone === 'loss' && $badgeText === 'PROFIT SHOT') {
                $badgeText = 'RESULT';
            }

            [$accent, $accent2, $ink, $valueRgb, $icon, $caption] = match ($tone) {
                'profit' => [CardPalette::CYAN, CardPalette::TEAL, CardPalette::INK, CardPalette::CYAN, 'check', 'TOTAL PROFIT'],
                'loss' => [CardPalette::RED, CardPalette::PINK, CardPalette::WHITE, CardPalette::RED, 'cross', 'TOTAL LOSS'],
                default => [CardPalette::STEEL, CardPalette::STEEL_2, CardPalette::WHITE, CardPalette::WHITE, 'dash', 'NET RESULT'],
            };
            if ($kind === 'timeout') {
                $icon = 'clock';
            }

            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
            $symbol = NeonChrome::pair((string) ($d['symbol'] ?? ''));
            $leverage = strtoupper(trim((string) ($d['leverage'] ?? '')));

            $c = NeonChrome::begin($accent, $tone === 'flat' ? 0.06 : 0.15);

            $pill = [
                [$symbol, CardPalette::WHITE, 16.0, 'label', 0.12],
                [$isLong ? 'LONG' : 'SHORT', $isLong ? CardPalette::CYAN : CardPalette::RED, 16.0, 'label', 0.12],
            ];
            if ($leverage !== '') {
                $pill[] = [$leverage, CardPalette::PINK, 16.0, 'display', 0.04];
            }
            NeonChrome::header($c, $pill);

            $cx = NeonChrome::W / 2;
            $top = 166.0;
            $titleSize = $c->fit($symbol, 64.0, 560.0, 'display', 0.0, 36.0);
            $titleBottom = NeonChrome::title($c, $cx, $top, $symbol, $titleSize, CardPalette::WHITE, CardPalette::WHITE, 0.12, 20.0);

            $badgeBottom = NeonChrome::badge($c, $cx, $titleBottom + 28.0, $badgeText, $icon, $accent, $accent2, $ink);
            $captionBottom = NeonChrome::caption($c, $cx, $badgeBottom + 36.0, $caption);

            $headline = $headline !== '' ? $headline : '0.00%';
            $pnlSize = $c->fit($headline, 124.0, 620.0, 'display', 0.0, 56.0);
            $pnlBottom = NeonChrome::title($c, $cx, $captionBottom + 24.0, $headline, $pnlSize, $valueRgb, $accent, $tone === 'flat' ? 0.12 : 0.4, 28.0);

            $sub = [['PRICE MOVE', CardPalette::MUTED, 14.0, 'meta', 0.2]];
            if ($move !== '') {
                $sub[] = [$move, CardPalette::WHITE, 14.0, 'meta', 0.1];
            }
            $sub[] = ['•', CardPalette::PINK, 0, '', 0];
            $sub[] = [$status, CardPalette::MUTED, 14.0, 'meta', 0.2];
            NeonChrome::row($c, $cx, $pnlBottom + 34.0, $sub, 'center', 12.0);
            $bottom = $pnlBottom + 40.0;

            $mid = ($top + $bottom) / 2;
            $favorable = CardFormat::number($move) ?? $pnl;
            $rising = $isLong ? $favorable >= 0 : $favorable < 0;
            NeonChrome::emblem($c, 272.0, $mid, NeonChrome::baseAsset($symbol));
            NeonChrome::chart(
                $c,
                NeonChrome::W - 272.0,
                $mid,
                $rising,
                [$accent, $tone === 'loss' ? CardPalette::RED : $accent2],
                match ($tone) {
                    'profit' => CardPalette::PINK,
                    'loss' => CardPalette::CYAN,
                    default => CardPalette::MUTED,
                }
            );

            NeonChrome::statsPanel($c, 612.0, 144.0, [
                ['ENTRY', CardFormat::orNA($d['entry'] ?? null), CardPalette::MUTED, 'entry', CardPalette::WHITE],
                ['EXIT', CardFormat::orNA($d['exit'] ?? null), $accent, $tone === 'loss' ? 'stop' : 'target', $tone === 'flat' ? CardPalette::WHITE : $valueRgb],
                ['DURATION', CardFormat::orNA($d['duration'] ?? null), CardPalette::MUTED, 'clock', CardPalette::WHITE],
            ]);

            NeonChrome::footer($c, (string) ($d['time'] ?? ''));

            return NeonChrome::finish($c);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'result card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}
