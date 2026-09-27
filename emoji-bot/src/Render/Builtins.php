<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use Closure;
use GdImage;

/**
 * Built-in procedural templates (no third-party artwork). Every shape uses the user's
 * two colors: c1 = main color, c2 = secondary color (outline + text).
 */
final class Builtins
{
    public const CAT_ANIMATED = 'animated';
    public const CAT_CLASSIC = 'classic';

    public const CATEGORY_TITLES = [
        self::CAT_ANIMATED => '✨ اشکال متحرک',
        self::CAT_CLASSIC => 'اشکال ساده (ثابت)',
    ];

    /** @return Template[] */
    public static function all(): array
    {
        $T = [];
        $add = function (string $id, string $title, string $cat, string $emoji, string $effect, Closure $draw, array $box, ?Closure $style = null) use (&$T): void {
            $T[] = new BuiltinTemplate($id, $title, $cat, $emoji, $effect, $draw, $box, $style ?? self::textOn(...));
        };
        $A = self::CAT_ANIMATED;
        $C = self::CAT_CLASSIC;

        // ---- Animated ------------------------------------------------------
        $add('b_flag_wave', 'پرچم مواج', $A, '🚩', 'wave', self::flag(false), [276, 205, 330, 190]);
        $add('b_heart_beat', 'قلب تپنده', $A, '💓', 'beat', self::heart(...), [256, 236, 270, 150]);
        $add('b_coin_flip', 'سکه چرخان', $A, '🪙', 'flip', self::coin(...), [256, 254, 270, 160]);
        $add('b_shield_shine', 'سپر درخشان', $A, '🛡', 'shine', self::shield(...), [256, 222, 280, 150]);
        $add('b_badge_pulse', 'نشان تپنده', $A, '✨', 'pulse', self::badge(...), [256, 256, 300, 170]);
        $add('b_rainbow', 'متن رنگین‌کمان', $A, '🌈', 'rainbow', self::none(...), [256, 256, 470, 470], self::textPlain(...));
        $add('b_neon', 'نئون', $A, '💡', 'glow', self::neon(...), [256, 256, 380, 300], self::textNeon(...));
        $add('b_sign_wiggle', 'تابلو لرزان', $A, '👋', 'wiggle', self::sign(...), [256, 196, 370, 210], self::textDark(...));
        $add('b_star_pulse', 'ستاره تپنده', $A, '⭐', 'pulse', self::star(...), [256, 284, 190, 100]);
        $add('b_bounce', 'متن جهنده', $A, '🔥', 'bounce', self::none(...), [256, 256, 470, 470], self::textPlain(...));
        $add('b_key_press', 'کلید فشاری', $A, '⌨️', 'press', self::keycap(...), [256, 226, 300, 200]);
        $add('b_pin_float', 'پین شناور', $A, '📍', 'float', self::pin(...), [256, 206, 270, 170]);
        $add('b_warn_blink', 'هشدار چشمک‌زن', $A, '⚠️', 'blink', self::warning(...), [256, 330, 230, 110]);
        $add('b_bubble_shake', 'حباب لرزان', $A, '💬', 'shake', self::bubble(...), [256, 222, 360, 230]);
        $add('b_seal_spin', 'مهر چرخان', $A, '🏵', 'spin_base', self::seal(...), [256, 256, 290, 170]);
        $add('b_chart_grow', 'نمودار صعودی', $A, '📈', 'grow', self::chart(true), [104, 236, 118, 118]);
        $add('b_chart_fall', 'نمودار نزولی', $A, '📉', 'grow', self::chart(false), [408, 236, 118, 118]);

        // ---- Classic (static) ---------------------------------------------
        $add('b_shield', 'سپر', $C, '🛡', 'none', self::shield(...), [256, 222, 280, 150]);
        $add('b_badge', 'نشان', $C, '🔵', 'none', self::badge(...), [256, 256, 300, 170]);
        $add('b_keycap', 'کلید', $C, '⌨️', 'none', self::keycap(...), [256, 226, 300, 200]);
        $add('b_heart', 'قلب', $C, '❤️', 'none', self::heart(...), [256, 236, 270, 150]);
        $add('b_bubble', 'حباب گفتگو', $C, '💬', 'none', self::bubble(...), [256, 222, 360, 230]);
        $add('b_pin', 'پین', $C, '📍', 'none', self::pin(...), [256, 206, 270, 170]);
        $add('b_warning', 'مثلث', $C, '⚠️', 'none', self::warning(...), [256, 330, 230, 110]);
        $add('b_star', 'ستاره', $C, '⭐', 'none', self::star(...), [256, 284, 190, 100]);
        $add('b_hexagon', 'شش‌ضلعی', $C, '🔷', 'none', self::hexagon(...), [256, 256, 320, 180]);
        $add('b_ribbon', 'روبان', $C, '🎗', 'none', self::ribbon(...), [256, 245, 360, 130]);
        $add('b_sign', 'تابلو', $C, '👋', 'none', self::sign(...), [256, 196, 370, 210], self::textDark(...));
        $add('b_coin', 'سکه', $C, '🪙', 'none', self::coin(...), [256, 254, 270, 160]);
        $add('b_tag', 'برچسب', $C, '🏷', 'none', self::tag(...), [318, 256, 280, 200]);
        $add('b_seal', 'مهر', $C, '🏵', 'none', self::seal(...), [256, 256, 290, 170]);
        $add('b_flag', 'پرچم', $C, '🚩', 'none', self::flag(true), [276, 200, 330, 190]);
        $add('b_pill', 'کپسول', $C, '💊', 'none', self::pill(...), [256, 256, 380, 150]);
        $add('b_crown', 'تاج', $C, '👑', 'none', self::crown(...), [256, 300, 290, 150]);
        $add('b_cloud', 'ابر', $C, '☁️', 'none', self::cloud(...), [256, 312, 350, 150]);
        $add('b_diamond', 'لوزی', $C, '💎', 'none', self::diamond(...), [256, 256, 280, 150]);
        $add('b_octagon', 'هشت‌ضلعی', $C, '🛑', 'none', self::octagon(...), [256, 256, 330, 180]);
        $add('b_square', 'مربع', $C, '🟥', 'none', self::square(...), [256, 256, 380, 320]);
        $add('b_circle', 'دایره', $C, '🔴', 'none', self::circle(...), [256, 256, 360, 220]);
        $add('b_ticket', 'بلیت', $C, '🎟', 'none', self::ticket(...), [206, 256, 300, 190]);
        $add('b_medal', 'مدال', $C, '🏅', 'none', self::medal(...), [256, 318, 240, 150]);
        $add('b_plain', 'فقط متن', $C, '🔤', 'none', self::none(...), [256, 256, 470, 470], self::textPlain(...));
        $add('b_chart_up', 'نمودار صعودی', $C, '📈', 'none', self::chart(true), [104, 236, 118, 118]);
        $add('b_chart_down', 'نمودار نزولی', $C, '📉', 'none', self::chart(false), [408, 236, 118, 118]);

        return $T;
    }

