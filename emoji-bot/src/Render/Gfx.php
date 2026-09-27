<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;

/**
 * GD drawing helpers. Shapes are drawn on a large canvas and downsampled later,
 * which gives smooth anti-aliased edges (GD does not anti-alias filled shapes).
 */
final class Gfx
{
    public static function canvas(int $w, ?int $h = null): GdImage
    {
        $im = imagecreatetruecolor($w, $h ?? $w);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefilledrectangle($im, 0, 0, $w - 1, ($h ?? $w) - 1, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        return $im;
    }

    public static function copyOf(GdImage $src): GdImage
    {
        $im = self::canvas(imagesx($src), imagesy($src));
        imagecopy($im, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        return $im;
    }

    /** "#rrggbb" => [r, g, b] */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
    }

    /** Allocates a color. $alpha is 0 (opaque) .. 127 (transparent). */
    public static function col(GdImage $im, array $c, int $alpha = 0): int
    {
        return imagecolorallocatealpha($im, (int) $c[0], (int) $c[1], (int) $c[2], max(0, min(127, $alpha)));
    }

    public static function mix(array $a, array $b, float $t): array
    {
        $t = max(0.0, min(1.0, $t));
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    /** $amount < 0 darkens toward black, > 0 lightens toward white. */
    public static function shade(array $c, float $amount): array
    {
        return $amount < 0 ? self::mix($c, [0, 0, 0], -$amount) : self::mix($c, [255, 255, 255], $amount);
    }

    public static function luminance(array $c): float
    {
        return (0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]) / 255;
    }

    public static function hsv(float $h, float $s, float $v): array
    {
        $h = fmod($h, 360.0);
        if ($h < 0) {
            $h += 360;
        }
        $c = $v * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $v - $c;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };
        return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
    }

    /** @param array<array{0: float, 1: float}> $pts */
    public static function polygon(GdImage $im, array $pts, int $color, float $ox = 0, float $oy = 0): void
    {
        $flat = [];
        foreach ($pts as [$x, $y]) {
            $flat[] = (int) round($x + $ox);
            $flat[] = (int) round($y + $oy);
        }
        if (count($flat) >= 6) {
            imagefilledpolygon($im, $flat, $color);
        }
    }

    public static function circle(GdImage $im, float $cx, float $cy, float $r, int $color): void
    {
        $d = (int) round($r * 2);
        imagefilledellipse($im, (int) round($cx), (int) round($cy), $d, $d, $color);
    }

    public static function roundedRect(GdImage $im, float $x1, float $y1, float $x2, float $y2, float $r, int $color, float $ox = 0, float $oy = 0): void
    {
        self::polygon($im, self::roundedRectPoints($x1, $y1, $x2, $y2, $r), $color, $ox, $oy);
    }

