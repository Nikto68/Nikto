<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;

/**
 * An emoji design with a text area.
 */
abstract class Template
{
    /** Supported animation effects => Persian label */
    public const EFFECTS = [
        'none' => 'None (static)',
        'pulse' => 'Pulse',
        'beat' => 'Heartbeat',
        'wiggle' => 'Wiggle',
        'shake' => 'Shake',
        'float' => 'Float',
        'bounce' => 'Bouncing text',
        'flip' => 'Coin flip',
        'wave' => 'Flag wave',
        'shine' => 'Shine',
        'blink' => 'Blink',
        'rainbow' => 'Rainbow text',
        'glow' => 'Neon glow',
        'press' => 'Press',
        'spin_base' => 'Spinning background',
        'peek' => 'Peek over the sign',
        'hover' => 'Hover over the ribbon',
        'grow' => 'Growing chart',
    ];

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $category,
        public readonly string $emoji,
        public readonly string $effect = 'none',
        public readonly int $version = 1,
    ) {
    }

    /**
     * Draws the non-text parts at $S x $S.
     * @param bool $tint recolor artwork with the user's main color (only art templates use it)
     * @return array{base: GdImage, overlay: ?GdImage, back?: ?GdImage} "back" is drawn behind "base"
     *         and is what moves in the peek/hover effects
     */
    abstract public function layers(int $S, array $c1, array $c2, bool $tint = false): array;

    /**
     * How much taller than the text box a logo may be (logos are usually square, text is wide).
     * Templates whose text sits on a board/banner keep logos inside it.
     */
    public function logoHeightFactor(): float
    {
        return 1.9;
    }

    /** True if the template looks different when the user's "tint" switch is on. */
    public function tintable(): bool
    {
        return false;
    }

    /** Text area in unit coordinates: [centerX, centerY, width, height, angleDeg]. */
    abstract public function box(): array;

    abstract public function textStyle(array $c1, array $c2): TextStyle;

    public function animated(): bool
    {
        return $this->effect !== 'none';
    }

    public function cacheKey(): string
    {
        return $this->id . '@' . $this->version;
    }
}