    // ---- text styles -------------------------------------------------------

    public static function textOn(array $c1, array $c2): TextStyle
    {
        return new TextStyle($c2, null, 0.0, Gfx::shade($c1, -0.6), 2);
    }

    public static function textDark(array $c1, array $c2): TextStyle
    {
        return new TextStyle($c1, null, 0.0, null, 2);
    }

    public static function textPlain(array $c1, array $c2): TextStyle
    {
        return new TextStyle($c1, $c2, 0.13, null, 3);
    }

    public static function textNeon(array $c1, array $c2): TextStyle
    {
        return new TextStyle($c2, $c1, 0.035, null, 2);
    }

    // ---- shape helpers -----------------------------------------------------

    private static function poly(array $pts512, float $k): Closure
    {
        $pts = Gfx::scalePoints($pts512, $k);
        return static fn (GdImage $im, int $c, float $ox = 0, float $oy = 0) => Gfx::polygon($im, $pts, $c, $ox, $oy);
    }

    private static function disc(float $cx, float $cy, float $r, float $k): Closure
    {
        return static fn (GdImage $im, int $c, float $ox = 0, float $oy = 0) => Gfx::circle($im, $cx * $k + $ox, $cy * $k + $oy, $r * $k, $c);
    }

    private static function union(Closure ...$shapes): Closure
    {
        return static function (GdImage $im, int $c, float $ox = 0, float $oy = 0) use ($shapes): void {
            foreach ($shapes as $s) {
                $s($im, $c, $ox, $oy);
            }
        };
    }

