<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;
use RuntimeException;

/**
 * Template made from an image uploaded by an admin (e.g. character art with a sign to write on).
 *
 * Config keys:
 *   box [cx, cy, w, h] (0..1), angle (deg, clockwise), recolor none|duotone|tint,
 *   text_color c1|c2|fixed, text_fixed #hex, stroke none|c1|c2|fixed, stroke_fixed #hex,
 *   stroke_width 0..0.3, shadow bool, max_lines 1..3, effect, overlay (file name or null)
 */
final class CustomTemplate extends Template
{
    public const RECOLOR = ['none' => 'Original colors', 'duotone' => 'Two-tone (dark → color 1, light → color 2)', 'tint' => 'Tint (keeps shading)'];

    /** @var array<string, GdImage> */
    private static array $imageCache = [];

    public function __construct(
        int $dbId,
        string $title,
        string $category,
        string $emoji,
        int $version,
        public readonly array $config,
        private readonly string $dir,
        private readonly string $cacheDir,
    ) {
        parent::__construct('c' . $dbId, $title, $category, $emoji, (string) ($config['effect'] ?? 'none'), $version);
    }

    public static function defaults(): array
    {
        return [
            'file' => '',
            'overlay' => null,
            'box' => [0.5, 0.5, 0.7, 0.3],
            'angle' => 0,
            'recolor' => 'duotone',
            'text_color' => 'c2',
            'text_fixed' => '#ffffff',
            'stroke' => 'none',
            'stroke_fixed' => '#000000',
            'stroke_width' => 0.08,
            'shadow' => false,
            'max_lines' => 2,
            'effect' => 'none',
        ];
    }

    /** Cleans admin input into a safe config array. */
    public static function sanitize(array $in, array $current): array
    {
        $c = array_merge(self::defaults(), $current);
        if (isset($in['box']) && is_array($in['box']) && count($in['box']) === 4) {
            $b = array_map(fn ($v) => max(0.0, min(1.0, (float) $v)), array_values($in['box']));
            $b[2] = max(0.05, $b[2]);
            $b[3] = max(0.03, $b[3]);
            $c['box'] = array_map(fn ($v) => round($v, 4), $b);
        }
        if (isset($in['angle'])) {
            $c['angle'] = max(-45, min(45, (int) $in['angle']));
        }
        foreach (['recolor' => array_keys(self::RECOLOR), 'text_color' => ['c1', 'c2', 'fixed'], 'stroke' => ['none', 'c1', 'c2', 'fixed'], 'effect' => array_keys(Template::EFFECTS)] as $k => $allowed) {
            if (isset($in[$k]) && in_array($in[$k], $allowed, true)) {
                $c[$k] = $in[$k];
            }
        }
        foreach (['text_fixed', 'stroke_fixed'] as $k) {
            if (isset($in[$k]) && is_string($in[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', $in[$k])) {
                $c[$k] = strtolower($in[$k]);
            }
        }
        if (isset($in['stroke_width'])) {
            $c['stroke_width'] = round(max(0.0, min(0.3, (float) $in['stroke_width'])), 3);
        }
        if (isset($in['shadow'])) {
            $c['shadow'] = (bool) $in['shadow'];
        }
        if (isset($in['max_lines'])) {
            $c['max_lines'] = max(1, min(3, (int) $in['max_lines']));
        }
        return $c;
    }

    public function layers(int $S, array $c1, array $c2, bool $tint = false): array
    {
        $base = $this->loadRecolored((string) $this->config['file'], $c1, $c2, $S);
        $overlay = !empty($this->config['overlay']) ? $this->loadRecolored((string) $this->config['overlay'], $c1, $c2, $S) : null;
        return ['base' => $base, 'overlay' => $overlay];
    }

    public function box(): array
    {
        $b = $this->config['box'] ?? [0.5, 0.5, 0.7, 0.3];
        return [(float) $b[0], (float) $b[1], (float) $b[2], (float) $b[3], (float) ($this->config['angle'] ?? 0)];
    }

    public function textStyle(array $c1, array $c2): TextStyle
    {
        $pick = fn (string $mode, string $fixed) => match ($mode) {
            'c1' => $c1,
            'c2' => $c2,
            default => Gfx::rgb($fixed),
        };
        $fill = $pick((string) $this->config['text_color'], (string) $this->config['text_fixed']);
        $stroke = ($this->config['stroke'] ?? 'none') === 'none' ? null : $pick((string) $this->config['stroke'], (string) $this->config['stroke_fixed']);
        return new TextStyle(
            $fill,
            $stroke,
            $stroke ? (float) $this->config['stroke_width'] : 0.0,
            !empty($this->config['shadow']) ? [0, 0, 0] : null,
            (int) ($this->config['max_lines'] ?? 2),
        );
    }

    private function loadRecolored(string $file, array $c1, array $c2, int $S): GdImage
    {
        $mode = (string) ($this->config['recolor'] ?? 'none');
        $key = $this->cacheKey() . "|$file|$mode|" . ($mode === 'none' ? '' : Gfx::hex($c1) . Gfx::hex($c2));
        if (!isset(self::$imageCache[$key])) {
            $diskKey = $this->cacheDir . '/tpl-' . sha1($key) . '.png';
            $img = is_file($diskKey) ? @imagecreatefrompng($diskKey) : false;
            if (!$img) {
                $src = @imagecreatefrompng($this->dir . '/' . basename($file));
                if (!$src) {
                    throw new RuntimeException("template image missing: $file");
                }
                imagesavealpha($src, true);
                $img = $mode === 'none' ? $src : self::recolor($src, $mode, $c1, $c2);
                if ($mode !== 'none') {
                    @imagepng($img, $diskKey, 6);
                }
            }
            imagesavealpha($img, true);
            if (count(self::$imageCache) > 24) {
                self::$imageCache = [];
            }
            self::$imageCache[$key] = $img;
        }
        return Gfx::resize(self::$imageCache[$key], $S);
    }

    /** Maps luminance to a two-color (duotone) or black→color→white (tint) gradient. */
    public static function recolor(GdImage $src, string $mode, array $c1, array $c2): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $lumOf = static fn (int $p) => ((($p >> 16) & 255) * 54 + (($p >> 8) & 255) * 183 + ($p & 255) * 19) >> 8;

        // Auto-levels over visible pixels so any artwork uses the full range.
        $min = 255;
        $max = 0;
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $p = imagecolorat($src, $x, $y);
                if ((($p >> 24) & 0x7F) < 100) {
                    $l = $lumOf($p);
                    $min = min($min, $l);
                    $max = max($max, $l);
                }
            }
        }
        if ($max - $min < 16) {
            $min = 0;
            $max = 255;
        }

