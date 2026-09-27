<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use InvalidArgumentException;

/**
 * User-chosen rendering parameters (text + style). Immutable and validated.
 */
final class Params
{
    /** original colors | solid main color | solid second color | two-tone (dark->c1, light->c2) */
    public const LOGO_MODES = ['original', 'c1', 'c2', 'duo'];

    public readonly array $rgb1;
    public readonly array $rgb2;

    private function __construct(
        public readonly string $text,
        public readonly string $font,
        public readonly string $c1,
        public readonly string $c2,
        public readonly float $size,
        public readonly float $dy,
        public readonly bool $tint = false,
        public readonly ?string $logo = null,
        public readonly string $logoMode = 'original',
    ) {
        $this->rgb1 = Gfx::rgb($c1);
        $this->rgb2 = Gfx::rgb($c2);
    }

    /**
     * @throws InvalidArgumentException with a user-facing (Persian) message
     */
    /**
     * @param bool $latinOnly only English letters, digits and basic symbols (default policy for users)
     */
    public static function fromArray(array $a, Fonts $fonts, int $maxLen, bool $latinOnly = false): self
    {
        $font = (string) ($a['font'] ?? Fonts::DEFAULT);
        if (!$fonts->exists($font)) {
            $font = Fonts::DEFAULT;
        }
        $logo = is_string($a['logo'] ?? null) && LogoStore::validRef($a['logo']) ? $a['logo'] : null;
        $logoMode = in_array($a['logo_mode'] ?? '', self::LOGO_MODES, true) ? $a['logo_mode'] : 'original';
        $text = $logo !== null ? '' : self::normalizeText((string) ($a['text'] ?? ''));
        if ($text === '' && $logo === null) {
            throw new InvalidArgumentException('Please enter your text.');
        }
        if ($text !== '' && mb_strlen($text) > $maxLen) {
            throw new InvalidArgumentException("Text can be at most $maxLen characters.");
        }
        if ($latinOnly && preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException('English letters, numbers and basic symbols only (e.g. Sina).');
        }
        foreach (Shaper::codepoints($text) as $cp) {
            if ($cp === 0x20 || $cp === 0x200C || $cp === 0x200D) {
                continue;
            }
            if (!$fonts->canRender($font, $cp)) {
                throw new InvalidArgumentException('The character "' . mb_chr($cp) . '" is not supported (no emoji or special symbols).');
            }
        }
        return new self(
            $text,
            $font,
            self::color($a['c1'] ?? '#ff2d55', '#ff2d55'),
            self::color($a['c2'] ?? '#ffffff', '#ffffff'),
            round(max(0.5, min(1.6, (float) ($a['size'] ?? 1.0))), 2),
            round(max(-1.0, min(1.0, (float) ($a['dy'] ?? 0.0))), 2),
            filter_var($a['tint'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $logo,
            $logoMode,
        );
    }

    public static function normalizeText(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        // Drop control/format characters except ZWNJ/ZWJ, collapse whitespace.
        $text = preg_replace('/[\p{Cc}\p{Co}\p{Cs}]|(?![\x{200C}\x{200D}])\p{Cf}/u', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }

    private static function color(mixed $v, string $default): string
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        return preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : $default;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text, 'font' => $this->font, 'c1' => $this->c1, 'c2' => $this->c2,
            'size' => $this->size, 'dy' => $this->dy, 'tint' => $this->tint,
            'logo' => $this->logo, 'logo_mode' => $this->logoMode,
        ];
    }

    public function key(): string
    {
        return sha1(json_encode($this->toArray(), JSON_UNESCAPED_UNICODE));
    }
}