    /**
     * The common "sticker" look: c2 outline, c1 fill with vertical shading and an optional gloss.
     * @param array{outline?: float, outlineColor?: array, fill?: array, decorate?: Closure, y0?: float, y1?: float, top?: int, bottom?: int, gloss?: array} $o
     */
    private static function sticker(GdImage $im, float $k, Closure $shape, array $c1, array $c2, array $o = []): void
    {
        $S = imagesx($im);
        $outline = $o['outline'] ?? 14;
        if ($outline > 0) {
            Gfx::outline($im, $shape, Gfx::col($im, $o['outlineColor'] ?? $c2), $outline * $k);
        }
        $layer = Gfx::canvas($S);
        $shape($layer, Gfx::col($layer, $o['fill'] ?? $c1), 0.0, 0.0);
        if (isset($o['decorate'])) {
            ($o['decorate'])($layer);
        }
        Gfx::shadeV($layer, ($o['y0'] ?? 40) * $k, ($o['y1'] ?? 470) * $k, $o['top'] ?? 156, $o['bottom'] ?? 104);
        if (isset($o['gloss'])) {
            [$gx, $gy, $gw, $gh] = $o['gloss'];
            Gfx::gloss($layer, $gx * $k, $gy * $k, $gw * $k, $gh * $k, 170);
        }
        imagecopy($im, $layer, 0, 0, 0, 0, $S, $S);
    }

    /** Draws a ring (outer radius r, width w) in color on the given layer. */
    private static function ring(GdImage $im, float $cx, float $cy, float $r, float $w, array $color, array $inner, float $k): void
    {
        Gfx::circle($im, $cx * $k, $cy * $k, $r * $k, Gfx::col($im, $color));
        Gfx::circle($im, $cx * $k, $cy * $k, ($r - $w) * $k, Gfx::col($im, $inner));
    }

    // ---- designs -------------------------------------------------------------

    private static function none(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        return null;
    }

    private static function shieldPoints(): array
    {
        $pts = [[92, 60], [420, 60], [420, 236]];
        array_push($pts, ...array_slice(Gfx::bezier([420, 236], [416, 372], [256, 470], 14), 1));
        array_push($pts, ...array_slice(Gfx::bezier([256, 470], [96, 372], [92, 236], 14), 1, -1));
        return Gfx::roundedPolygon($pts, 34, 8);
    }

    private static function shield(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        self::sticker($im, $k, self::poly(self::shieldPoints(), $k), $c1, $c2, [
            'outline' => 16,
            'gloss' => [200, 110, 300, 170],
        ]);
        return null;
    }

    private static function badge(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        self::sticker($im, $k, self::disc(256, 256, 214, $k), $c1, $c2, [
            'outline' => 16,
            'decorate' => fn (GdImage $l) => self::ring($l, 256, 256, 188, 9, $c2, $c1, $k),
            'gloss' => [200, 150, 280, 180],
        ]);
        return null;
    }

