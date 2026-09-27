<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

/**
 * User-facing bot texts (English). HTML parse mode — always escape user data with e().
 * The admin panel (Admin.php) keeps its own Persian texts.
 */
final class Texts
{
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function num(int $n): string
    {
        return number_format($n);
    }

    public static function welcome(string $name, int $coins): string
    {
        return "Hi <b>" . self::e($name) . "</b> 👋\n\n"
            . "Welcome to the <b>custom premium emoji</b> maker ✨\n\n"
            . "🖋 Type your name or any text (or upload your logo)\n"
            . "🎨 Pick colors, a font and your favorite templates\n"
            . "⚡️ Get your own animated emoji pack in seconds!\n\n"
            . "💰 Balance: <b>" . self::num($coins) . "</b> coins\n\n"
            . "Tap <b>🎨 Create emoji</b> to start 👇";
    }

    public static function help(): string
    {
        return "📖 <b>How it works</b>\n\n"
            . "1. Tap <b>🎨 Create emoji</b> to open the app.\n"
            . "2. Type your text in English (e.g. your name), or switch to <b>Logo</b> and upload your logo.\n"
            . "3. Choose two colors, a font, size and height.\n"
            . "4. Select templates (or <b>Select all</b>) and tap <b>Create pack</b>.\n"
            . "5. When it's ready, tap <b>Add to Telegram</b>.\n\n"
            . "✨ All trending templates are animated; the 🎨 button paints the templates in your color too.\n"
            . "💡 Custom emoji can be used in messages by <b>Telegram Premium</b> users.\n"
            . "🗑 You can delete your packs any time from <b>My packs</b>.";
    }

    public static function packReady(string $title, int $count, bool $added): string
    {
        return ($added ? "✅ <b>" . self::num($count) . "</b> emoji added to your pack!\n\n" : "🎉 Your emoji pack is ready!\n\n")
            . "📦 <b>" . self::e($title) . "</b>\n"
            . "✨ Emoji: " . self::num($count) . "\n\n"
            . "Tap the button below to add it to Telegram 👇";
    }

    public static function joinRequired(): string
    {
        return "🔒 Please join our channel to use the bot, then tap <b>✅ I've joined</b>.";
    }

    public static function banned(): string
    {
        return '🚫 Your access to this bot has been blocked.';
    }

    public static function maintenance(): string
    {
        return "🛠 We're updating the bot. Please try again in a little while.";
    }

    public static function invite(string $link, int $bonus): string
    {
        return "👥 <b>Invite friends</b>\n\n"
            . ($bonus > 0 ? "Get <b>" . self::num($bonus) . "</b> coins for every friend who starts the bot with your link 🎁\n\n" : '')
            . "🔗 Your personal link:\n<code>" . self::e($link) . "</code>";
    }

    public static function hint(): string
    {
        return 'Tap the button below to create your emoji 👇';
    }
}