    public static function roundedRectPoints(float $x1, float $y1, float $x2, float $y2, float $r): array
    {
        $r = min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2);
        return self::roundedPolygon([[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2]], $r, 10);
    }

    /** Replaces every corner of a polygon with a circular arc of radius $r. */
    public static function roundedPolygon(array $pts, float $r, int $segments = 8): array
    {
        $n = count($pts);
        if ($r <= 0 || $n < 3) {
            return $pts;
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $p0 = $pts[($i - 1 + $n) % $n];
            $p1 = $pts[$i];
            $p2 = $pts[($i + 1) % $n];
            $l1 = hypot($p0[0] - $p1[0], $p0[1] - $p1[1]);
            $l2 = hypot($p2[0] - $p1[0], $p2[1] - $p1[1]);
            if ($l1 < 1e-6 || $l2 < 1e-6) {
                continue;
            }
            $v1 = [($p0[0] - $p1[0]) / $l1, ($p0[1] - $p1[1]) / $l1];
            $v2 = [($p2[0] - $p1[0]) / $l2, ($p2[1] - $p1[1]) / $l2];
            $angle = acos(max(-1.0, min(1.0, $v1[0] * $v2[0] + $v1[1] * $v2[1])));
            if ($angle < 1e-3 || abs($angle - M_PI) < 1e-3) {
                $out[] = $p1;
                continue;
            }
            $tanHalf = tan($angle / 2);
            $d = min($r / $tanHalf, $l1 / 2, $l2 / 2);
            $rr = $d * $tanHalf;
            $bis = [$v1[0] + $v2[0], $v1[1] + $v2[1]];
            $bl = hypot($bis[0], $bis[1]);
            $bis = [$bis[0] / $bl, $bis[1] / $bl];
            $dist = $rr / sin($angle / 2);
            $c = [$p1[0] + $bis[0] * $dist, $p1[1] + $bis[1] * $dist];
            $t1 = [$p1[0] + $v1[0] * $d, $p1[1] + $v1[1] * $d];
            $t2 = [$p1[0] + $v2[0] * $d, $p1[1] + $v2[1] * $d];
            $a1 = atan2($t1[1] - $c[1], $t1[0] - $c[0]);
            $a2 = atan2($t2[1] - $c[1], $t2[0] - $c[0]);
            $da = $a2 - $a1;
            while ($da > M_PI) {
                $da -= 2 * M_PI;
            }
            while ($da < -M_PI) {
                $da += 2 * M_PI;
            }
            for ($k = 0; $k <= $segments; $k++) {
                $a = $a1 + $da * $k / $segments;
                $out[] = [$c[0] + $rr * cos($a), $c[1] + $rr * sin($a)];
            }
        }
        return $out;
    }

    public static function regularPolygon(float $cx, float $cy, float $r, int $n, float $rotDeg = -90): array
    {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad($rotDeg + 360 * $i / $n);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        return $pts;
    }

    public static function star(float $cx, float $cy, float $outer, float $inner, int $points, float $rotDeg = -90): array
    {
        $pts = [];
        for ($i = 0; $i < $points * 2; $i++) {
            $r = $i % 2 === 0 ? $outer : $inner;
            $a = deg2rad($rotDeg + 180 * $i / $points);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        return $pts;
    }

    /** Classic parametric heart fitted into the given box. */
    public static function heart(float $x1, float $y1, float $x2, float $y2, int $n = 120): array
    {
        $raw = [];
        for ($i = 0; $i < $n; $i++) {
            $t = 2 * M_PI * $i / $n;
            $raw[] = [16 * sin($t) ** 3, -(13 * cos($t) - 5 * cos(2 * $t) - 2 * cos(3 * $t) - cos(4 * $t))];
        }
        return self::fit($raw, $x1, $y1, $x2, $y2);
    }

    /** Scales points so their bounding box fills the target box. */
    public static function fit(array $pts, float $x1, float $y1, float $x2, float $y2): array
    {
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        $minX = min($xs);
        $minY = min($ys);
        $sx = ($x2 - $x1) / max(1e-6, max($xs) - $minX);
        $sy = ($y2 - $y1) / max(1e-6, max($ys) - $minY);
        return array_map(fn ($p) => [$x1 + ($p[0] - $minX) * $sx, $y1 + ($p[1] - $minY) * $sy], $pts);
    }

    public static function bezier(array $p0, array $c, array $p1, int $n = 16): array
    {
        $pts = [];
        for ($i = 0; $i <= $n; $i++) {
            $t = $i / $n;
            $u = 1 - $t;
            $pts[] = [$u * $u * $p0[0] + 2 * $u * $t * $c[0] + $t * $t * $p1[0], $u * $u * $p0[1] + 2 * $u * $t * $c[1] + $t * $t * $p1[1]];
        }
        return $pts;
    }

    public static function scalePoints(array $pts, float $k): array
    {
        return array_map(fn ($p) => [$p[0] * $k, $p[1] * $k], $pts);
    }

    /**
     * Draws $shape expanded by $width (Minkowski sum with a disc, approximated by stamping).
     * @param callable(GdImage, int, float, float): void $shape
     */
    public static function outline(GdImage $im, callable $shape, int $color, float $width, int $steps = 24): void
    {
        if ($width <= 0) {
            return;
        }
        $shape($im, $color, 0.0, 0.0);
        for ($i = 0; $i < $steps; $i++) {
            $a = 2 * M_PI * $i / $steps;
            $shape($im, $color, $width * cos($a), $width * sin($a));
        }
    }

    /**
     * Vertical light/dark shading that only touches existing (non-transparent) pixels.
     * gray 128 = no change, >128 lightens, <128 darkens (GD overlay layer effect).
     */
    public static function shadeV(GdImage $im, float $y0, float $y1, int $grayTop, int $grayBottom): void
    {
        imagelayereffect($im, IMG_EFFECT_OVERLAY);
        $h = imagesy($im);
        $w = imagesx($im);
        for ($y = 0; $y < $h; $y++) {
            $t = $y1 > $y0 ? ($y - $y0) / ($y1 - $y0) : 0;
            $g = (int) round($grayTop + ($grayBottom - $grayTop) * max(0, min(1, $t)));
            if ($g === 128) {
                continue;
            }
            imageline($im, 0, $y, $w - 1, $y, imagecolorallocatealpha($im, $g, $g, $g, 0));
        }
        imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
    }

    /** Lightens existing pixels inside an ellipse (glossy highlight). */
    public static function gloss(GdImage $im, float $cx, float $cy, float $w, float $h, int $gray = 176): void
    {
        imagelayereffect($im, IMG_EFFECT_OVERLAY);
        imagefilledellipse($im, (int) $cx, (int) $cy, (int) $w, (int) $h, imagecolorallocatealpha($im, $gray, $gray, $gray, 0));
        imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
    }

    /** Lightens existing pixels under a polygon. */
    public static function overlayPolygon(GdImage $im, array $pts, int $gray): void
    {
        imagelayereffect($im, IMG_EFFECT_OVERLAY);
        self::polygon($im, $pts, imagecolorallocatealpha($im, $gray, $gray, $gray, 0));
        imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
    }

    /** Makes pixels fully transparent (punches a hole). */
    public static function punchCircle(GdImage $im, float $cx, float $cy, float $r): void
    {
        imagealphablending($im, false);
        self::circle($im, $cx, $cy, $r, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
    }

    public static function punchPolygon(GdImage $im, array $pts): void
    {
        imagealphablending($im, false);
        self::polygon($im, $pts, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
    }

    /** Alpha-correct resize into a new transparent canvas. */
    public static function resize(GdImage $src, int $w, ?int $h = null): GdImage
    {
        $h ??= $w;
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $w - 1, $h - 1, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagealphablending($dst, true);
        return $dst;
    }

    /** Draws $src onto $dst scaled by ($sx, $sy) around the destination center, shifted by ($dx, $dy). */
    public static function placeScaled(GdImage $dst, GdImage $src, float $sx, float $sy, float $dx = 0, float $dy = 0): void
    {
        $W = imagesx($dst);
        $H = imagesy($dst);
        $w = max(1, (int) round($W * $sx));
        $h = max(1, (int) round($H * $sy));
        $x = (int) round(($W - $w) / 2 + $dx);
        $y = (int) round(($H - $h) / 2 + $dy);
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
    }

    /** Rotates around the center, keeping the canvas size (corners are cropped). */
    public static function rotate(GdImage $src, float $deg): GdImage
    {
        if (abs($deg) < 0.01) {
            return self::copyOf($src);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $rot = imagerotate($src, $deg, imagecolorallocatealpha($src, 0, 0, 0, 127));
        imagesavealpha($rot, true);
        $out = self::canvas($w, $h);
        $rw = imagesx($rot);
        $rh = imagesy($rot);
        imagecopy($out, $rot, 0, 0, (int) round(($rw - $w) / 2), (int) round(($rh - $h) / 2), $w, $h);
        return $out;
    }

    /** Cheap alpha-aware blur: area-average down, bilinear back up. */
    public static function blur(GdImage $src, int $factor): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $small = self::resize($src, max(1, intdiv($w, $factor)), max(1, intdiv($h, $factor)));
        imagealphablending($small, false);
        $up = imagescale($small, $w, $h, IMG_BILINEAR_FIXED);
        if (!$up) {
            return self::resize($small, $w, $h);
        }
        imagesavealpha($up, true);
        imagealphablending($up, true);
        return $up;
    }

    /** Multiplies the opacity of every pixel by roughly $opacity (0..1). */
    public static function fade(GdImage $im, float $opacity): void
    {
        $add = (int) round((1 - max(0, min(1, $opacity))) * 127);
        if ($add > 0) {
            imagefilter($im, IMG_FILTER_COLORIZE, 0, 0, 0, $add);
        }
    }

    public static function png(GdImage $im, int $level = 9): string
    {
        ob_start();
        imagepng($im, null, $level);
        return (string) ob_get_clean();
    }
}
