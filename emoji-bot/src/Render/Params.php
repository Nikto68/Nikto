<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use InvalidArgumentException;

/**
 * User-chosen rendering parameters (text + style). Immutable and validated.
 */
final class Params
{
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
        $text = self::normalizeText((string) ($a['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('متن را وارد کنید.');
        }
        if (mb_strlen($text) > $maxLen) {
            throw new InvalidArgumentException("متن حداکثر $maxLen کاراکتر می‌تواند باشد.");
        }
        if ($latinOnly && preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException('فقط حروف انگلیسی، عدد و علامت‌های ساده مجاز است (مثلاً: Sina).');
        }
        foreach (Shaper::codepoints($text) as $cp) {
            if ($cp === 0x20 || $cp === 0x200C || $cp === 0x200D) {
                continue;
            }
            if (!$fonts->canRender($font, $cp)) {
                throw new InvalidArgumentException('کاراکتر «' . mb_chr($cp) . '» پشتیبانی نمی‌شود (ایموجی و علائم خاص مجاز نیست).');
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
        return ['text' => $this->text, 'font' => $this->font, 'c1' => $this->c1, 'c2' => $this->c2, 'size' => $this->size, 'dy' => $this->dy, 'tint' => $this->tint];
    }

    public function key(): string
    {
        return sha1(json_encode($this->toArray(), JSON_UNESCAPED_UNICODE));
    }
}
