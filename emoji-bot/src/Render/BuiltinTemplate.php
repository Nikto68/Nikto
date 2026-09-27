<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use Closure;
use GdImage;

/**
 * Procedurally drawn template. Geometry is authored on a 512x512 grid and scaled.
 */
final class BuiltinTemplate extends Template
{
    /**
     * @param Closure(GdImage, float, array, array): ?GdImage $draw draws the base, may return an overlay layer
     * @param array $box512 [cx, cy, w, h, angle] on the 512 grid
     * @param Closure(array, array): TextStyle $style
     */
    public function __construct(
        string $id,
        string $title,
        string $category,
        string $emoji,
        string $effect,
        private readonly Closure $draw,
        private readonly array $box512,
        private readonly Closure $style,
    ) {
        parent::__construct($id, $title, $category, $emoji, $effect, 1);
    }

    public function layers(int $S, array $c1, array $c2): array
    {
        $base = Gfx::canvas($S);
        $overlay = ($this->draw)($base, $S / 512, $c1, $c2);
        return ['base' => $base, 'overlay' => $overlay];
    }

    public function box(): array
    {
        [$cx, $cy, $w, $h] = $this->box512;
        return [$cx / 512, $cy / 512, $w / 512, $h / 512, (float) ($this->box512[4] ?? 0)];
    }

    public function textStyle(array $c1, array $c2): TextStyle
    {
        return ($this->style)($c1, $c2);
    }
}
