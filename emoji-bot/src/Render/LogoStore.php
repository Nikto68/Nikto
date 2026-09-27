<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;
use RuntimeException;

/**
 * User-uploaded logos for logo packs. Stored per user as storage/logos/<userId>/<hash>.png.
 * A logo reference ("ref") looks like "123456789/0123456789abcdef0123".
 */
final class LogoStore
{
    private const MAX_SIDE = 512;
    private const KEEP_PER_USER = 12;

    /** @var array<string, GdImage> */
    private array $cache = [];

    public function __construct(private readonly string $dir)
    {
    }

    public static function validRef(string $ref): bool
    {
        return (bool) preg_match('/^\d{1,20}\/[a-f0-9]{20}$/', $ref);
    }

    public static function owner(string $ref): int
    {
        return (int) explode('/', $ref, 2)[0];
    }

    public function exists(string $ref): bool
    {
        return self::validRef($ref) && is_file($this->path($ref));
    }

    private function path(string $ref): string
    {
        return $this->dir . '/' . $ref . '.png';
    }

    public function load(string $ref): GdImage
    {
        if (isset($this->cache[$ref])) {
            return $this->cache[$ref];
        }
        $im = self::validRef($ref) ? @imagecreatefrompng($this->path($ref)) : false;
        if (!$im) {
            throw new RuntimeException('logo not found');
        }
        imagesavealpha($im, true);
        if (count($this->cache) > 8) {
            $this->cache = [];
        }
        return $this->cache[$ref] = $im;
    }

    /** @return string[] newest first */
    public function recent(int $userId, int $limit = 8): array
    {
        $files = glob($this->dir . '/' . $userId . '/*.png') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn ($f) => $userId . '/' . basename($f, '.png'), array_slice($files, 0, $limit));
    }

    /**
     * Validates, cleans (background removal for flat backgrounds, trimming) and stores an upload.
     * @return string logo ref
     */
    public function save(int $userId, string $tmpPath): string
    {
        $info = @getimagesize($tmpPath);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            throw new RuntimeException('The logo must be a PNG, JPG or WEBP image.');
        }
        if ($info[0] < 32 || $info[1] < 32 || $info[0] > 6000 || $info[1] > 6000) {
            throw new RuntimeException('The logo must be between 32 and 6000 pixels.');
        }
        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($tmpPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmpPath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
            IMAGETYPE_GIF => @imagecreatefromgif($tmpPath),
        };
        if (!$src) {
            throw new RuntimeException('The logo image is damaged.');
        }
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        imagesavealpha($src, true);

        // Work at <= 512 px.
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        $im = Gfx::resize($src, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        self::removeFlatBackground($im);
        $im = self::trim($im);
        if ($im === null) {
            throw new RuntimeException('The logo is empty (fully transparent).');
        }

        $png = Gfx::png($im, 9);
        $ref = $userId . '/' . substr(sha1($png), 0, 20);
        $dir = $this->dir . '/' . $userId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not save the logo.');
        }
        file_put_contents($this->path($ref), $png, LOCK_EX);
        touch($this->path($ref));
        foreach (array_slice($this->recent($userId, 100), self::KEEP_PER_USER) as $old) {
            @unlink($this->path($old));
        }
        return $ref;
    }

    /**
     * If the image has no transparency and its border is one flat color (e.g. white JPG),
     * make everything connected to the border with that color transparent.
     */
    private static function removeFlatBackground(GdImage $im): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $corners = [imagecolorat($im, 0, 0), imagecolorat($im, $w - 1, 0), imagecolorat($im, 0, $h - 1), imagecolorat($im, $w - 1, $h - 1)];
        foreach ($corners as $c) {
            if ((($c >> 24) & 0x7F) > 10) {
                return; // already transparent
            }
        }
        $rgb = fn (int $c) => [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
        $ref = $rgb($corners[0]);
        $near = function (int $c, int $tol) use ($rgb, $ref): bool {
            [$r, $g, $b] = $rgb($c);
            return abs($r - $ref[0]) + abs($g - $ref[1]) + abs($b - $ref[2]) <= $tol;
        };
        foreach ($corners as $c) {
            if (!$near($c, 30)) {
                return; // not a flat background: keep the image as it is
            }
        }
        imagealphablending($im, false);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        $seen = [];
        $stack = [];
        for ($x = 0; $x < $w; $x++) {
            $stack[] = [$x, 0];
            $stack[] = [$x, $h - 1];
        }
        for ($y = 0; $y < $h; $y++) {
            $stack[] = [0, $y];
            $stack[] = [$w - 1, $y];
        }
        while ($stack) {
            [$x, $y] = array_pop($stack);
            $key = $y * $w + $x;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (!$near(imagecolorat($im, $x, $y), 60)) {
                continue;
            }
            imagesetpixel($im, $x, $y, $transparent);
            if ($x > 0) {
                $stack[] = [$x - 1, $y];
            }
            if ($x < $w - 1) {
                $stack[] = [$x + 1, $y];
            }
            if ($y > 0) {
                $stack[] = [$x, $y - 1];
            }
            if ($y < $h - 1) {
                $stack[] = [$x, $y + 1];
            }
        }
        imagealphablending($im, true);
    }

    /** Crops fully transparent borders (keeps a small margin). Null if nothing is visible. */
    private static function trim(GdImage $im): ?GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 120) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX < 0) {
            return null;
        }
        $pad = (int) round(max($maxX - $minX, $maxY - $minY) * 0.02);
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $cw = min($w, $maxX + $pad + 1) - $minX;
        $ch = min($h, $maxY + $pad + 1) - $minY;
        $out = Gfx::canvas($cw, $ch);
        imagealphablending($out, false);
        imagecopy($out, $im, 0, 0, $minX, $minY, $cw, $ch);
        imagealphablending($out, true);
        return $out;
    }

    /** Same alpha, every visible pixel painted with $rgb. */
    public static function silhouette(GdImage $src, array $rgb): GdImage
    {
        $im = Gfx::copyOf($src);
        imagefilter($im, IMG_FILTER_BRIGHTNESS, -255);
        imagefilter($im, IMG_FILTER_COLORIZE, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2], 0);
        return $im;
    }
}
