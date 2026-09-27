<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;

/**
 * Fits text into a box (choosing 1..N lines), then draws it with optional outline/shadow.
 */
final class TextRenderer
{
    private const REF = 100.0; // reference font size used for measuring
    private const LINE_GAP = 1.22;

    /** @var array<string, array{0: float, 1: float, 2: float}> advance/top/bottom at REF size */
    private array $measureCache = [];

    public function __construct(private readonly Fonts $fonts)
    {
    }

    /**
     * @param float $boxW box width in px
     * @param float $boxH box height in px
     * @param float $scale user size multiplier
     */
    public function layout(string $text, string $style, float $boxW, float $boxH, TextStyle $ts, float $scale = 1.0): array
    {
        $words = explode(' ', $text);
        $candidates = [[$text]];
        $n = count($words);
        if ($ts->maxLines >= 2 && $n >= 2) {
            for ($i = 1; $i < $n; $i++) {
                $candidates[] = [implode(' ', array_slice($words, 0, $i)), implode(' ', array_slice($words, $i))];
            }
        }
        if ($ts->maxLines >= 3 && $n >= 3) {
            for ($i = 1; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidates[] = [
                        implode(' ', array_slice($words, 0, $i)),
                        implode(' ', array_slice($words, $i, $j - $i)),
                        implode(' ', array_slice($words, $j)),
                    ];
                }
            }
        }

        $best = null;
        $bestSize = -1.0;
        $pad = 2 * $ts->strokeRatio; // outline on both sides, in units of font size
        foreach ($candidates as $lines) {
            $m = $this->measureLines($lines, $style);
            $size = min($boxW / ($m['width'] / self::REF + $pad), $boxH / ($m['height'] / self::REF + $pad));
            // Prefer fewer lines unless more lines give a clearly bigger text.
            $score = $size * (1 - 0.1 * (count($lines) - 1));
            if ($score > $bestSize) {
                $bestSize = $score;
                $best = [$lines, $m, $size];
            }
        }
        [$lines, $m, $size] = $best;
        $size *= $scale;
        $k = $size / self::REF;
        return [
            'size' => $size,
            'lines' => array_map(fn ($line) => [
                'runs' => array_map(fn ($r) => [$r[0], $r[1], $r[2] * $k], $line['runs']),
                'width' => $line['width'] * $k,
            ], $m['lines']),
            'gap' => $m['gap'] * $k,
            'top' => $m['top'] * $k,
            'height' => $m['height'] * $k,
            'width' => $m['width'] * $k,
        ];
    }

    private function measureLines(array $lines, string $style): array
    {
        $out = [];
        $gap = self::REF * self::LINE_GAP;
        $top = 0.0;
        $bottom = 0.0;
        $width = 0.0;
        foreach ($lines as $i => $line) {
            $runs = [];
            $lineW = 0.0;
            $lineTop = 0.0;
            $lineBottom = 0.0;
            foreach ($this->runs($line, $style) as [$font, $str]) {
                [$adv, $t, $b] = $this->measure($font, $str);
                $runs[] = [$font, $str, $adv];
                $lineW += $adv;
                $lineTop = min($lineTop, $t);
                $lineBottom = max($lineBottom, $b);
            }
            if ($i === 0) {
                $top = $lineTop;
            }
            $bottom = $i * $gap + $lineBottom;
            $width = max($width, $lineW);
            $out[] = ['runs' => $runs, 'width' => $lineW];
        }
        return ['lines' => $out, 'gap' => $gap, 'top' => $top, 'height' => max(1.0, $bottom - $top), 'width' => max(1.0, $width)];
    }

    /**
     * Splits a logical line into visual-order runs that share a font.
     * @return array<array{0: string, 1: string}>
     */
    private function runs(string $line, string $style): array
    {
        $runs = [];
        $curFont = null;
        $buf = [];
        foreach (Shaper::visual($line) as $cp) {
            // Spaces and neutral punctuation stay in the current run to avoid needless splits.
            $font = ($cp === 0x20 && $curFont !== null) ? $curFont : $this->fonts->fontFor($style, $cp);
            if ($font !== $curFont && $buf) {
                $runs[] = [$curFont, Shaper::toUtf8($buf)];
                $buf = [];
            }
            $curFont = $font;
            $buf[] = $cp;
        }
        if ($buf) {
            $runs[] = [$curFont, Shaper::toUtf8($buf)];
        }
        return $runs;
    }

    /** @return array{0: float, 1: float, 2: float} advance, ink top (negative = above baseline), ink bottom */
    private function measure(string $font, string $str): array
    {
        $key = $font . "\0" . $str;
        if (!isset($this->measureCache[$key])) {
            $b = imagettfbbox(self::REF, 0, $font, $str);
            $this->measureCache[$key] = [
                (float) ($b[2] - $b[0]),
                (float) min($b[5], $b[7]),
                (float) max($b[1], $b[3]),
            ];
        }
        return $this->measureCache[$key];
    }

    /**
     * Draws a layout centered on ($cx, $cy).
     * @param ?array $fillOverride rgb used instead of the style fill (for color animations)
     * @param bool $strokeOnly draw only the outline/shadow; $fillOnly draw only the fill
     */
    public function draw(GdImage $im, array $layout, float $cx, float $cy, TextStyle $ts, ?array $fillOverride = null, string $part = 'all'): void
    {
        $size = $layout['size'];
        $baseline0 = $cy - $layout['height'] / 2 - $layout['top'];
        $stroke = $ts->stroke !== null ? $ts->strokeRatio * $size : 0.0;

        $paint = function (int $color, float $ox, float $oy) use ($im, $layout, $cx, $baseline0, $size): void {
            foreach ($layout['lines'] as $i => $line) {
                $x = $cx - $line['width'] / 2 + $ox;
                $y = $baseline0 + $i * $layout['gap'] + $oy;
                foreach ($line['runs'] as [$font, $str, $adv]) {
                    imagettftext($im, $size, 0, (int) round($x), (int) round($y), $color, $font, $str);
                    $x += $adv;
                }
            }
        };

        if ($part !== 'fill') {
            if ($ts->shadow !== null) {
                $off = max(1.0, $size * 0.06);
                $shadowColor = Gfx::col($im, $ts->shadow, 70);
                $paint($shadowColor, $off * 0.4, $off);
            }
            if ($stroke >= 0.5) {
                $color = Gfx::col($im, $ts->stroke);
                $steps = $stroke > 6 ? 24 : 16;
                for ($i = 0; $i < $steps; $i++) {
                    $a = 2 * M_PI * $i / $steps;
                    $paint($color, $stroke * cos($a), $stroke * sin($a));
                }
                $paint($color, 0, 0);
            }
        }
        if ($part !== 'stroke') {
            $paint(Gfx::col($im, $fillOverride ?? $ts->fill), 0, 0);
        }
    }
}
