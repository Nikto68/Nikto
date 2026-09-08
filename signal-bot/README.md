# Telegram Auto Signal Bot

ربات اسکن خودکار بازار کریپتو (Binance، MEXC، Wallex) که بر اساس اندیکاتورها،
نواحی Support/Resistance، Order Block و Fair Value Gap، سیگنال تولید و در
کانال‌های تلگرام منتشر می‌کند. کل سیستم به‌صورت یک **Worker/Daemon
طولانی‌مدت** روی ReactPHP اجرا می‌شود — **هیچ Cron Jobی در هیچ‌جای این پروژه
استفاده نشده است.**

> این پروژه کاملاً مستقل از فایل‌های دیگر ریشه ریپازیتوری (`admin_panel.php`،
> `bot_master_membership.php` و...) است و به آن‌ها هیچ وابستگی ندارد.

---

## معماری

```
Telegram
   │
   ▼
public/webhook.php  (تنها نقطه HTTP در کل پروژه)
   │
   ▼
Bot (Router)  ──▶  Command/Callback/State Handlers  ──▶  Database
                                                       └─▶ Settings

worker/market_worker.php   (Daemon — بدون Cron، دائمی)
   │
   ├── ExchangeManager ── Binance / MEXC / Wallex (REST + WebSocket)
   │                          │
   │                          ▼
   │                    Symbol Universe (Top-N بر اساس Volume/Liquidity/Spread)
   │                          │
   │                          ▼
   │                       Candles
   │                          │
   │                          ▼
   │                     Indicators (SMA/EMA/RSI/MACD/ATR/VWAP/BB/ADX/Stochastic)
   │                          │
   │                          ▼
   │              S/R Zones + Order Blocks + FVG
   │                          │
   │                          ▼
   │                   Strategy Engine ──▶ Confluence Engine ──▶ Signal Candidate
   │                          │
   │                          ▼
   │                   Signal Validator (symbol/liquidity/spread/freshness/
   │                                      score/duplicate/cooldown/SL-TP)
   │                          │
   │                          ▼
   │                      Signal Engine ──▶ Database (signals)
   │                          │
   │                          ▼  (فقط در حالت LIVE)
   │                        Queue  ──▶  Telegram Sender  ──▶  Channels
   │
   └── QueueWorkerRunner  (تخلیهٔ صف روی تایمر داخلی، بدون Cron)
```

جزئیات کامل‌تر معماری هر بخش، به‌صورت کامنت در بالای همان کلاس نوشته شده؛
این‌جا فقط نقشهٔ کلی است.

---

## ساختار پروژه

```
signal-bot/
├── public/webhook.php        تنها Entry Point تحت وب (Webhook تلگرام)
├── worker/
│   ├── market_worker.php     Daemon اصلی (اسکنر + استراتژی + سیگنال + صف)
│   └── queue_worker.php      Daemon اختیاری صف (جدا از worker اصلی)
├── bin/
│   ├── migrate.php           اجرای Migrationها
│   └── set-webhook.php       ثبت Webhook تلگرام
├── config/                   config.php, database.php, telegram.php, exchanges.php
├── database/
│   ├── migrations/           منبع اصلی Schema (به ترتیب اجرا می‌شوند)
│   └── schema.sql            نسخهٔ تجمیع‌شده (مستندسازی/نصب دستی)
├── src/
│   ├── Core/                 DI Container، Config، Exceptionهای پایه
│   ├── Database/             PDO Wrapper + Migration Runner + Repositoryها
│   ├── Logger/                Monolog چندکاناله + Redact خودکار Secretها
│   ├── Telegram/              Bot API Client، Entity/Premium Emoji، Quote/Reply
│   ├── Bot/                   Router + هر بخش پنل مدیریت (Admin/*)
│   ├── Exchange/               ExchangeInterface + Binance/MEXC/Wallex + WebSocket
│   ├── Market/                 SymbolManager، MarketScanner، CandleManager، ...
│   ├── Indicators/              IndicatorInterface + ۱۰ اندیکاتور آماده
│   ├── Strategy/                SupportResistance، OrderBlock، FVG، StrategyEngine
│   ├── Signal/                  Validator، Cooldown، Formatter، Engine
│   ├── Queue/                   Queue داخل دیتابیس + Jobها
│   └── Worker/                  اجراکنندهٔ اصلی روی Event Loop
├── storage/logs/               app/telegram/exchange/scanner/signal/error .log
└── deploy/                     نمونهٔ systemd، supervisor، nginx
```

