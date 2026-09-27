<?php
declare(strict_types=1);

namespace EmojiBot;

use Throwable;

/**
 * One-time (idempotent) installation: checks the server and registers webhook, commands and menu button.
 */
final class Setup
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return string[] report lines */
    public function run(bool $webhook = true): array
    {
        $app = $this->app;
        $r = [];
        $ok = fn (bool $cond, string $good, string $bad) => $r[] = ($cond ? '✅ ' . $good : '❌ ' . $bad);

        $ok(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION, 'PHP 8.1+ لازم است (فعلی: ' . PHP_VERSION . ')');
        $gd = function_exists('gd_info') ? gd_info() : [];
        $ok(!empty($gd['FreeType Support']), 'GD + FreeType', 'افزونه GD با FreeType نصب نیست');
        $ok(function_exists('curl_init'), 'cURL', 'افزونه cURL نصب نیست');
        $ok(function_exists('mb_strlen'), 'mbstring', 'افزونه mbstring نصب نیست');
        $ok(is_writable($app->storage()), 'پوشه storage قابل نوشتن است', 'پوشه storage قابل نوشتن نیست');
        $video = $app->video()->available();
        $r[] = $video ? '✅ ffmpeg + libvpx-vp9 (ایموجی متحرک فعال)' : '⚠️ ffmpeg پیدا نشد — فقط ایموجی ثابت ساخته می‌شود';

        try {
            $app->db();
            $r[] = '✅ دیتابیس (' . $app->db()->driver . ')';
        } catch (Throwable $e) {
            $r[] = '❌ دیتابیس: ' . $e->getMessage();
            return $r;
        }

        try {
            $me = $app->tg()->call('getMe');
            $app->settings()->set('bot_username', (string) $me['username']);
            $app->settings()->set('bot_name', (string) $me['first_name']);
            $r[] = '✅ ربات: @' . $me['username'];
        } catch (Throwable $e) {
            $r[] = '❌ توکن ربات: ' . $e->getMessage();
            return $r;
        }

        $tg = $app->tg();
        $steps = [
            'دستورات' => fn () => $tg->call('setMyCommands', ['commands' => [
                ['command' => 'start', 'description' => 'شروع و ساخت ایموجی'],
                ['command' => 'packs', 'description' => 'پک‌های من'],
                ['command' => 'help', 'description' => 'راهنما'],
            ]]),
            'منوی ربات (دکمه مینی‌اپ)' => fn () => $tg->call('setChatMenuButton', ['menu_button' => [
                'type' => 'web_app',
                'text' => '🎨 ساخت ایموجی',
                'web_app' => ['url' => $app->appUrl()],
            ]]),
            'توضیحات ربات' => function () use ($tg) {
                $tg->call('setMyShortDescription', ['short_description' => 'ساخت پک ایموجی پریمیوم اختصاصی با اسم و متن دلخواه شما ✨']);
                $tg->call('setMyDescription', ['description' => "✨ پک ایموجی پریمیوم اختصاصی بساز!\n\nاسمت یا هر متنی رو بنویس، رنگ و قالب رو انتخاب کن و در چند ثانیه پک ایموجی مخصوص خودت رو داشته باش.\n\nبرای شروع /start رو بزن 👇"]);
            },
        ];
        foreach ($app->adminIds() as $adminId) {
            $steps["دستورات مدیر ($adminId)"] = fn () => $tg->call('setMyCommands', [
                'commands' => [
                    ['command' => 'start', 'description' => 'شروع'],
                    ['command' => 'admin', 'description' => 'پنل مدیریت'],
                    ['command' => 'packs', 'description' => 'پک‌های من'],
                    ['command' => 'cancel', 'description' => 'لغو عملیات'],
                ],
                'scope' => ['type' => 'chat', 'chat_id' => $adminId],
            ]);
        }
        if ($webhook) {
            $steps['وبهوک'] = fn () => $tg->call('setWebhook', [
                'url' => $app->baseUrl() . '/webhook.php',
                'secret_token' => $app->webhookSecret(),
                'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query', 'my_chat_member'],
                'max_connections' => 40,
            ]);
        }
        foreach ($steps as $name => $fn) {
            try {
                $fn();
                $r[] = "✅ $name";
            } catch (Throwable $e) {
                // Admins that never started the bot make the scoped command call fail; not fatal.
                $r[] = "⚠️ $name: " . $e->getMessage();
            }
        }
        if ($webhook) {
            try {
                $info = $tg->call('getWebhookInfo');
                $r[] = 'ℹ️ Webhook: ' . ($info['url'] ?? '') . (!empty($info['last_error_message']) ? ' — آخرین خطا: ' . $info['last_error_message'] : '');
            } catch (Throwable) {
            }
        }
        $r[] = '';
        $r[] = 'مینی‌اپ: ' . $app->appUrl();
        $r[] = 'آدرس cron (هر دقیقه): ' . $app->baseUrl() . '/cron.php?key=' . $app->urlKey('cron');
        $r[] = 'در BotFather ‹Configure Mini App› را هم روی همین آدرس مینی‌اپ تنظیم کنید تا دکمه Open بالای پروفایل ربات بیاید.';
        return $r;
    }
}
