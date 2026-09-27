<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;

/**
 * Renders templates + text into static images or animation frames.
 */
final class Renderer
{
    public const FPS = 30;
    public const FRAMES = 60;        // 2 seconds, seamless loop
    public const PREVIEW_FRAMES = 12;

    private TextRenderer $text;

    /** @var array<string, array> */
    private array $layerCache = [];

    /** @var array<string, GdImage> recolored logos */
    private array $logoCache = [];

    public function __construct(
        private readonly Fonts $fonts,
        private readonly string $cacheDir,
        private readonly ?LogoStore $logos = null,
    ) {
        $this->text = new TextRenderer($fonts);
    }

    // ---- public API ------------------------------------------------------------

    public function renderStatic(Template $t, Params $p, int $out): GdImage
    {
        $P = $this->parts($t, $p, min(512, max(300, $out * 4)));
        return Gfx::resize($this->flatten($P, $this->textLayer($P)), $out);
    }

    /**
     * @param int[]|null $indices frame numbers to render (default: all)
     * @return GdImage[]
     */
    public function renderFrames(Template $t, Params $p, int $out, int $count = self::FRAMES, ?array $indices = null): array
    {
        $P = $this->parts($t, $p, min(512, max(256, $out * 2)));
        $fx = $this->prepare($t->effect, $P, $out);
        $frames = [];
        foreach ($indices ?? range(0, $count - 1) as $i) {
            $frames[] = $this->frame($t->effect, $i / $count, $P, $fx, $out);
        }
        return $frames;
    }

