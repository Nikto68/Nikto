<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

/**
 * All user-facing bot texts (Persian). HTML parse mode — always escape user data with e().
 */
final class Texts
{
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Persian digits with thousands separators. */
    public static function num(int $n): string
    {
        return strtr(number_format($n), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '٬']);
    }

    public static function welcome(string $name, int $coins): string
    {
        return "سلام <b>" . self::e($name) . "</b> 👋\n\n"
            . "به ربات ساخت <b>ایموجی پریمیوم اختصاصی</b> خوش اومدی ✨\n\n"
            . "🖋 اسمت یا هر متنی که دوست داری رو بنویس\n"
            . "🎨 رنگ، فونت و قالب‌ها رو انتخاب کن\n"
            . "⚡️ در چند ثانیه پک ایموجی مخصوص خودت ساخته میشه!\n\n"
            . "💰 موجودی: <b>" . self::num($coins) . "</b> سکه\n\n"
            . "برای شروع روی دکمه <b>«🎨 ساخت ایموجی»</b> بزن 👇";
    }

    public static function help(): string
    {
        return "📖 <b>راهنما</b>\n\n"
            . "۱. روی «🎨 ساخت ایموجی» بزنید تا مینی‌اپ باز شود.\n"
            . "۲. متن دلخواه (حداکثر چند کلمه) را بنویسید.\n"
            . "۳. دو رنگ، فونت، اندازه و ارتفاع متن را تنظیم کنید.\n"
            . "۴. قالب‌ها را انتخاب کنید (یا «انتخاب همه») و دکمه ساخت را بزنید.\n"
            . "۵. بعد از ساخت، روی «افزودن به تلگرام» بزنید.\n\n"
            . "💡 ایموجی‌های سفارشی در پیام‌ها برای کاربران <b>تلگرام پریمیوم</b> قابل استفاده‌اند.\n"
            . "🗑 هر پکی را که ساخته‌اید از بخش «پک‌های من» می‌توانید حذف کنید.";
    }

    public static function packReady(string $title, int $count, bool $added): string
    {
        return ($added ? "✅ <b>" . self::num($count) . "</b> ایموجی به پک اضافه شد!\n\n" : "🎉 پک ایموجی شما آماده شد!\n\n")
            . "📦 <b>" . self::e($title) . "</b>\n"
            . "✨ تعداد: " . self::num($count) . " ایموجی\n\n"
            . "برای اضافه شدن به تلگرام روی دکمه زیر بزنید 👇";
    }

    public static function joinRequired(): string
    {
        return "🔒 برای استفاده از ربات ابتدا عضو کانال ما شوید، سپس روی «✅ عضو شدم» بزنید.";
    }

    public static function banned(): string
    {
        return '🚫 دسترسی شما به ربات مسدود شده است.';
    }

    public static function maintenance(): string
    {
        return '🛠 ربات در حال بروزرسانی است. لطفا کمی بعد دوباره تلاش کنید.';
    }

    public static function invite(string $link, int $bonus): string
    {
        return "👥 <b>دعوت دوستان</b>\n\n"
            . ($bonus > 0 ? "به ازای هر نفر که با لینک شما وارد ربات شود <b>" . self::num($bonus) . "</b> سکه هدیه می‌گیرید 🎁\n\n" : '')
            . "🔗 لینک اختصاصی شما:\n<code>" . self::e($link) . "</code>";
    }

    public static function hint(): string
    {
        return 'برای ساخت ایموجی روی دکمه زیر بزنید 👇';
    }
}