    private static function keycap(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $side = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 56 * $k, 92 * $k, 456 * $k, 456 * $k, 74 * $k, $c, $ox, $oy);
        $top = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 80 * $k, 60 * $k, 432 * $k, 388 * $k, 62 * $k, $c, $ox, $oy);
        Gfx::outline($im, self::union($side, $top), Gfx::col($im, $c2), 12 * $k);
        $side($im, Gfx::col($im, Gfx::shade($c1, -0.38)), 0, 0);
        self::sticker($im, $k, $top, $c1, $c2, ['outline' => 0, 'y0' => 60, 'y1' => 390, 'top' => 164, 'bottom' => 112, 'gloss' => [210, 110, 300, 120]]);
        return null;
    }

    private static function heart(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        self::sticker($im, $k, self::poly(Gfx::heart(40, 62, 472, 462), $k), $c1, $c2, [
            'outline' => 16,
            'gloss' => [168, 150, 150, 110],
        ]);
        return null;
    }

    private static function bubble(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = self::union(
            static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 44 * $k, 70 * $k, 468 * $k, 372 * $k, 88 * $k, $c, $ox, $oy),
            self::poly([[118, 330], [236, 356], [92, 466]], $k),
        );
        self::sticker($im, $k, $shape, $c1, $c2, ['outline' => 16, 'y0' => 70, 'y1' => 470, 'gloss' => [220, 120, 340, 110]]);
        return null;
    }

    private static function pin(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = self::union(self::disc(256, 206, 176, $k), self::poly([[119.8, 317.5], [392.2, 317.5], [256, 486]], $k));
        self::sticker($im, $k, $shape, $c1, $c2, ['outline' => 15, 'y0' => 30, 'y1' => 486, 'gloss' => [210, 120, 220, 140]]);
        return null;
    }

    private static function warning(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon([[256, 54], [476, 438], [36, 438]], 50, 10);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, ['outline' => 16, 'y0' => 54, 'y1' => 438]);
        return null;
    }

    private static function star(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon(Gfx::star(256, 276, 240, 116, 5), 22, 8);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, ['outline' => 15, 'y0' => 36, 'y1' => 470, 'gloss' => [220, 200, 200, 140]]);
        return null;
    }

    private static function hexagon(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon(Gfx::regularPolygon(256, 256, 236, 6, -90), 36, 8);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, ['outline' => 16, 'gloss' => [210, 150, 300, 160]]);
        return null;
    }

    private static function ribbon(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $tailL = [[8, 214], [120, 214], [120, 378], [8, 378], [60, 296]];
        $tailR = [[504, 214], [392, 214], [392, 378], [504, 378], [452, 296]];
        $foldL = [[64, 330], [120, 330], [120, 378]];
        $foldR = [[448, 330], [392, 330], [392, 378]];
        $band = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 64 * $k, 156 * $k, 448 * $k, 332 * $k, 16 * $k, $c, $ox, $oy);
        Gfx::outline($im, self::union(self::poly($tailL, $k), self::poly($tailR, $k), $band), Gfx::col($im, $c2), 12 * $k);
        $dark = Gfx::col($im, Gfx::shade($c1, -0.32));
        self::poly($tailL, $k)($im, $dark);
        self::poly($tailR, $k)($im, $dark);
        $darker = Gfx::col($im, Gfx::shade($c1, -0.6));
        self::poly($foldL, $k)($im, $darker);
        self::poly($foldR, $k)($im, $darker);
        self::sticker($im, $k, $band, $c1, $c2, ['outline' => 0, 'y0' => 156, 'y1' => 332, 'gloss' => [256, 170, 380, 70]]);
        return null;
    }

    private static function sign(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $stick = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 230 * $k, 290 * $k, 282 * $k, 500 * $k, 12 * $k, $c, $ox, $oy);
        $board = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 34 * $k, 50 * $k, 478 * $k, 342 * $k, 42 * $k, $c, $ox, $oy);
        Gfx::outline($im, self::union($stick, $board), Gfx::col($im, $c2), 10 * $k);
        $stick($im, Gfx::col($im, Gfx::shade($c1, -0.4)));
        $board($im, Gfx::col($im, $c1));
        Gfx::roundedRect($im, 58 * $k, 74 * $k, 454 * $k, 318 * $k, 24 * $k, Gfx::col($im, $c2));
        return null;
    }

    private static function coin(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $edge = self::disc(256, 276, 216, $k);
        $face = self::disc(256, 252, 216, $k);
        Gfx::outline($im, self::union($edge, $face), Gfx::col($im, $c2), 11 * $k);
        $edge($im, Gfx::col($im, Gfx::shade($c1, -0.42)));
        self::sticker($im, $k, $face, $c1, $c2, [
            'outline' => 0,
            'y0' => 36,
            'y1' => 468,
            'decorate' => fn (GdImage $l) => self::ring($l, 256, 252, 182, 12, Gfx::shade($c1, -0.28), $c1, $k),
            'gloss' => [196, 150, 200, 150],
        ]);
        return null;
    }

    private static function tag(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon([[34, 256], [150, 102], [478, 102], [478, 410], [150, 410]], 34, 8);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, ['outline' => 15, 'y0' => 100, 'y1' => 410]);
        Gfx::punchCircle($im, 126 * $k, 256 * $k, 26 * $k);
        return null;
    }

    private static function seal(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon(Gfx::star(256, 256, 240, 208, 16), 7, 4);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, [
            'outline' => 12,
            'decorate' => fn (GdImage $l) => self::ring($l, 256, 256, 182, 8, $c2, $c1, $k),
        ]);
        return null;
    }

    /** Flag: waving outline when static, flat cloth + separate pole overlay for the wave animation. */
    private static function flag(bool $static): Closure
    {
        return static function (GdImage $im, float $k, array $c1, array $c2) use ($static): ?GdImage {
            $top = [];
            $bottom = [];
            for ($i = 0; $i <= 32; $i++) {
                $x = 80 + 390 * $i / 32;
                $w = $static ? 18 * sin(2 * M_PI * $i / 32) : 0;
                $top[] = [$x, 72 + $w];
                $bottom[] = [$x, 334 + $w];
            }
            $cloth = Gfx::roundedPolygon(array_merge($top, array_reverse($bottom)), 10, 4);
            self::sticker($im, $k, self::poly($cloth, $k), $c1, $c2, ['outline' => 12, 'y0' => 60, 'y1' => 350, 'gloss' => [240, 110, 320, 90]]);

            $poleLayer = $static ? $im : Gfx::canvas(imagesx($im));
            $pole = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 50 * $k, 44 * $k, 82 * $k, 498 * $k, 16 * $k, $c, $ox, $oy);
            $knob = self::disc(66, 44, 24, $k);
            Gfx::outline($poleLayer, self::union($pole, $knob), Gfx::col($poleLayer, Gfx::shade($c1, -0.5)), 7 * $k);
            $pole($poleLayer, Gfx::col($poleLayer, $c2));
            $knob($poleLayer, Gfx::col($poleLayer, $c2));
            Gfx::shadeV($poleLayer, 0, 512 * $k, 128, 128);
            return $static ? null : $poleLayer;
        };
    }

    private static function pill(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 26 * $k, 148 * $k, 486 * $k, 364 * $k, 108 * $k, $c, $ox, $oy);
        self::sticker($im, $k, $shape, $c1, $c2, ['outline' => 15, 'y0' => 148, 'y1' => 364, 'gloss' => [256, 176, 380, 70]]);
        return null;
    }

    private static function crown(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $body = Gfx::roundedPolygon([[76, 430], [76, 150], [172, 246], [256, 92], [340, 246], [436, 150], [436, 430]], 18, 6);
        $shape = self::union(self::poly($body, $k), self::disc(76, 146, 30, $k), self::disc(256, 88, 34, $k), self::disc(436, 146, 30, $k));
        self::sticker($im, $k, $shape, $c1, $c2, [
            'outline' => 15,
            'y0' => 60,
            'y1' => 430,
            'decorate' => function (GdImage $l) use ($k, $c1, $c2): void {
                Gfx::roundedRect($l, 76 * $k, 360 * $k, 436 * $k, 430 * $k, 16 * $k, Gfx::col($l, Gfx::shade($c1, -0.25)));
                foreach ([[76, 146, 22], [256, 88, 25], [436, 146, 22]] as [$x, $y, $r]) {
                    Gfx::circle($l, $x * $k, $y * $k, $r * $k, Gfx::col($l, $c2));
                }
            },
        ]);
        return null;
    }

    private static function cloud(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = self::union(
            static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 40 * $k, 236 * $k, 472 * $k, 410 * $k, 86 * $k, $c, $ox, $oy),
            self::disc(178, 240, 104, $k),
            self::disc(304, 204, 128, $k),
        );
        self::sticker($im, $k, $shape, $c1, $c2, ['outline' => 15, 'y0' => 76, 'y1' => 410, 'gloss' => [280, 160, 240, 110]]);
        return null;
    }

    private static function diamond(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $pts = Gfx::roundedPolygon([[256, 32], [480, 256], [256, 480], [32, 256]], 46, 8);
        self::sticker($im, $k, self::poly($pts, $k), $c1, $c2, ['outline' => 15, 'y0' => 32, 'y1' => 480, 'gloss' => [220, 150, 220, 130]]);
        return null;
    }

    private static function octagon(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $outer = Gfx::roundedPolygon(Gfx::regularPolygon(256, 256, 238, 8, -112.5), 22, 6);
        $inner1 = Gfx::regularPolygon(256, 256, 204, 8, -112.5);
        $inner2 = Gfx::regularPolygon(256, 256, 192, 8, -112.5);
        self::sticker($im, $k, self::poly($outer, $k), $c1, $c2, [
            'outline' => 12,
            'decorate' => function (GdImage $l) use ($k, $c1, $c2, $inner1, $inner2): void {
                self::poly($inner1, $k)($l, Gfx::col($l, $c2));
                self::poly($inner2, $k)($l, Gfx::col($l, $c1));
            },
        ]);
        return null;
    }

    private static function square(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 34 * $k, 34 * $k, 478 * $k, 478 * $k, 104 * $k, $c, $ox, $oy);
        self::sticker($im, $k, $shape, $c1, $c2, ['outline' => 14, 'gloss' => [256, 100, 380, 150]]);
        return null;
    }

    private static function circle(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        self::sticker($im, $k, self::disc(256, 256, 232, $k), $c1, $c2, ['outline' => 12, 'gloss' => [210, 150, 260, 170]]);
        return null;
    }

    private static function ticket(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 22 * $k, 124 * $k, 490 * $k, 388 * $k, 28 * $k, $c, $ox, $oy);
        self::sticker($im, $k, $shape, $c1, $c2, [
            'outline' => 14,
            'y0' => 124,
            'y1' => 388,
            'decorate' => function (GdImage $l) use ($k, $c2): void {
                for ($y = 150; $y < 364; $y += 34) {
                    Gfx::roundedRect($l, 380 * $k, $y * $k, 392 * $k, ($y + 18) * $k, 6 * $k, Gfx::col($l, $c2));
                }
            },
        ]);
        Gfx::punchCircle($im, 22 * $k, 256 * $k, 40 * $k);
        Gfx::punchCircle($im, 490 * $k, 256 * $k, 40 * $k);
        return null;
    }

    private static function medal(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $left = [[140, 20], [234, 20], [306, 214], [212, 214]];
        $right = [[372, 20], [278, 20], [206, 214], [300, 214]];
        Gfx::outline($im, self::union(self::poly($left, $k), self::poly($right, $k)), Gfx::col($im, $c2), 10 * $k);
        self::poly($left, $k)($im, Gfx::col($im, Gfx::shade($c1, -0.25)));
        self::poly($right, $k)($im, Gfx::col($im, Gfx::shade($c1, -0.45)));
        self::sticker($im, $k, self::disc(256, 318, 176, $k), $c1, $c2, [
            'outline' => 14,
            'y0' => 142,
            'y1' => 494,
            'decorate' => fn (GdImage $l) => self::ring($l, 256, 318, 150, 9, $c2, $c1, $k),
            'gloss' => [210, 230, 200, 130],
        ]);
        return null;
    }

    /**
     * Crypto-style bar chart (green up / red down) with a coin that carries the text or logo.
     * The bars are a separate "back" layer so the grow effect can animate them.
     */
    private static function chart(bool $up): Closure
    {
        return static function (GdImage $im, float $k, array $c1, array $c2) use ($up): array {
            $S = imagesx($im);
            $color = $up ? [34, 197, 94] : [239, 68, 68];
            $tops = $up ? [352, 226, 70] : [70, 226, 352];
            $bars = Gfx::canvas($S);
            $shapes = [];
            foreach ([[40, 160], [196, 316], [352, 472]] as $i => [$x1, $x2]) {
                $top = $tops[$i];
                $shapes[] = static fn (GdImage $b, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($b, $x1 * $k, $top * $k, $x2 * $k, 486 * $k, 14 * $k, $c, $ox, $oy);
            }
            Gfx::outline($bars, self::union(...$shapes), Gfx::col($bars, Gfx::shade($color, -0.6)), 10 * $k);
            $fill = Gfx::canvas($S);
            foreach ($shapes as $i => $s) {
                $s($fill, Gfx::col($fill, $color));
                $x1 = [40, 196, 352][$i];
                Gfx::roundedRect($fill, ($x1 + 14) * $k, ($tops[$i] + 14) * $k, ($x1 + 34) * $k, 470 * $k, 8 * $k, Gfx::col($fill, Gfx::shade($color, 0.45)));
            }
            Gfx::shadeV($fill, 60 * $k, 486 * $k, 150, 110);
            imagecopy($bars, $fill, 0, 0, 0, 0, $S, $S);

            // Coin above the shortest bar.
            $cx = $up ? 100 : 412;
            self::sticker($im, $k, self::disc($cx, 236, 86, $k), $c1, $c2, [
                'outline' => 10,
                'outlineColor' => Gfx::shade($c1, -0.55),
                'y0' => 150,
                'y1' => 322,
                'decorate' => fn (GdImage $l) => self::ring($l, $cx, 236, 74, 6, Gfx::shade($c1, 0.35), $c1, $k),
                'gloss' => [$cx - 20, 200, 90, 60],
            ]);
            return ['back' => $bars];
        };
    }

    private static function neon(GdImage $im, float $k, array $c1, array $c2): ?GdImage
    {
        $shape = static fn (GdImage $i, int $c, float $ox = 0, float $oy = 0) => Gfx::roundedRect($i, 26 * $k, 26 * $k, 486 * $k, 486 * $k, 100 * $k, $c, $ox, $oy);
        Gfx::outline($im, $shape, Gfx::col($im, $c1), 8 * $k);
        $shape($im, Gfx::col($im, Gfx::mix($c1, [8, 8, 16], 0.88)));
        return null;
    }
}