    /**
     * PNG preview. Animated templates return a horizontal sprite of PREVIEW_FRAMES frames.
     * @return array{png: string, frames: int}
     */
    public function preview(Template $t, Params $p, int $size): array
    {
        $key = sha1($t->cacheKey() . '|' . $p->key() . '|' . $size);
        $file = $this->cacheDir . '/preview/' . substr($key, 0, 2) . '/' . $key . '.png';
        $frames = $t->animated() ? self::PREVIEW_FRAMES : 1;
        if (is_file($file)) {
            return ['png' => (string) file_get_contents($file), 'frames' => $frames];
        }
        if ($t->animated()) {
            $step = self::FRAMES / self::PREVIEW_FRAMES;
            $indices = array_map(fn ($i) => (int) round($i * $step), range(0, self::PREVIEW_FRAMES - 1));
            $list = $this->renderFrames($t, $p, $size, self::FRAMES, $indices);
            $im = Gfx::canvas($size * count($list), $size);
            foreach ($list as $i => $f) {
                imagecopy($im, $f, $i * $size, 0, 0, 0, $size, $size);
            }
        } else {
            $im = $this->renderStatic($t, $p, $size);
        }
        $png = Gfx::png($im, 7);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, $png, LOCK_EX);
        return ['png' => $png, 'frames' => $frames];
    }

    // ---- composition -------------------------------------------------------------

    private function parts(Template $t, Params $p, int $S): array
    {
        $tint = $p->tint && $t->tintable();
        $key = $t->cacheKey() . '|' . $p->c1 . $p->c2 . '|' . $S . ($tint ? '|t' : '');
        if (!isset($this->layerCache[$key])) {
            if (count($this->layerCache) > 16) {
                $this->layerCache = [];
            }
            $this->layerCache[$key] = $t->layers($S, $p->rgb1, $p->rgb2, $tint);
        }
        $layers = $this->layerCache[$key];
        $ts = $t->textStyle($p->rgb1, $p->rgb2);
        [$bx, $by, $bw, $bh, $angle] = $t->box();
        $logo = null;
        $logoW = $logoH = 0.0;
        $layout = null;
        if ($p->logo !== null) {
            $logo = $this->logoImage($p);
            // Logos are usually square: let them use more height than a line of text would.
            $lw = $bw * $S;
            $lh = max($bh * $S, min($bw * $S, $bh * $S * $t->logoHeightFactor()));
            $scale = min($lw / imagesx($logo), $lh / imagesy($logo)) * $p->size;
            $logoW = imagesx($logo) * $scale;
            $logoH = imagesy($logo) * $scale;
        } else {
            $layout = $this->text->layout($p->text, $p->font, $bw * $S, $bh * $S, $ts, $p->size);
        }
        return [
            'logo' => $logo,
            'logoW' => $logoW,
            'logoH' => $logoH,
            'S' => $S,
            'base' => $layers['base'],
            'overlay' => $layers['overlay'],
            'back' => $layers['back'] ?? null,
            'ts' => $ts,
            'layout' => $layout,
            'cx' => $bx * $S,
            'cy' => $by * $S - $p->dy * $bh * $S * 0.5, // positive "height" moves the text up
            'angle' => $angle,
            'rgb1' => $p->rgb1,
            'rgb2' => $p->rgb2,
        ];
    }

    /** Loads the user's logo recolored according to the logo mode. */
    private function logoImage(Params $p): GdImage
    {
        if ($this->logos === null) {
            throw new \RuntimeException('logo store not configured');
        }
        $key = $p->logo . '|' . $p->logoMode . '|' . $p->c1 . $p->c2;
        if (!isset($this->logoCache[$key])) {
            $src = $this->logos->load((string) $p->logo);
            $this->logoCache[$key] = match ($p->logoMode) {
                'c1' => LogoStore::silhouette($src, $p->rgb1),
                'c2' => LogoStore::silhouette($src, $p->rgb2),
                'duo' => CustomTemplate::recolor($src, 'duotone', $p->rgb1, $p->rgb2),
                default => $src,
            };
        }
        return $this->logoCache[$key];
    }

    /** Draws the user's content (text or logo) centered on ($cx, $cy). */
    private function drawContent(GdImage $im, array $P, float $cx, float $cy, TextStyle $ts, ?array $fill = null, string $part = 'all'): void
    {
        if ($P['logo'] === null) {
            $this->text->draw($im, $P['layout'], $cx, $cy, $ts, $fill, $part);
            return;
        }
        $w = max(1, (int) round($P['logoW']));
        $h = max(1, (int) round($P['logoH']));
        $logo = Gfx::resize($P['logo'], $w, $h);
        $x = (int) round($cx - $w / 2);
        $y = (int) round($cy - $h / 2);
        $unit = min($w, $h);
        if ($part !== 'fill') {
            if ($ts->shadow !== null) {
                $off = max(1, (int) round($unit * 0.04));
                $sh = LogoStore::silhouette($logo, $ts->shadow);
                Gfx::fade($sh, 0.45);
                imagecopy($im, $sh, $x + (int) round($off * 0.4), $y + $off, 0, 0, $w, $h);
            }
            $stroke = $ts->stroke !== null ? $ts->strokeRatio * 0.4 * $unit : 0.0;
            if ($stroke >= 0.5) {
                $sil = LogoStore::silhouette($logo, $ts->stroke);
                $steps = $stroke > 6 ? 24 : 16;
                for ($i = 0; $i < $steps; $i++) {
                    $a = 2 * M_PI * $i / $steps;
                    imagecopy($im, $sil, (int) round($x + $stroke * cos($a)), (int) round($y + $stroke * sin($a)), 0, 0, $w, $h);
                }
            }
        }
        if ($part !== 'stroke') {
            imagecopy($im, $fill !== null ? LogoStore::silhouette($logo, $fill) : $logo, $x, $y, 0, 0, $w, $h);
        }
    }

    private function textLayer(array $P, ?array $fill = null, string $part = 'all'): GdImage
    {
        $S = $P['S'];
        if (abs($P['angle']) < 0.01) {
            $l = Gfx::canvas($S);
            $this->drawContent($l, $P, $P['cx'], $P['cy'], $P['ts'], $fill, $part);
            return $l;
        }
        $tmp = Gfx::canvas($S);
        $this->drawContent($tmp, $P, $S / 2, $S / 2, $P['ts'], $fill, $part);
        $rot = Gfx::rotate($tmp, -$P['angle']); // imagerotate is counter-clockwise; our angle is clockwise
        $l = Gfx::canvas($S);
        imagecopy($l, $rot, (int) round($P['cx'] - $S / 2), (int) round($P['cy'] - $S / 2), 0, 0, $S, $S);
        return $l;
    }

    private function flatten(array $P, GdImage $text, ?GdImage $base = null, bool $withOverlay = true, bool $withBack = true): GdImage
    {
        $S = $P['S'];
        $im = Gfx::canvas($S);
        if ($withBack && $P['back']) {
            imagecopy($im, $P['back'], 0, 0, 0, 0, $S, $S);
        }
        imagecopy($im, $base ?? $P['base'], 0, 0, 0, 0, $S, $S);
        imagecopy($im, $text, 0, 0, 0, 0, $S, $S);
        if ($withOverlay && $P['overlay']) {
            imagecopy($im, $P['overlay'], 0, 0, 0, 0, $S, $S);
        }
        return $im;
    }

    // ---- effects -----------------------------------------------------------------

    private function prepare(string $effect, array $P, int $out): array
    {
        $fx = [];
        switch ($effect) {
            case 'rainbow':
                $fx['stroke'] = $this->textLayer($P, null, 'stroke');
                break;
            case 'glow':
                $glowStyle = new TextStyle($P['rgb1'], $P['rgb1'], 0.16, null, $P['ts']->maxLines);
                $g = Gfx::canvas($P['S']);
                $this->drawContent($g, $P, $P['cx'], $P['cy'], $glowStyle, $P['rgb1']);
                $fx['glow'] = Gfx::blur($g, 8);
                $fx['text'] = $this->textLayer($P);
                break;
            case 'bounce':
            case 'spin_base':
                $fx['text'] = $this->textLayer($P);
                break;
            case 'peek':
            case 'hover':
            case 'grow':
                // The artwork ("back") moves; the sign/banner with the text stays still.
                $fx['front'] = $this->flatten($P, $this->textLayer($P), null, true, false);
                $fx['flat'] = $this->flatten($P, $this->textLayer($P));
                break;
            case 'wave':
                $W = $out * 2;
                $fx['W'] = $W;
                $fx['cloth'] = Gfx::resize($this->flatten($P, $this->textLayer($P), null, false), $W);
                $fx['pole'] = $P['overlay'] ? Gfx::resize($P['overlay'], $W) : null;
                break;
            case 'shine':
            case 'blink':
                $fx['W'] = $out * 2;
                $fx['flatW'] = Gfx::resize($this->flatten($P, $this->textLayer($P)), $out * 2);
                break;
            default:
                $fx['flat'] = $this->flatten($P, $this->textLayer($P));
        }
        return $fx;
    }

    private function frame(string $effect, float $t, array $P, array $fx, int $out): GdImage
    {
        $S = $P['S'];
        $dst = Gfx::canvas($out);
        switch ($effect) {
            case 'pulse':
                $s = 1 + 0.07 * sin(2 * M_PI * 2 * $t);
                Gfx::placeScaled($dst, $fx['flat'], $s * 0.95, $s * 0.95);
                return $dst;

            case 'beat':
                $u = fmod($t * 2, 1.0);
                $s = 1 + 0.12 * exp(-((($u - 0.12) / 0.055) ** 2)) + 0.07 * exp(-((($u - 0.34) / 0.06) ** 2));
                Gfx::placeScaled($dst, $fx['flat'], $s * 0.9, $s * 0.9);
                return $dst;

            case 'wiggle':
                $a = 7 * sin(2 * M_PI * 2 * $t);
                Gfx::placeScaled($dst, Gfx::rotate($fx['flat'], $a), 0.94, 0.94);
                return $dst;

            case 'shake':
                $dx = 0.0;
                $a = 0.0;
                if ($t < 0.5) {
                    $u = $t / 0.5;
                    $env = sin(M_PI * $u);
                    $dx = 0.035 * $out * sin(2 * M_PI * 5 * $u) * $env;
                    $a = 4 * sin(2 * M_PI * 5 * $u + 0.6) * $env;
                }
                Gfx::placeScaled($dst, abs($a) > 0.05 ? Gfx::rotate($fx['flat'], $a) : $fx['flat'], 0.93, 0.93, $dx);
                return $dst;

            case 'float':
                Gfx::placeScaled($dst, $fx['flat'], 0.92, 0.92, 0, -0.04 * $out * sin(2 * M_PI * $t));
                return $dst;

            case 'grow':
                // Bars grow from the bottom, hold, then drop back (seamless loop); the coin bobs.
                $g = match (true) {
                    $t < 0.35 => 0.15 + 0.85 * self::smooth($t / 0.35),
                    $t < 0.85 => 1.0,
                    default => 1.0 - 0.85 * self::smooth(($t - 0.85) / 0.15),
                };
                if (!$P['back']) {
                    Gfx::placeScaled($dst, $fx['flat'], 0.94, 0.94 * $g, 0, $out * 0.94 * (1 - $g) / 2);
                    return $dst;
                }
                $im = Gfx::canvas($S);
                $h = max(1, (int) round($S * $g));
                imagecopyresampled($im, $P['back'], 0, $S - $h, 0, 0, $S, $h, $S, $S);
                imagecopy($im, $fx['front'], 0, (int) round(-0.03 * $S * sin(2 * M_PI * $t)), 0, 0, $S, $S);
                return Gfx::resize($im, $out);

            case 'peek':
            case 'hover':
                if (!$P['back']) {
                    Gfx::placeScaled($dst, $fx['flat'], 0.92, 0.92, 0, -0.04 * $out * sin(2 * M_PI * $t));
                    return $dst;
                }
                $im = Gfx::canvas($S);
                if ($effect === 'peek') {
                    // Pops up from behind the sign, sways, sinks back.
                    $s = 0.5 - 0.5 * cos(2 * M_PI * $t);
                    $dy = $S * (0.045 - 0.085 * $s);
                    $back = Gfx::rotate($P['back'], 5 * sin(2 * M_PI * 2 * $t) * $s);
                } else {
                    $dy = -0.035 * $S * sin(2 * M_PI * $t);
                    $back = Gfx::rotate($P['back'], 3 * sin(2 * M_PI * $t + 1.2));
                }
                imagecopy($im, $back, 0, (int) round($dy), 0, 0, $S, $S);
                imagecopy($im, $fx['front'], 0, 0, 0, 0, $S, $S);
                return Gfx::resize($im, $out);

            case 'press':
                $press = match (true) {
                    $t < 0.12 => self::smooth($t / 0.12),
                    $t < 0.32 => 1.0,
                    $t < 0.48 => 1 - self::smooth(($t - 0.32) / 0.16),
                    default => 0.0,
                };
                $sy = 0.94 * (1 - 0.08 * $press);
                $sx = 0.94 * (1 + 0.025 * $press);
                Gfx::placeScaled($dst, $fx['flat'], $sx, $sy, 0, $out * (0.94 - $sy) / 2);
                return $dst;

            case 'flip':
                $c = cos(2 * M_PI * $t);
                Gfx::placeScaled($dst, $fx['flat'], max(0.03, abs($c)) * 0.95, 0.95);
                return $dst;

            case 'bounce':
                $im = Gfx::canvas($S);
                imagecopy($im, $P['base'], 0, 0, 0, 0, $S, $S);
                $h = abs(sin(2 * M_PI * $t));
                Gfx::placeScaled($im, $fx['text'], 0.84, 0.84, 0, $S * (0.07 - 0.14 * $h));
                return Gfx::resize($im, $out);

            case 'spin_base':
                $im = $this->flatten($P, $fx['text'], Gfx::rotate($P['base'], -45 * $t));
                return Gfx::resize($im, $out);

            case 'rainbow':
                $color = Gfx::hsv(360 * $t, 0.85, 1.0);
                $im = Gfx::canvas($S);
                imagecopy($im, $P['base'], 0, 0, 0, 0, $S, $S);
                imagecopy($im, $fx['stroke'], 0, 0, 0, 0, $S, $S);
                imagecopy($im, $this->textLayer($P, $color, 'fill'), 0, 0, 0, 0, $S, $S);
                return Gfx::resize($im, $out);

            case 'glow':
                $i = 0.7 + 0.3 * sin(2 * M_PI * 2 * $t);
                if (($t >= 0.30 && $t < 0.34) || ($t >= 0.37 && $t < 0.39)) {
                    $i = 0.15; // neon flicker
                }
                $g = Gfx::copyOf($fx['glow']);
                Gfx::fade($g, $i);
                $im = Gfx::canvas($S);
                imagecopy($im, $P['base'], 0, 0, 0, 0, $S, $S);
                imagecopy($im, $g, 0, 0, 0, 0, $S, $S);
                imagecopy($im, $g, 0, 0, 0, 0, $S, $S);
                imagecopy($im, $fx['text'], 0, 0, 0, 0, $S, $S);
                return Gfx::resize($im, $out);

            case 'wave':
                $W = $fx['W'];
                $im = Gfx::canvas($W);
                $x0 = 0.15 * $W;
                $A = 0.04 * $W;
                $lambda = 0.75 * $W;
                imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
                $shade = [];
                for ($x = 0; $x < $W; $x += 2) {
                    $k = max(0.0, min(1.0, ($x - $x0) / ($W - $x0)));
                    $ph = 2 * M_PI * ($x / $lambda - 2 * $t);
                    $dy = (int) round($A * $k * sin($ph));
                    imagecopy($im, $fx['cloth'], $x, $dy, $x, 0, 2, $W);
                    $shade[$x] = (int) round(128 + 40 * $k * cos($ph));
                }
                imagelayereffect($im, IMG_EFFECT_OVERLAY);
                foreach ($shade as $x => $g) {
                    if ($g !== 128) {
                        imagefilledrectangle($im, $x, 0, $x + 1, $W - 1, imagecolorallocatealpha($im, $g, $g, $g, 0));
                    }
                }
                imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
                if ($fx['pole']) {
                    imagecopy($im, $fx['pole'], 0, 0, 0, 0, $W, $W);
                }
                return Gfx::resize($im, $out);

            case 'shine':
                $W = $fx['W'];
                $im = Gfx::copyOf($fx['flatW']);
                if ($t < 0.55) {
                    $u = $t / 0.55;
                    $c = -0.35 * $W + 1.7 * $W * $u;
                    $tilt = 0.3 * $W;
                    foreach ([[0.09, 214], [0.035, 200]] as $i => [$bw, $gray]) {
                        $off = $i === 0 ? 0 : 0.14 * $W;
                        $b = $bw * $W;
                        Gfx::overlayPolygon($im, [
                            [$c + $off - $b - $tilt, 0], [$c + $off + $b - $tilt, 0],
                            [$c + $off + $b + $tilt, $W], [$c + $off - $b + $tilt, $W],
                        ], $gray);
                    }
                }
                return Gfx::resize($im, $out);

            case 'blink':
                $W = $fx['W'];
                $im = Gfx::copyOf($fx['flatW']);
                $g = (int) round(128 + 64 * max(0.0, sin(2 * M_PI * 2 * $t)) ** 2);
                if ($g > 128) {
                    imagelayereffect($im, IMG_EFFECT_OVERLAY);
                    imagefilledrectangle($im, 0, 0, $W - 1, $W - 1, imagecolorallocatealpha($im, $g, $g, $g, 0));
                    imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
                }
                return Gfx::resize($im, $out);

            default:
                return Gfx::resize($fx['flat'], $out);
        }
    }

    private static function smooth(float $x): float
    {
        $x = max(0.0, min(1.0, $x));
        return $x * $x * (3 - 2 * $x);
    }
}