        $lut = [];
        for ($l = 0; $l < 256; $l++) {
            $t = max(0.0, min(1.0, ($l - $min) / ($max - $min)));
            $c = $mode === 'tint'
                ? ($t < 0.5 ? Gfx::mix([0, 0, 0], $c1, $t * 2) : Gfx::mix($c1, [255, 255, 255], ($t - 0.5) * 2))
                : Gfx::mix($c1, $c2, $t);
            $lut[$l] = ($c[0] << 16) | ($c[1] << 8) | $c[2];
        }

        $dst = Gfx::canvas($w, $h);
        imagealphablending($dst, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $p = imagecolorat($src, $x, $y);
                $a = ($p >> 24) & 0x7F;
                if ($a === 127) {
                    continue;
                }
                imagesetpixel($dst, $x, $y, ($a << 24) | $lut[$lumOf($p)]);
            }
        }
        imagealphablending($dst, true);
        return $dst;
    }

    /**
     * Validates an uploaded image and stores it as a 512x512 PNG (re-encoded, metadata stripped).
     * Returns the stored file name.
     */
    public static function storeUpload(string $tmpPath, string $dir, string $name): string
    {
        $info = @getimagesize($tmpPath);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            throw new RuntimeException('The image must be PNG, WEBP or JPG.');
        }
        if ($info[0] < 64 || $info[1] < 64 || $info[0] > 4096 || $info[1] > 4096) {
            throw new RuntimeException('The image must be between 64 and 4096 pixels.');
        }
        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($tmpPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmpPath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
            IMAGETYPE_GIF => @imagecreatefromgif($tmpPath),
        };
        if (!$src) {
            throw new RuntimeException('The image is damaged.');
        }
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        imagesavealpha($src, true);
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = 512 / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = Gfx::canvas(512);
        imagealphablending($dst, false);
        imagecopyresampled($dst, $src, intdiv(512 - $nw, 2), intdiv(512 - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        $file = preg_replace('/[^a-z0-9_-]/i', '', $name) . '.png';
        if (!imagepng($dst, $dir . '/' . $file, 9)) {
            throw new RuntimeException('Could not save the image.');
        }
        return $file;
    }
}