---

## نصب

### پیش‌نیازها

- PHP ≥ 8.2 با اکستنشن‌های `pdo_mysql`, `curl`, `mbstring`, `json`, `pcntl`
- MySQL/MariaDB
- Composer

### مراحل

```bash
cd signal-bot
cp .env.example .env
# .env را ویرایش کنید: TELEGRAM_BOT_TOKEN، DB_*، و در صورت نیاز کلیدهای صرافی‌ها

composer install

php bin/migrate.php          # ساخت جدول‌ها + Seed تنظیمات پیش‌فرض
php bin/set-webhook.php https://your-domain.example.com/webhook.php
```

سپس Worker را اجرا کنید (برای توسعه مستقیم، برای Production بخش
[Production](#production-systemd--supervisor) را ببینید):

```bash
php worker/market_worker.php
```

> ⚠️ در محیط Sandbox این نشست، دسترسی به `codeload.github.com` توسط سیاست
> شبکه مسدود بود، بنابراین `composer install` کامل تست نشد. کد به‌صورت
> کامل نوشته و Syntax تک‌تک فایل‌ها بررسی شده، منطق پایگاه‌داده (Migration،
> Repositoryها، Cooldown، Queue) در برابر یک MariaDB واقعی تست شده،
> و فرمول‌های اندیکاتور/Zone/OrderBlock/FVG با داده‌های ساختگی صحت‌سنجی
> شده‌اند — اما اجرای end-to-end با `vendor/` واقعی روی این سرور انجام
> نشده. اولین اجرای `composer install` روی سرور واقعی شما را با دقت بیشتری
> دنبال کنید.

---

## تنظیمات (.env)

همهٔ متغیرهای لازم در `.env.example` مستند شده‌اند. مهم‌ترین‌ها:

| متغیر | توضیح |
|---|---|
| `TELEGRAM_BOT_TOKEN` | توکن ربات از BotFather |
| `TELEGRAM_WEBHOOK_SECRET` | یک رشتهٔ تصادفی طولانی (برای اعتبارسنجی Webhook) |
| `TELEGRAM_ADMIN_IDS` | آیدی عددی ادمین‌ها، با کاما جدا |
| `APP_MODE` | `LIVE` یا `DRY_RUN` — بعد از نصب هم از داخل ربات قابل تغییر است |
| `DB_*` | اتصال دیتابیس |
| `BINANCE_*` / `MEXC_*` / `WALLEX_*` | فعال/غیرفعال بودن هر صرافی، کلید API، آدرس REST/WS |

تنظیمات مربوط به اسکنر (`scan_symbol_limit`، `scan_interval_seconds`،
`min_volume`، ...) فقط مقدار **اولیه**شان از `.env` می‌آید؛ بعد از اولین
اجرا در جدول `bot_settings` ذخیره می‌شوند و از داخل پنل مدیریت ربات
(بخش «📊 اسکنر و سیگنال») قابل تغییرند، بدون نیاز به ری‌استارت.

---

## پنل مدیریت داخل تلگرام

با دستور `/admin` (فقط برای آیدی‌های داخل `TELEGRAM_ADMIN_IDS` یا جدول
`admins`) منوی کامل باز می‌شود:

- **📡 صرافی‌ها** — وضعیت زنده + فعال/غیرفعال کردن هر صرافی
- **📺 کانال‌ها** — افزودن با آیدی عددی یا یوزرنیم، بررسی خودکار دسترسی
  ربات، فیلتر حداقل امتیاز/جهت سیگنال per-channel
- **📊 اسکنر و سیگنال** — حالت LIVE/DRY_RUN، آستانه‌های حجم/نقدشوندگی/اسپرد،
  حداقل امتیاز، کول‌داون، تایم‌فریم‌های پیش‌فرض
- **📈 اندیکاتورها** — فهرست اندیکاتورهای آماده (فقط نمایشی؛ پارامترها در
  Config هر استراتژی تنظیم می‌شود)
- **🧠 استراتژی‌ها** — فعال/غیرفعال کردن (تا زمانی که استراتژی واقعی اضافه
  نشده، این فهرست خالی است — به بخش زیر مراجعه کنید)
- **📝 قالب‌های سیگنال** — ویرایش متن سیگنال با حفظ کامل Premium Emoji و
  سایر Entityهای تلگرام
- **✏️ متن‌های ربات** — ویرایش هر متنی که ربات می‌فرستد
- **🧪 تست سیگنال** — اجرای واقعی کل Pipeline روی یک نماد دلخواه بدون ارسال
  واقعی سیگنال
- **📜 تاریخچه سیگنال‌ها**، **📋 لاگ‌ها**، **❤️ سلامت سیستم**

---

## استراتژی فعال: SMC Confluence

یک استراتژی واقعی فعال است: **`smc_confluence`** (`src/Strategy/SmcStrategy.php`)،
ترجمهٔ مستقیم اندیکاتور ترکیبی SMC که خودتان دادید (S/R چندتایم‌فریمی +
Market Structure/BOS-CHoCH + Liquidity خرید/فروش + Order Block/Supply-Demand).
منطق دقیق:

- **جهت معامله**: از آخرین شکست ساختار (BOS/CHoCH) در تایم‌فریم 4H
  (`src/Strategy/Smc/SwingStructure.php`) — صعودی=LONG، نزولی=SHORT.
- **ماشهٔ سیگنال**: فقط وقتی قیمت روی یک ناحیهٔ Order Block/Supply-Demand
  فعال (تشخیص‌داده‌شده در 4H یا 1H، `src/Strategy/Smc/SmcZone.php`) و
  هم‌جهت با روند باشد.
- **Entry/SL/TP**: Entry روی لبهٔ نزدیک ناحیه، SL کمی پشت لبهٔ دور (بافر
  بر اساس ATR)، TP1/2/3 در سطوح بعدی Liquidity (`LiquidityZones.php`) یا
  S/R چندتایم‌فریمی (1D/1W) در جهت معامله — دقیقاً طبق دستور شما.
- **Confluence Factorها**: `order_block`، `trend`، `structure_alignment`
  (هم‌جهتی 1H با 4H)، `liquidity` (Sweep اخیر Liquidity مخالف)، `support`/
  `resistance` (هم‌پوشانی با سطح 1D/1W) — وزن هرکدام در جدول `strategies`
  (فیلد `config.weights`) تنظیم شده و از پنل مدیریت قابل تغییر است، نه
  Hard-Code.

منطق با دادهٔ ساختگی برای هر بخش (تشخیص BOS/CHoCH، خوشه‌بندی Liquidity و
Sweep، فیلتر ATR روی Order Block، محاسبهٔ Entry/SL/TP و امتیازدهی
Confluence) تک‌تک تست و تأیید شده است.

⚠️ استراتژی به‌صورت پیش‌فرض **فعال** است ولی چون `APP_MODE` پیش‌فرض
`DRY_RUN` است، تا وقتی خودتان از پنل مدیریت آن را به `LIVE` نبرید، هیچ
سیگنالی واقعاً به کانالی ارسال نمی‌شود — فقط در دیتابیس ثبت می‌شود (قابل
مشاهده در «📜 تاریخچه سیگنال‌ها» و «🧪 تست سیگنال»).

## اضافه کردن یک استراتژی دیگر

برای افزودن یک استراتژی واقعی دیگر (در کنار `smc_confluence`، نه لزوماً
جایگزین آن):

1. `src/Strategy/ExampleStrategy.php` را کپی کنید و کلاسش را rename کنید.
   این فایل فقط یک Template است و همیشه `null` برمی‌گرداند — هرگز به‌عنوان
   استراتژی واقعی استفاده نشود.
2. در متد `evaluate()`، شرایط واقعی را با `StrategyContext` (کندل‌های
   چند-تایم‌فریم، مقادیر اندیکاتور، Zoneها، Order Blockها، FVGها) پیاده
   کنید و در صورت برقرار بودن شرایط یک `StrategyResult` برگردانید — شامل
   جهت، Entry/SL/TP و لیست `confluenceFactors` (نام‌های دلخواه مثل
   `order_block`, `fvg`, `trend`).
3. در `src/Bootstrap/StrategyServiceProvider.php`، داخل `StrategyRegistry`
   نمونهٔ کلاس جدید را `register()` کنید.
4. با `StrategyRepository::upsert()` یک ردیف در جدول `strategies` بسازید
   (یا از پنل مدیریت، بخش استراتژی‌ها، فعالش کنید) — شامل `weights`،
   `required_confluences` و `min_score` داخل فیلد `config`.

از این به بعد `StrategyEngine` آن را روی هر نماد در هر چرخهٔ اسکن اجرا
می‌کند و `ConfluenceEngine` امتیازدهی می‌کند — دقیقاً طبق همان وزن‌هایی که
شما در `config` مشخص کرده‌اید، نه عددی Hard-Code شده.

---

## اضافه کردن صرافی جدید

هیچ کد بیرون از `src/Exchange/` صرافی‌ها را نمی‌شناسد. برای افزودن صرافی
جدید:

1. کلاسی بسازید که `ExchangeInterface` را پیاده‌سازی کند (بهترین نمونه:
   `src/Exchange/Binance.php`).
2. یک entry به `config/exchanges.php` اضافه کنید (adapter class + ENV
   Variableهای مربوطه).
3. یک ردیف در `exchanges` (جدول) اضافه کنید — یا با یک Migration کوچک،
   یا دستی.

هیچ تغییری در `MarketScanner`، `StrategyEngine` یا هر بخش دیگر لازم نیست.

---

## Dry Run در برابر Live

```
APP_MODE=DRY_RUN   (پیش‌فرض)   → کل Pipeline اجرا و در دیتابیس ثبت می‌شود،
                                   ولی چیزی به کانال ارسال نمی‌شود.
APP_MODE=LIVE                  → سیگنال‌های معتبر واقعاً به کانال‌های
                                   مطابق فیلتر ارسال می‌شوند.
```

تغییر حالت از پنل مدیریت (بخش «📊 اسکنر و سیگنال») فوری اعمال می‌شود، بدون
نیاز به ری‌استارت Worker.

---

## Production — systemd یا Supervisor

نمونه‌های آماده در `deploy/`:

```bash
sudo cp deploy/systemd/signal-bot-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now signal-bot-worker
```

یا با Supervisor:

```bash
sudo cp deploy/supervisor/signal-bot.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start signal-bot:*
```

`worker/queue_worker.php` اختیاری است — `market_worker.php` خودش صف را
هم روی تایمر داخلی تخلیه می‌کند؛ آن را جدا اجرا کنید فقط اگر حجم سیگنال/
کانال‌ها بالا رفت و خواستید تخلیهٔ صف مستقل مقیاس بگیرد.

Webhook تلگرام (`public/webhook.php`) زیر nginx + php-fpm سرو می‌شود —
نمونه در `deploy/nginx/signal-bot.conf`.

**هیچ Cron Jobی لازم نیست.** فقط این دو (یا سه) Process باید همیشه در حال
اجرا باشند و systemd/Supervisor آن‌ها را در صورت Crash خودکار Restart کند.

---

## امنیت

- هیچ Secretی در کد یا Git نیست؛ همه در `.env` (که `.gitignore` شده).
- Webhook با `X-Telegram-Bot-Api-Secret-Token` اعتبارسنجی می‌شود
  (`hash_equals`، مقاوم در برابر Timing Attack).
- همهٔ Queryها Prepared Statement هستند؛ هیچ رشتهٔ ورودی کاربر مستقیم در
  SQL Concatenate نمی‌شود.
- توکن‌ها/رمزها/کلیدهای API هرگز در هیچ‌کدام از `storage/logs/*.log`
  نوشته نمی‌شوند (`SecretRedactor` آن‌ها را قبل از نوشتن جایگزین می‌کند).
- دسترسی پنل مدیریت فقط برای آیدی‌های داخل جدول `admins` (Seed شده از
  `TELEGRAM_ADMIN_IDS`).

---

## محدودیت‌های شناخته‌شده

- **WebSocket واقعی فقط برای Binance** پیاده شده (Stream ترکیبی JSON
  رسمی و پایدار). برای MEXC و Wallex، به‌جای حدس‌زدن یک پروتکل WS که در
  این محیط قابل تست نبود، از همان معماری WebSocket (`ExchangeWebSocketInterface`)
  با یک پیاده‌سازی REST-Polling استفاده شده — کاملاً مطابق مسیر Fallback
  مشخص‌شده در طراحی اولیه. افزودن WS واقعی برای این دو بعداً فقط یک کلاس
  جدید در `src/Exchange/WebSocket/` است.
- فیلدهای دقیق پاسخ Wallex (نام‌گذاری JSON) بر اساس مستندات عمومی نوشته
  شده و پیش از استفادهٔ Live باید در برابر API فعلی Wallex Verify شود —
  در کامنت بالای `src/Exchange/Wallex.php` مشخص شده.
- استراتژی `smc_confluence` بر اساس منطق قابل‌استخراج از Pine Script
  (سوئینگ/BOS/CHoCH، خوشه‌بندی Liquidity، فیلتر ATR روی Order Block) نوشته
  شده، نه اجرای خط‌به‌خط همان اسکریپت (که کلاً بصری/برای TradingView است).
  آستانه‌ها (ضریب ATR، حداقل تعداد Touch برای Liquidity Pool، طول Swing و
  ...) پیش‌فرض‌های مستند و منطقی‌اند اما مثل هر استراتژی جدید باید قبل از
  فعال کردن حالت LIVE، حتماً با «🧪 تست سیگنال» روی چند نماد/دوره بررسی و
  در صورت نیاز از طریق `strategies.config` تنظیم شوند.
- `composer install` در محیط توسعهٔ این نشست به‌خاطر مسدود بودن
  `codeload.github.com` تکمیل نشد؛ باید در سرور واقعی اجرا شود.

---

## Logging

```
storage/logs/app.log        همه‌چیز (سطح LOG_LEVEL به بالا)
storage/logs/telegram.log   تعامل با Bot API
storage/logs/exchange.log   REST/WebSocket صرافی‌ها
storage/logs/scanner.log    چرخهٔ اسکن، تشخیص Zone/OB/FVG
storage/logs/signal.log     Validation، Cooldown، ارسال به کانال
storage/logs/error.log      همهٔ خطاهای ERROR+ از تمام کانال‌های بالا
```

خطاهای ERROR+ همچنین در جدول `logs` هم Mirror می‌شوند (برای بخش
«📋 لاگ‌ها» و «❤️ سلامت سیستم» داخل ربات، بدون نیاز به SSH).
