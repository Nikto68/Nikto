<?php
declare(strict_types=1);

namespace EmojiBot\Packs;

use CURLFile;
use EmojiBot\App;
use EmojiBot\Render\Params;
use EmojiBot\Render\Template;
use EmojiBot\TelegramError;
use RuntimeException;

/**
 * Talks to the Bot API sticker methods: builds custom emoji sets owned by the user.
 */
final class PackService
{
    public const EMOJI_SIZE = 100;
    public const MAX_SET_SIZE = 200;   // custom emoji sets can hold up to 200 emoji
    public const MAX_INITIAL = 50;     // createNewStickerSet accepts 1..50 stickers

    public function __construct(private readonly App $app)
    {
    }

    public static function link(string $name): string
    {
        return 'https://t.me/addemoji/' . $name;
    }

    /**
     * Renders one emoji file.
     * @return array{path: string, format: string, emoji: string}
     */
    public function renderItem(Template $t, Params $p, string $dir, int $index): array
    {
        $renderer = $this->app->renderer();
        if ($t->animated()) {
            $frames = $renderer->renderFrames($t, $p, self::EMOJI_SIZE);
            $path = sprintf('%s/e%03d.webm', $dir, $index);
            $this->app->video()->encode($frames, $path);
            return ['path' => $path, 'format' => 'video', 'emoji' => $t->emoji];
        }
        $im = $renderer->renderStatic($t, $p, self::EMOJI_SIZE);
        $path = sprintf('%s/e%03d.png', $dir, $index);
        if (!imagepng($im, $path, 9)) {
            throw new RuntimeException('cannot write png');
        }
        return ['path' => $path, 'format' => 'static', 'emoji' => $t->emoji];
    }

    /** Unique set name that satisfies Telegram's rules and ends with _by_<bot>. */
    public function newSetName(int $userId): string
    {
        $bot = strtolower($this->app->botUsername());
        $name = 'e' . base_convert((string) $userId, 10, 36) . 'x' . bin2hex(random_bytes(3)) . '_by_' . $bot;
        if (strlen($name) > 64) {
            $name = 'e' . bin2hex(random_bytes(4)) . '_by_' . $bot;
        }
        return preg_replace('/_+/', '_', $name);
    }

