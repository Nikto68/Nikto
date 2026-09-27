<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;
use RuntimeException;

/**
 * Template built on a 3D emoji artwork (assets/art, Microsoft Fluent Emoji, MIT).
 *
 * Layouts:
 *   label  – the artwork fills the emoji, the text is written on the object (box in artwork coords)
 *   sign   – the character peeks over a sign it holds with its paws, the text is on the sign
 *   banner – the object sits above a ribbon/pill banner that carries the text
 */
final class ArtTemplate extends Template
{
    public const LABEL = 'label';
    public const SIGN = 'sign';
    public const BANNER = 'banner';

    /** @var array<string, GdImage> */
    private static array $art = [];
    /** @var array<string, array> */
    private static array $avg = [];

    /**
     * @param array $box [cx, cy, w, h, angle] in artwork coordinates (0..1), label layout only
     */
    public function __construct(
        string $id,
        string $title,
        string $category,
        string $emoji,
        string $effect,
        public readonly string $file,
        public readonly string $layout,
        private readonly array $box,
        private readonly string $dir,
        private readonly string $cacheDir,
    ) {
        parent::__construct($id, $title, $category, $emoji, $effect, 1);
    }

    public function tintable(): bool
    {
        return true;
    }

    public function layers(int $S, array $c1, array $c2, bool $tint = false): array
    {
        $art = $this->art($tint ? $c1 : null);
        $base = Gfx::canvas($S);
        $back = null;
        $k = $S / 512;
        switch ($this->layout) {
            case self::SIGN:
                $back = Gfx::canvas($S);
                $this->place($back, $art, 0.14, 0.0, 0.72);
                $this->board($base, $k, $c1, $c2);
                $paw = $tint ? Gfx::shade($c1, 0.3) : self::avgColor($art, $this->file);
                foreach ([0.285, 0.715] as $x) {
                    $shape = static fn (GdImage $im, int $c, float $ox = 0, float $oy = 0) => imagefilledellipse(
                        $im, (int) round($x * $S + $ox), (int) round(0.585 * $S + $oy), (int) round(0.15 * $S), (int) round(0.11 * $S), $c,
                    );
                    Gfx::outline($base, $shape, Gfx::col($base, Gfx::shade($paw, -0.5)), 6 * $k, 12);
                    $shape($base, Gfx::col($base, $paw));
                    Gfx::gloss($base, $x * $S - 0.02 * $S, 0.57 * $S, 0.06 * $S, 0.035 * $S, 190);
                }
                break;
            case self::BANNER:
                $back = Gfx::canvas($S);
                $this->place($back, $art, 0.1, 0.0, 0.8);
                $shape = static fn (GdImage $im, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($im, 16 * $k, 352 * $k, 496 * $k, 480 * $k, 64 * $k, $c, $ox, $oy);
                Gfx::outline($base, $shape, Gfx::col($base, $c2), 9 * $k);
                $layer = Gfx::canvas($S);
                $shape($layer, Gfx::col($layer, $c1));
                Gfx::shadeV($layer, 352 * $k, 480 * $k, 160, 100);
                Gfx::gloss($layer, 256 * $k, 364 * $k, 420 * $k, 38 * $k, 176);
                imagecopy($base, $layer, 0, 0, 0, 0, $S, $S);
                break;
            default:
                $this->place($base, $art, 0.03, 0.03, 0.94);
        }
        return ['base' => $base, 'overlay' => null, 'back' => $back];
    }

    /** The sign held by the character. */
    private function board(GdImage $im, float $k, array $c1, array $c2): void
    {
        $S = imagesx($im);
        $outer = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 26 * $k, 288 * $k, 486 * $k, 490 * $k, 38 * $k, $c, $ox, $oy);
        Gfx::outline($im, $outer, Gfx::col($im, $c2), 9 * $k);
        $layer = Gfx::canvas($S);
        $outer($layer, Gfx::col($layer, Gfx::shade($c1, -0.35)));
        Gfx::roundedRect($layer, 38 * $k, 300 * $k, 474 * $k, 478 * $k, 28 * $k, Gfx::col($layer, $c1));
        Gfx::shadeV($layer, 288 * $k, 490 * $k, 150, 104);
        imagecopy($im, $layer, 0, 0, 0, 0, $S, $S);
    }

    private function place(GdImage $dst, GdImage $art, float $x, float $y, float $size): void
    {
        $S = imagesx($dst);
        imagecopyresampled($dst, $art, (int) round($x * $S), (int) round($y * $S), 0, 0, (int) round($size * $S), (int) round($size * $S), imagesx($art), imagesy($art));
    }

    public function box(): array
    {
        return match ($this->layout) {
            self::SIGN => [0.5, 0.762, 0.8, 0.26, 0.0],
            self::BANNER => [0.5, 0.812, 0.84, 0.19, 0.0],
            default => [
                0.03 + 0.94 * $this->box[0],
                0.03 + 0.94 * $this->box[1],
                0.94 * $this->box[2],
                0.94 * $this->box[3],
                (float) ($this->box[4] ?? 0),
            ],
        };
    }

    public function textStyle(array $c1, array $c2): TextStyle
    {
        if ($this->layout === self::LABEL) {
            return new TextStyle($c2, $c1, 0.14, [0, 0, 0], 2);
        }
        return new TextStyle($c2, null, 0.0, Gfx::shade($c1, -0.6), 2);
    }

    /** Artwork, optionally recolored with the user's color (cached in memory and on disk). */
    private function art(?array $tint): GdImage
    {
        $key = $this->file . ($tint ? '|' . Gfx::hex($tint) : '');
        if (isset(self::$art[$key])) {
            return self::$art[$key];
        }
        $src = null;
        if ($tint) {
            $cached = $this->cacheDir . '/art-' . $this->file . '-' . substr(Gfx::hex($tint), 1) . '.png';
            $src = is_file($cached) ? @imagecreatefrompng($cached) : null;
        }
        if (!$src) {
            $src = @imagecreatefrompng($this->dir . '/' . $this->file . '.png');
            if (!$src) {
                throw new RuntimeException('art missing: ' . $this->file);
            }
            imagesavealpha($src, true);
            if ($tint) {
                $src = CustomTemplate::recolor($src, 'tint', $tint, [255, 255, 255]);
                @imagepng($src, $cached, 6);
            }
        }
        imagesavealpha($src, true);
        if (count(self::$art) > 40) {
            self::$art = [];
        }
        return self::$art[$key] = $src;
    }

    /** Average color of the visible pixels (used for the paws holding the sign). */
    private static function avgColor(GdImage $im, string $key): array
    {
        if (isset(self::$avg[$key])) {
            return self::$avg[$key];
        }
        $r = $g = $b = $n = 0;
        $w = imagesx($im);
        $h = imagesy($im);
        for ($y = (int) ($h * 0.35); $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                $p = imagecolorat($im, $x, $y);
                if ((($p >> 24) & 0x7F) < 20) {
                    $r += ($p >> 16) & 255;
                    $g += ($p >> 8) & 255;
                    $b += $p & 255;
                    $n++;
                }
            }
        }
        $c = $n ? [intdiv($r, $n), intdiv($g, $n), intdiv($b, $n)] : [230, 180, 90];
        // Paws look better slightly lighter/more saturated than the plain average.
        return self::$avg[$key] = Gfx::shade($c, 0.08);
    }
}
