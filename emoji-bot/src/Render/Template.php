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
        'none' => 'بدون حرکت',
        'pulse' => 'تپش',
        'beat' => 'ضربان قلب',
        'wiggle' => 'تکان',
        'shake' => 'لرزش',
        'float' => 'شناور',
        'bounce' => 'پرش متن',
        'flip' => 'چرخش سکه‌ای',
        'wave' => 'موج پرچم',
        'shine' => 'درخشش',
        'blink' => 'چشمک',
        'rainbow' => 'رنگین‌کمان متن',
        'glow' => 'نئون',
        'press' => 'فشردن',
        'spin_base' => 'چرخش پس‌زمینه',
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
     * @return array{base: GdImage, overlay: ?GdImage}
     */
    abstract public function layers(int $S, array $c1, array $c2): array;

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
