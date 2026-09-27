<?php
declare(strict_types=1);

namespace EmojiBot\Render;

/**
 * How a template wants its text painted.
 */
final class TextStyle
{
    /**
     * @param array $fill   rgb
     * @param ?array $stroke rgb or null for no outline
     * @param float $strokeRatio outline width as a fraction of the font size
     * @param ?array $shadow rgb of a soft drop shadow or null
     */
    public function __construct(
        public readonly array $fill,
        public readonly ?array $stroke = null,
        public readonly float $strokeRatio = 0.0,
        public readonly ?array $shadow = null,
        public readonly int $maxLines = 2,
    ) {
    }

    public function withFill(array $fill): self
    {
        return new self($fill, $this->stroke, $this->strokeRatio, $this->shadow, $this->maxLines);
    }
}