    public function title(string $raw, string $fallback): string
    {
        $title = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $raw) ?? '');
        $title = preg_replace('/\s+/u', ' ', $title) ?? '';
        if ($title === '') {
            $title = $fallback;
        }
        $suffix = trim((string) $this->app->settings()->get('title_suffix'));
        $max = 64 - ($suffix !== '' ? mb_strlen($suffix) + 3 : 0);
        $title = mb_substr($title, 0, max(8, $max));
        return $suffix !== '' ? $title . ' | ' . $suffix : $title;
    }

    private static function inputSticker(array $item, string $attach, bool $fallbackEmoji): array
    {
        return [
            'sticker' => 'attach://' . $attach,
            'format' => $item['format'],
            'emoji_list' => [$fallbackEmoji ? '⭐' : $item['emoji']],
        ];
    }

    /**
     * Creates the set with the first (up to 50) items; the rest are added one by one.
     * @param array<array{path: string, format: string, emoji: string}> $items
     * @param callable(int): void $onUploaded receives the number of uploaded items so far
     * @return array{name: string, added: int}
     */
    public function createSet(int $userId, string $title, array $items, callable $onUploaded): array
    {
        $first = array_slice($items, 0, self::MAX_INITIAL);
        $rest = array_slice($items, self::MAX_INITIAL);
        $fallbackEmoji = false;
        $name = $this->newSetName($userId);

        for ($attempt = 0; ; $attempt++) {
            $params = ['user_id' => $userId, 'name' => $name, 'title' => $title, 'sticker_type' => 'custom_emoji'];
            $stickers = [];
            foreach ($first as $i => $item) {
                $params['f' . $i] = new CURLFile($item['path'], $item['format'] === 'video' ? 'video/webm' : 'image/png', basename($item['path']));
                $stickers[] = self::inputSticker($item, 'f' . $i, $fallbackEmoji);
            }
            $params['stickers'] = $stickers;
            try {
                $this->app->tg()->call('createNewStickerSet', $params, 180, 30);
                break;
            } catch (TelegramError $e) {
                $msg = $e->getMessage();
                if ($attempt < 2 && str_contains($msg, 'already occupied')) {
                    $name = $this->newSetName($userId);
                    continue;
                }
                if ($attempt < 2 && !$fallbackEmoji && stripos($msg, 'emoji') !== false) {
                    $fallbackEmoji = true;
                    continue;
                }
                throw $e;
            }
        }
        $added = count($first);
        $onUploaded($added);
        // The set exists now; a failure while adding the rest must not lose it (partial success).
        $added += $this->addItems($userId, $name, $rest, $fallbackEmoji, fn (int $n) => $onUploaded(count($first) + $n), false);
        return ['name' => $name, 'added' => $added];
    }

    /**
     * Adds items one by one. Stops at the first error: throws it if nothing was added yet
     * (and $throwIfNone), otherwise returns the partial count.
     * @return int number of successfully added items
     */
    public function addItems(int $userId, string $name, array $items, bool $fallbackEmoji, callable $onUploaded, bool $throwIfNone = true): int
    {
        $added = 0;
        foreach ($items as $item) {
            $params = [
                'user_id' => $userId,
                'name' => $name,
                'f0' => new CURLFile($item['path'], $item['format'] === 'video' ? 'video/webm' : 'image/png', basename($item['path'])),
                'sticker' => self::inputSticker($item, 'f0', $fallbackEmoji),
            ];
            try {
                try {
                    $this->app->tg()->call('addStickerToSet', $params, 60, 30);
                } catch (TelegramError $e) {
                    if ($fallbackEmoji || stripos($e->getMessage(), 'emoji') === false) {
                        throw $e;
                    }
                    $fallbackEmoji = true;
                    $params['sticker'] = self::inputSticker($item, 'f0', true);
                    $this->app->tg()->call('addStickerToSet', $params, 60, 30);
                }
            } catch (TelegramError $e) {
                if ($added === 0 && $throwIfNone) {
                    throw $e;
                }
                $this->app->log('pack', 'addStickerToSet stopped', ['set' => $name, 'added' => $added, 'err' => $e->getMessage()]);
                break;
            }
            $added++;
            $onUploaded($added);
        }
        return $added;
    }

    public function deleteSet(string $name): void
    {
        try {
            $this->app->tg()->call('deleteStickerSet', ['name' => $name]);
        } catch (TelegramError $e) {
            if (!str_contains($e->getMessage(), 'STICKERSET_INVALID')) {
                throw $e;
            }
        }
    }

    /** Converts Bot API errors into short Persian messages for users. */
    public static function friendlyError(\Throwable $e): string
    {
        $m = $e->getMessage();
        return match (true) {
            str_contains($m, 'PEER_ID_INVALID'), str_contains($m, 'user not found'), str_contains($m, 'USER_IS_BOT')
                => 'ابتدا ربات را استارت کنید و دوباره امتحان کنید.',
            str_contains($m, 'STICKERSET_INVALID') => 'این پک پیدا نشد یا حذف شده است.',
            str_contains($m, 'STICKERS_TOO_MUCH'), str_contains($m, 'too much') => 'ظرفیت این پک پر شده است (حداکثر ۲۰۰ ایموجی).',
            str_contains($m, 'Too Many Requests'), $e instanceof TelegramError && $e->getCode() === 429
                => 'تلگرام محدودیت موقت گذاشته است؛ چند دقیقه بعد دوباره امتحان کنید.',
            str_contains($m, 'ffmpeg'), str_contains($m, '64 KB') => 'ساخت ایموجی متحرک ناموفق بود.',
            default => 'ساخت پک ناموفق بود. لطفا دوباره تلاش کنید.',
        };
    }
}
