<?php
/**
 * 🎨 نمای سایتِ فروشگاه — همه‌ی HTML/CSS/JSِ صفحه‌ی اصلی (index.php)
 *
 * ظاهر: شیشه‌ای (glassmorphism) رویِ یک پس‌زمینه‌ی زنده — شفقِ رنگیِ
 * آرام‌جنبان، آسمانِ پرستاره با شهاب (بوم/canvas)، درخششی که دنبالِ
 * نشانگر می‌آید، و نورِ «اسپات‌لایت» رویِ هر کارت شیشه‌ای.
 *
 * قاعده‌های این فایل:
 *   • صفحه سمتِ سرور ساخته می‌شود — موتورِ جستجو همه‌ی قیمت‌ها را می‌بیند
 *     و صفحه بدونِ JS هم کامل است. JS برای خرید، ماشین‌حساب، فیلتر و افکت‌هاست.
 *   • هر داده‌ای که از پنل می‌آید از siteE رد می‌شود؛ JS هم فقط با
 *     textContent می‌نویسد، هرگز innerHTML.
 *   • سرعت: blur (backdrop-filter) فقط رویِ چند سطحِ ثابت — هدر، پنجره‌ها،
 *     هیرو و ماشین‌حساب‌ها. بقیه‌ی کارت‌ها نیمه‌شفاف‌اند و چون پس‌زمینه
 *     خودش نرم و محو است، همان حسِ شیشه را بی‌هزینه می‌دهند. انیمیشن‌ها
 *     فقط transform/opacity، بوم وقتِ پنهان‌بودنِ تب می‌ایستد، و با
 *     prefers-reduced-motion همه‌چیز ساکن می‌شود.
 */

function siteE($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** عدد با رقم فارسی و جداکننده‌ی هزارگان — ۱۲۳٬۴۵۶ */
function siteFa($n, $dec = 0) {
    $s = number_format((float)$n, $dec, '.', ',');
    if ($dec > 0 && str_contains($s, '.')) $s = rtrim(rtrim($s, '0'), '.');
    return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                      '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
                      ',' => '٬', '.' => '٫']);
}

/** قیمتِ خوانا — صفر یعنی قیمت را ربات بعد از پرسیدن می‌دهد */
function siteMoney($n, $cur = 'تومان') {
    if ((float)$n <= 0) return '<span class="ask">استعلام قیمت</span>';
    $dec = in_array($cur, ['تومان', 'ریال'], true) ? 0 : 2;
    return '<b class="amt">' . siteFa($n, $dec) . '</b> <small class="cur">' . siteE($cur) . '</small>';
}

function siteMoneyText($n, $cur = 'تومان') {
    if ((float)$n <= 0) return 'استعلام قیمت';
    $dec = in_array($cur, ['تومان', 'ریال'], true) ? 0 : 2;
    return siteFa($n, $dec) . ' ' . $cur;
}

function siteIcon($id, $cls = '') {
    return '<svg class="ic' . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#i-' . $id . '"/></svg>';
}

/**
 * ویژگی‌های دکمه‌ی خرید. data-item (کلیدِ «app:id») یعنی همین‌جا خریده
 * می‌شود؛ نبودش یعنی خریدش داخلِ ربات است و پنجره فقط راهِ ربات را نشان می‌دهد.
 */
function siteBuy($k, $name, $priceText, $need) {
    return ($k !== '' ? 'data-item="' . siteE($k) . '" ' : '') .
           'data-buy data-name="' . siteE($name) . '" data-price="' . siteE($priceText) .
           '" data-need="' . siteE($need) . '"';
}

function siteBotUrl($d) {
    return $d['bot'] !== '' ? 'https://t.me/' . rawurlencode($d['bot']) . '?start=site' : '';
}

function siteHeaders() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com data:; " .
        "img-src 'self' data:; connect-src 'self'; " .
        "base-uri 'none'; form-action 'none'; frame-ancestors 'self'; object-src 'none'"
    );
}

function siteKindLabel($k) {
    return ['follow' => 'فالوور و ممبر', 'like' => 'لایک و ری‌اکشن', 'view' => 'بازدید',
            'comment' => 'کامنت', 'other' => 'سایر'][$k] ?? 'سایر';
}
function siteKindIcon($k) {
    return ['follow' => 'users', 'like' => 'heart', 'view' => 'eye', 'comment' => 'chat', 'other' => 'spark'][$k] ?? 'spark';
}

/** یک کارتِ خدمتِ رشد (اینستاگرام / کانال تلگرام) */
function siteOfferCard($o, $platform) {
    $perTxt = $o['per'] > 1 ? 'هر ' . siteFa($o['per']) . ' ' . ($o['unit'] !== '' ? $o['unit'] : 'عدد')
                            : ($o['unit'] !== '' ? 'هر ۱ ' . $o['unit'] : '');
    $need = $platform === 'ig' ? 'لینک پیج یا پست اینستاگرام' : 'لینک پست، استوری یا کانال';
    if ($o['qty']) $need .= ' + تعداد';
    $priceTxt = siteMoneyText($o['price'], $o['cur']) . ($perTxt !== '' && $o['price'] > 0 ? ' — ' . $perTxt : '');
    ob_start(); ?>
    <article class="offer g lift rv" data-kind="<?= siteE($o['kind']) ?>">
      <div class="offer-top">
        <span class="orb <?= $platform === 'ig' ? 'o-ig' : 'o-tg' ?>"><?= siteIcon(siteKindIcon($o['kind'])) ?></span>
        <?php if ($o['badge'] !== ''): ?><span class="tag"><?= siteE($o['badge']) ?></span><?php endif; ?>
      </div>
      <h3><?= siteE($o['name']) ?></h3>
      <?php if ($o['desc'] !== ''): ?><p class="muted"><?= siteE($o['desc']) ?></p><?php endif; ?>
      <div class="offer-price">
        <?= siteMoney($o['price'], $o['cur']) ?>
        <?php if ($perTxt !== '' && $o['price'] > 0): ?><span class="per"><?= siteE($perTxt) ?></span><?php endif; ?>
      </div>
      <?php if ($o['qty'] && $o['min'] > 0): ?>
        <div class="offer-range"><?= siteIcon('tag') ?> حداقل <?= siteFa($o['min']) ?><?php if ($o['max'] > $o['min']): ?> · حداکثر <?= siteFa($o['max']) ?><?php endif; ?></div>
      <?php endif; ?>
      <button type="button" class="btn btn-glass btn-block" <?= siteBuy($o['k'], $o['name'], $priceTxt, $need) ?>><?= $o['k'] !== '' ? 'خرید' : 'سفارش در ربات' ?> <?= siteIcon('arrow') ?></button>
    </article>
    <?php return ob_get_clean();
}

function siteView($d) {
    $bot     = siteBotUrl($d);
    $name    = (string)$d['name'];
    $support = $d['support'] !== '' ? 'https://t.me/' . rawurlencode($d['support']) : $bot;
    $channel = $d['channel'] !== '' ? 'https://t.me/' . rawurlencode($d['channel']) : '';
    $shop    = !empty($d['shop']);

    // ── چیزهایی که هیرو و نوارِ قیمت نشان می‌دهند — همه از کاتالوگِ واقعی ──
    $heroStar = null;
    foreach ($d['stars'] as $s) if ($s['qty'] === 500) $heroStar = $s;
    if (!$heroStar && $d['stars']) $heroStar = $d['stars'][intdiv(count($d['stars']), 2)];
    $heroPrem = null;
    foreach ($d['premium'] as $p) if (!$heroPrem || $p['months'] > $heroPrem['months']) $heroPrem = $p;
    $heroGift = null;
    foreach ($d['gifts'] as $g) if ($g['price'] > 0) { $heroGift = $g; break; }
    $heroCtry = $d['countries'][0] ?? null;

    $premMin = 0.0;
    foreach ($d['premium'] as $p) if ($p['price'] > 0 && ($premMin <= 0 || $p['price'] < $premMin)) $premMin = $p['price'];
    $giftMin = 0.0;
    foreach ($d['gifts'] as $g) if ($g['price'] > 0 && ($giftMin <= 0 || $g['price'] < $giftMin)) $giftMin = $g['price'];
    $igMin = 0.0;
    foreach ($d['insta'] as $o) if ($o['price'] > 0 && ($igMin <= 0 || $o['price'] < $igMin)) $igMin = $o['price'];
    $grMin = 0.0;
    foreach ($d['tgrowth'] as $o) if ($o['price'] > 0 && ($grMin <= 0 || $o['price'] < $grMin)) $grMin = $o['price'];
    $starFrom = $d['star_unit'];
    if ($starFrom <= 0) foreach ($d['stars'] as $s) if ($s['qty'] > 0 && $s['price'] > 0) {
        $u = $s['price'] / $s['qty'];
        if ($starFrom <= 0 || $u < $starFrom) $starFrom = $u;
    }

    $ticker = [];
    if ($starFrom > 0) $ticker[] = ['star', 'هر ۱ استارز', siteMoneyText($starFrom)];
    foreach ($d['premium'] as $p) $ticker[] = ['crown', $p['name'], siteMoneyText($p['price'])];
    foreach ($d['coins'] as $c) $ticker[] = ['coin', $c['name'], siteMoneyText($c['price'])];
    if ($d['num_from'] > 0) $ticker[] = ['phone', 'شماره مجازی از', siteMoneyText($d['num_from'])];
    if ($giftMin > 0) $ticker[] = ['gift', 'گیفت تلگرام از', siteMoneyText($giftMin)];
    if ($igMin > 0) $ticker[] = ['insta', 'خدمات اینستاگرام از', siteMoneyText($igMin)];

    $hasStars = $d['stars'] || $d['star_unit'] > 0;
    $igKinds = [];
    foreach ($d['insta'] as $o) $igKinds[$o['kind']] = true;
    $grKinds = [];
    foreach ($d['tgrowth'] as $o) $grKinds[$o['kind']] = true;
    $igQty = array_values(array_filter($d['insta'], fn($o) => $o['qty'] && $o['unit_price'] > 0));

    $services = 0;
    foreach (['stars', 'premium', 'gifts', 'insta', 'tgrowth'] as $k) $services += count($d[$k]);
    $services += ($d['star_unit'] > 0 ? 1 : 0) + (int)$d['num_total'];

    $nav = [];
    if ($hasStars)       $nav[] = ['#stars', 'استارز'];
    if ($d['premium'])   $nav[] = ['#premium', 'پریمیوم'];
    if ($d['gifts'])     $nav[] = ['#gifts', 'گیفت'];
    if ($d['countries']) $nav[] = ['#numbers', 'شماره مجازی'];
    $nav[] = ['#instagram', 'اینستاگرام'];
    if ($d['tgrowth'])   $nav[] = ['#growth', 'رشد کانال'];
    $nav[] = ['#faq', 'سوالات'];

    $words = ['استارز'];
    if ($d['premium'])   $words[] = 'پریمیوم';
    if ($d['gifts'])     $words[] = 'گیفت';
    if ($d['countries']) $words[] = 'شماره مجازی';
    $words[] = 'فالوور';

    $tgNote = 'سفارش بعد از تایید پرداخت، خودکار پردازش و تحویل می‌شود.';
    // سالِ شمسی — نوروز حدودِ ۲۱ مارس است؛ یک روز جابه‌جایی برای سالِ کپی‌رایت مهم نیست
    $gy = (int)date('Y');
    $year = siteFa((int)date('n') > 3 || ((int)date('n') === 3 && (int)date('j') >= 21) ? $gy - 621 : $gy - 622);
    $desc = 'خرید مستقیم استارز تلگرام، پریمیوم، گیفت، شماره مجازی و فالوور اینستاگرام با قیمت لحظه‌ای، پرداخت امن و تحویل خودکار — ' . $name;

    // 🧠 داده‌ی پنجره‌ی خرید — فقط نمایشی؛ سرور همه‌چیز را از نو حساب می‌کند
    $boot = [
        'shop'  => $shop,
        'bot'   => $bot,
        'items' => $shop ? $d['items'] : new stdClass(),
        'pay'   => $d['pay'],
    ];
    $bootJson = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    ob_start(); ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= siteE($name) ?> — خرید استارز، پریمیوم، گیفت تلگرام و شماره مجازی</title>
<meta name="description" content="<?= siteE($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= siteE($name) ?> — فروشگاه خدمات تلگرام و اینستاگرام">
<meta property="og:description" content="<?= siteE($desc) ?>">
<meta name="theme-color" content="#05060F">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#38BDF8"/><stop offset=".55" stop-color="#6366F1"/><stop offset="1" stop-color="#A855F7"/></linearGradient></defs><rect width="64" height="64" rx="18" fill="url(#g)"/><path d="M32 13l5.6 11.4 12.6 1.8-9.1 8.9 2.1 12.5L32 41.7l-11.2 5.9 2.1-12.5-9.1-8.9 12.6-1.8z" fill="#fff"/></svg>') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<script>
(function(){var t=null;try{t=localStorage.getItem('site-theme')}catch(e){}
document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');
document.documentElement.classList.add('js');})();
</script>
<style>
@property --ang{syntax:'<angle>';inherits:false;initial-value:0deg}
:root,:root[data-theme=dark]{
  --bg0:#04050D; --bg1:#0A0D24;
  --glass:rgba(255,255,255,.045); --glass2:rgba(255,255,255,.085); --glass3:rgba(255,255,255,.12);
  --line:rgba(255,255,255,.10); --line2:rgba(255,255,255,.18); --hi:rgba(255,255,255,.22);
  --ink:#F4F6FF; --ink2:#CDD3EE; --dim:#8F98BD;
  --field:rgba(255,255,255,.06);
  --sh:0 24px 60px -30px rgba(0,0,0,.85); --sh2:0 40px 90px -40px rgba(0,0,0,.95);
  --aur-o:.9; --aur-blend:screen; --grid:rgba(255,255,255,.05); --noise:.06;
  --star:255,255,255; --glow:rgba(120,140,255,.16);
  color-scheme:dark;
}
:root[data-theme=light]{
  --bg0:#EEF1FB; --bg1:#F8F9FE;
  --glass:rgba(255,255,255,.55); --glass2:rgba(255,255,255,.75); --glass3:rgba(255,255,255,.9);
  --line:rgba(30,40,90,.09); --line2:rgba(30,40,90,.16); --hi:rgba(255,255,255,.95);
  --ink:#0A0F26; --ink2:#29314F; --dim:#5E6887;
  --field:rgba(255,255,255,.7);
  --sh:0 20px 50px -28px rgba(40,50,110,.35); --sh2:0 34px 80px -36px rgba(40,50,110,.45);
  --aur-o:.55; --aur-blend:normal; --grid:rgba(30,40,90,.06); --noise:.035;
  --star:70,80,160; --glow:rgba(99,102,241,.10);
  color-scheme:light;
}
:root{
  --sky:#38BDF8; --blue:#3B82F6; --indigo:#6366F1; --violet:#A855F7; --pink:#EC4899; --gold:#FBBF24; --amber:#F97316; --green:#22C55E; --teal:#14B8A6; --red:#F43F5E;
  --grad:linear-gradient(135deg,#38BDF8 0%,#6366F1 52%,#A855F7 100%);
  --gold-g:linear-gradient(135deg,#FDE68A 0%,#FBBF24 40%,#F97316 100%);
  --vio-g:linear-gradient(135deg,#C4B5FD 0%,#A855F7 45%,#6366F1 100%);
  --pink-g:linear-gradient(135deg,#FDA4AF 0%,#EC4899 50%,#A855F7 100%);
  --grn-g:linear-gradient(135deg,#6EE7B7 0%,#22C55E 50%,#0D9488 100%);
  --ig:linear-gradient(45deg,#F58529 0%,#DD2A7B 40%,#8134AF 75%,#515BD4 100%);
  --r:20px; --r2:28px;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{background:var(--bg0);color:var(--ink);font-family:Vazirmatn,Vazir,'IRANSans',system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;
  font-size:15.5px;line-height:1.85;overflow-x:hidden;-webkit-font-smoothing:antialiased;min-height:100vh}
a{color:inherit;text-decoration:none}
button,input,select,textarea{font:inherit;color:inherit}
img,svg{display:block}
.ic{width:1.15em;height:1.15em;flex:none;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.wrap{width:100%;max-width:1240px;margin:0 auto;padding:0 20px}
.muted{color:var(--dim)}
.gt{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-gold{background:var(--gold-g);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-ig{background:var(--ig);-webkit-background-clip:text;background-clip:text;color:transparent}
.shine{background:linear-gradient(90deg,#60A5FA,#A78BFA,#F472B6,#FBBF24,#34D399,#60A5FA);background-size:300% 100%;
  -webkit-background-clip:text;background-clip:text;color:transparent;animation:hue 9s linear infinite}
@keyframes hue{to{background-position:300% 0}}
:focus-visible{outline:2px solid var(--sky);outline-offset:3px;border-radius:10px}
::selection{background:rgba(99,102,241,.4)}
[hidden]{display:none!important}

/* ═════════ پس‌زمینه‌ی زنده ═════════ */
.bg{position:fixed;inset:0;z-index:-1;overflow:hidden;background:radial-gradient(120% 90% at 50% 0%,var(--bg1),var(--bg0) 70%)}
.aur{position:absolute;width:64vmax;height:64vmax;border-radius:50%;opacity:var(--aur-o);mix-blend-mode:var(--aur-blend);will-change:transform}
.a1{background:radial-gradient(closest-side,rgba(124,58,237,.55),transparent);top:-22vmax;right:-14vmax;animation:d1 28s ease-in-out infinite alternate}
.a2{background:radial-gradient(closest-side,rgba(37,99,235,.5),transparent);top:10vmax;left:-24vmax;animation:d2 34s ease-in-out infinite alternate}
.a3{background:radial-gradient(closest-side,rgba(6,182,212,.36),transparent);bottom:-30vmax;right:4vmax;animation:d3 30s ease-in-out infinite alternate}
.a4{background:radial-gradient(closest-side,rgba(236,72,153,.32),transparent);bottom:-10vmax;left:10vmax;width:48vmax;height:48vmax;animation:d4 38s ease-in-out infinite alternate}
.a5{background:radial-gradient(closest-side,rgba(251,191,36,.20),transparent);top:30vmax;right:24vmax;width:40vmax;height:40vmax;animation:d5 26s ease-in-out infinite alternate}
@keyframes d1{to{transform:translate3d(-20vmax,16vmax,0) scale(1.18)}}
@keyframes d2{to{transform:translate3d(18vmax,-8vmax,0) scale(.9)}}
@keyframes d3{to{transform:translate3d(-14vmax,-18vmax,0) scale(1.2)}}
@keyframes d4{to{transform:translate3d(22vmax,-14vmax,0) scale(1.1)}}
@keyframes d5{to{transform:translate3d(-16vmax,10vmax,0) scale(1.25)}}
#sky{position:absolute;inset:0;width:100%;height:100%}
.bg-grid{position:absolute;inset:0;background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:64px 64px;-webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 20%,transparent 75%);mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 20%,transparent 75%)}
.bg-noise{position:absolute;inset:-50%;opacity:var(--noise);pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
.bg-spot{position:absolute;left:0;top:0;width:640px;height:640px;margin:-320px 0 0 -320px;border-radius:50%;pointer-events:none;
  background:radial-gradient(closest-side,var(--glow),transparent);transform:translate3d(var(--cx,-999px),var(--cy,-999px),0);transition:opacity .4s}

/* ═════════ شیشه ═════════ */
.g{position:relative;isolation:isolate;background:linear-gradient(150deg,var(--glass2),var(--glass));border:1px solid var(--line);
  border-radius:var(--r);box-shadow:inset 0 1px 0 var(--hi),var(--sh)}
.g::before{content:"";position:absolute;inset:0;border-radius:inherit;z-index:-1;pointer-events:none;opacity:0;transition:opacity .35s;
  background:radial-gradient(380px circle at var(--mx,50%) var(--my,50%),rgba(255,255,255,.13),transparent 45%)}
.g:hover::before{opacity:1}
.g::after{content:"";position:absolute;inset:-1px;border-radius:inherit;padding:1px;pointer-events:none;
  background:linear-gradient(140deg,rgba(255,255,255,.42),rgba(255,255,255,0) 28%,rgba(255,255,255,0) 72%,rgba(255,255,255,.2));
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude}
:root[data-theme=light] .g::after{background:linear-gradient(140deg,rgba(255,255,255,1),rgba(255,255,255,0) 30%,rgba(255,255,255,0) 70%,rgba(255,255,255,.8))}
:root[data-theme=light] .g::before{background:radial-gradient(380px circle at var(--mx,50%) var(--my,50%),rgba(99,102,241,.10),transparent 45%)}
.blur{backdrop-filter:blur(22px) saturate(170%);-webkit-backdrop-filter:blur(22px) saturate(170%)}
.lift{transition:transform .28s cubic-bezier(.2,.8,.2,1),border-color .28s,box-shadow .28s}
.lift:hover{transform:translateY(-6px);border-color:var(--line2);box-shadow:inset 0 1px 0 var(--hi),var(--sh2)}
.ring{position:absolute;inset:-1px;border-radius:inherit;padding:1.6px;pointer-events:none;z-index:2;
  background:conic-gradient(from var(--ang),transparent 0 55%,var(--r1,#FBBF24),var(--r2,#F472B6),var(--r3,#60A5FA),transparent 96%);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 5s linear infinite}
@keyframes spin{to{--ang:360deg}}

/* ═════════ دکمه‌ها ═════════ */
.btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:9px;height:48px;padding:0 22px;border-radius:15px;
  border:1px solid transparent;font-weight:800;font-size:14.5px;cursor:pointer;white-space:nowrap;
  transition:transform .2s,box-shadow .2s,background .2s,border-color .2s,opacity .2s}
.btn::after{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,rgba(255,255,255,.45) 50%,transparent 70%);transform:translateX(130%);transition:transform .75s;pointer-events:none}
.btn:hover::after{transform:translateX(-130%)}
.btn:hover{transform:translateY(-2px)}
.btn:active{transform:translateY(0) scale(.98)}
.btn[disabled]{opacity:.55;pointer-events:none}
.btn-pri{background:var(--grad);color:#fff;box-shadow:0 14px 34px -12px rgba(99,102,241,.85),inset 0 1px 0 rgba(255,255,255,.35)}
.btn-gold{background:var(--gold-g);color:#2A1300;box-shadow:0 14px 34px -14px rgba(249,115,22,.85),inset 0 1px 0 rgba(255,255,255,.5)}
.btn-ig{background:var(--ig);color:#fff;box-shadow:0 14px 34px -14px rgba(221,42,123,.85),inset 0 1px 0 rgba(255,255,255,.3)}
.btn-grn{background:var(--grn-g);color:#fff;box-shadow:0 14px 34px -14px rgba(34,197,94,.8),inset 0 1px 0 rgba(255,255,255,.35)}
.btn-glass{background:var(--glass2);border-color:var(--line2);color:var(--ink);box-shadow:inset 0 1px 0 var(--hi)}
.btn-glass:hover{border-color:rgba(56,189,248,.55);color:var(--sky)}
.btn-lg{height:56px;padding:0 28px;font-size:15.5px;border-radius:17px}
.btn-sm{height:38px;padding:0 14px;font-size:13px;border-radius:12px}
.btn-block{width:100%}
.btn .ic{width:18px;height:18px}
.spin{width:18px;height:18px;border-radius:50%;border:2.5px solid rgba(255,255,255,.35);border-top-color:#fff;animation:rot .8s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}

/* ═════════ اعلان و هدر ═════════ */
.notice{position:relative;z-index:2;text-align:center;font-size:13px;font-weight:700;padding:9px 16px;color:#fff;
  background:linear-gradient(90deg,rgba(56,189,248,.85),rgba(99,102,241,.85),rgba(168,85,247,.85))}
.notice .ic{display:inline-block;vertical-align:-3px;margin-left:6px;width:15px;height:15px}
.hdr{position:sticky;top:12px;z-index:50;padding:0 20px;margin-top:12px}
.hdr-in{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:18px;height:68px;padding:0 12px 0 14px;border-radius:22px}
.logo{display:flex;align-items:center;gap:11px;font-weight:900;font-size:18.5px;letter-spacing:-.2px;padding-inline-start:4px}
.logo-mark{position:relative;width:42px;height:42px;border-radius:14px;background:var(--grad);display:grid;place-items:center;color:#fff;
  box-shadow:0 10px 26px -10px rgba(99,102,241,.95),inset 0 1px 0 rgba(255,255,255,.4)}
.logo-mark .ic{width:22px;height:22px;fill:#fff;stroke:none;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25))}
.logo small{display:block;font-size:11px;font-weight:600;color:var(--dim);letter-spacing:0;line-height:1.3}
.nav{display:flex;align-items:center;gap:2px;margin-inline-start:auto}
.nav a{padding:8px 13px;border-radius:12px;font-size:14px;font-weight:700;color:var(--ink2);transition:background .15s,color .15s}
.nav a:hover{background:var(--glass2);color:var(--ink)}
.hdr-act{display:flex;align-items:center;gap:8px}
.icon-btn{width:46px;height:46px;border-radius:14px;border:1px solid var(--line2);background:var(--glass);display:grid;place-items:center;cursor:pointer;color:var(--ink);transition:background .15s}
.icon-btn:hover{background:var(--glass3)}
.icon-btn .ic{width:20px;height:20px}
.i-sun{display:none}
:root[data-theme=light] .i-sun{display:block} :root[data-theme=light] .i-moon{display:none}
.acct{display:flex;align-items:center;gap:10px;height:46px;padding:0 8px 0 14px;border-radius:14px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;color:var(--ink)}
.acct:hover{background:var(--glass3)}
.acct .av{width:32px;height:32px;border-radius:11px;background:var(--grad);display:grid;place-items:center;color:#fff;font-weight:900;font-size:14px}
.acct .t{display:flex;flex-direction:column;line-height:1.35;text-align:start}
.acct .t b{font-size:13px;font-weight:800;max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acct .t span{font-size:11.5px;color:var(--dim);font-weight:700}
.menu-btn{display:none}
.drawer{display:none}

/* ═════════ هیرو ═════════ */
.hero{position:relative;padding:64px 0 34px}
.hero-in{display:grid;grid-template-columns:1.05fr .95fr;gap:52px;align-items:center}
.pill{display:inline-flex;align-items:center;gap:9px;padding:6px 16px 6px 8px;border-radius:99px;font-size:13px;font-weight:700;color:var(--ink2)}
.pill .dot{width:24px;height:24px;border-radius:99px;background:rgba(34,197,94,.16);display:grid;place-items:center}
.pill .dot::after{content:"";width:8px;height:8px;border-radius:99px;background:var(--green);animation:ping 2s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)}80%,100%{box-shadow:0 0 0 10px rgba(34,197,94,0)}}
.hero h1{font-size:clamp(34px,5vw,62px);line-height:1.26;font-weight:900;letter-spacing:-1px;margin:24px 0 18px}
.rot{display:inline-block;min-width:3ch;transition:opacity .35s,transform .35s,filter .35s}
.rot.out{opacity:0;transform:translateY(-18px);filter:blur(6px)}
.rot.pre{opacity:0;transform:translateY(18px);filter:blur(6px);transition:none}
.hero-sub{font-size:clamp(15px,1.4vw,18px);color:var(--ink2);max-width:600px;line-height:2}
.hero-cta{display:flex;flex-wrap:wrap;gap:12px;margin:32px 0 28px}
.trust{display:flex;flex-wrap:wrap;gap:10px}
.trust span{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--ink2);padding:7px 14px;border-radius:99px}
.trust .ic{width:17px;height:17px;color:var(--green)}

.hv{position:relative}
.bento{position:relative;display:grid;grid-template-columns:1.12fr 1fr;gap:14px}
.bt{display:flex;flex-direction:column;padding:18px;border-radius:24px}
.bt-row{display:flex;align-items:center;gap:13px;margin-bottom:14px}
.bt-k{font-size:12px;color:var(--dim);font-weight:700;line-height:1.6}
.bt-t{font-size:15px;font-weight:800;line-height:1.6}
.bt-p{margin-top:auto;display:flex;align-items:baseline;justify-content:space-between;gap:8px;padding-top:12px;border-top:1px dashed var(--line2);font-size:12.5px}
.bt-p .amt{font-size:16px}
.bt-star{grid-row:1/3;padding:24px;overflow:hidden;animation:float 7s ease-in-out infinite}
.bt-star::before{opacity:1;background:radial-gradient(260px circle at 85% 0%,rgba(251,191,36,.28),transparent 70%)}
.big-ic{position:relative;width:68px;height:68px;border-radius:22px;background:var(--gold-g);display:grid;place-items:center;margin-bottom:18px;
  box-shadow:0 18px 36px -12px rgba(249,115,22,.9),inset 0 1px 0 rgba(255,255,255,.6)}
.big-ic .ic{width:36px;height:36px;fill:#fff;stroke:none;filter:drop-shadow(0 3px 8px rgba(0,0,0,.25))}
.bt-star .bt-t{font-size:32px;font-weight:900;line-height:1.35;margin-top:2px}
.bt-star .bt-p{margin:22px 0 16px;padding:14px;border:1px solid var(--line);border-radius:15px;background:var(--glass)}
.bt-star .bt-p .amt{font-size:22px}
.bt-prem{animation:float 8s ease-in-out -2s infinite}
.bt-ig{animation:float 9s ease-in-out -4s infinite}
@keyframes float{0%,100%{translate:0 0}50%{translate:0 -9px}}
.orb{position:relative;width:48px;height:48px;border-radius:16px;display:grid;place-items:center;color:#fff;flex:none;
  box-shadow:0 12px 26px -12px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.4)}
.orb .ic{width:24px;height:24px}
.o-gold{background:var(--gold-g)} .o-vio{background:var(--vio-g)} .o-pink{background:var(--pink-g)} .o-grn{background:var(--grn-g)} .o-ig{background:var(--ig)} .o-tg{background:var(--grad)}
.emo{font-size:30px;line-height:1;width:48px;height:48px;border-radius:16px;background:var(--glass2);border:1px solid var(--line);display:grid;place-items:center;flex:none}

/* ═════════ نوار قیمت ═════════ */
.ticker{position:relative;margin-top:38px;overflow:hidden;border-radius:0;border-inline:0;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.ticker-track{display:flex;width:max-content;animation:tick 46s linear infinite}
.ticker:hover .ticker-track{animation-play-state:paused}
.tk{display:flex;align-items:center;gap:10px;padding:15px 28px;font-size:14px;white-space:nowrap;border-inline-end:1px solid var(--line)}
.tk .ic{width:18px;height:18px;color:var(--sky)}
.tk b{font-weight:900}
.tk .live{width:7px;height:7px;border-radius:99px;background:var(--green);box-shadow:0 0 10px var(--green)}
@keyframes tick{from{transform:translateX(0)}to{transform:translateX(50%)}}

/* ═════════ بخش‌ها ═════════ */
.sec{padding:100px 0;position:relative}
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:42px;flex-wrap:wrap}
.sec-head.center{flex-direction:column;align-items:center;text-align:center}
.kicker{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:800;padding:7px 15px;border-radius:99px;margin-bottom:16px;color:var(--sky)}
.kicker .ic{width:16px;height:16px}
.kicker.gold{color:var(--gold)} .kicker.vio{color:#C084FC} .kicker.pink{color:#F472B6} .kicker.grn{color:#4ADE80}
.sec h2{font-size:clamp(27px,3.4vw,44px);line-height:1.35;font-weight:900;letter-spacing:-.6px}
.sec-head p{color:var(--dim);max-width:580px;margin-top:10px;font-size:15.5px}
.sec-head.center p{margin-inline:auto}
.amt{font-weight:900;font-variant-numeric:tabular-nums}
.cur{font-size:.78em;color:var(--dim);font-weight:700}
.ask{font-weight:700;color:var(--dim);font-size:.92em}
.tag{font-size:11.5px;font-weight:800;padding:3px 11px;border-radius:99px;background:rgba(244,63,94,.14);color:#FB7185;white-space:nowrap;border:1px solid rgba(244,63,94,.22)}
.tag.gold{background:rgba(251,191,36,.14);color:var(--gold);border-color:rgba(251,191,36,.25)}

/* خدمات */
.svc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.svc{display:flex;flex-direction:column;gap:14px;padding:28px;border-radius:var(--r2);overflow:hidden}
.svc .halo{position:absolute;inset:auto -60px -80px auto;width:240px;height:240px;border-radius:50%;opacity:.22;filter:blur(40px);z-index:-1;background:var(--c)}
.svc h3{font-size:19.5px;font-weight:900}
.svc p{color:var(--dim);font-size:14px;line-height:1.95;flex:1}
.svc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:15px;border-top:1px dashed var(--line2);font-size:13.5px}
.svc-foot .amt{font-size:16px}
.svc-go{display:inline-flex;align-items:center;gap:6px;font-weight:800;color:var(--sky)}
.svc-go .ic{width:16px;height:16px;transition:transform .2s}
.svc:hover .svc-go .ic{transform:translateX(-5px)}

/* استارز */
.stars-lay{display:grid;grid-template-columns:390px 1fr;gap:24px;align-items:start}
.calc{position:sticky;top:100px;padding:28px;border-radius:var(--r2);overflow:hidden}
.calc h3{font-size:20px;font-weight:900;display:flex;align-items:center;gap:10px}
.calc h3 .ic{color:var(--gold);fill:var(--gold);stroke:none;width:24px;height:24px}
.calc label{display:block;font-size:13px;font-weight:700;color:var(--dim);margin:20px 0 8px}
.qty-box{display:flex;align-items:center;gap:8px;background:var(--field);border:1px solid var(--line2);border-radius:17px;padding:6px}
.qty-box input{flex:1;min-width:0;background:transparent;border:0;outline:0;font-size:25px;font-weight:900;text-align:center;padding:4px 0;direction:ltr}
.qty-box button{width:46px;height:46px;border-radius:13px;border:1px solid var(--line2);background:var(--glass2);cursor:pointer;font-size:22px;font-weight:800;line-height:1}
.qty-box button:hover{border-color:var(--gold);color:var(--gold)}
input[type=range]{width:100%;margin-top:16px;accent-color:var(--gold);height:6px}
.quick{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
.quick button{padding:6px 13px;border-radius:11px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;font-size:13px;font-weight:800}
.quick button:hover,.quick button.on{border-color:var(--gold);color:var(--gold);background:rgba(251,191,36,.10)}
.calc-total{margin:22px 0 16px;padding:18px;border-radius:17px;background:linear-gradient(135deg,rgba(251,191,36,.16),rgba(249,115,22,.06));border:1px solid rgba(251,191,36,.3)}
.calc-total .t{font-size:12.5px;color:var(--dim);font-weight:700}
.calc-total .v{font-size:32px;font-weight:900;line-height:1.5}
.calc-total .v small{font-size:14px;color:var(--dim);font-weight:700}
.calc-note{font-size:12px;color:var(--dim);margin-top:12px;display:flex;gap:6px;line-height:1.8}
.calc-note .ic{width:15px;height:15px;margin-top:4px}
.pk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(172px,1fr));gap:14px}
.pk{display:flex;flex-direction:column;gap:6px;padding:20px}
.pk:hover{border-color:rgba(251,191,36,.45)}
.pk-top{display:flex;align-items:center;justify-content:space-between}
.pk-star{width:42px;height:42px;border-radius:14px;background:rgba(251,191,36,.14);border:1px solid rgba(251,191,36,.25);display:grid;place-items:center}
.pk-star .ic{width:22px;height:22px;fill:var(--gold);stroke:none}
.pk-q{font-size:29px;font-weight:900;line-height:1.35;margin-top:8px}
.pk-q small{font-size:14px;color:var(--dim);font-weight:700}
.pk .amt{font-size:17px}
.pk-unit{font-size:12px;color:var(--dim)}
.pk .btn{margin-top:10px;height:42px}
.uses{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:26px}
.use{display:flex;align-items:center;gap:12px;padding:16px;font-size:13.5px;font-weight:800}
.use .ic{width:22px;height:22px;color:var(--gold)}

/* پریمیوم */
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;align-items:stretch}
.plan{display:flex;flex-direction:column;padding:32px 26px;border-radius:var(--r2)}
.plan.hot{--r1:#C084FC;--r2:#F472B6;--r3:#60A5FA;box-shadow:inset 0 1px 0 var(--hi),0 40px 80px -40px rgba(168,85,247,.8)}
.plan-badge{position:absolute;top:-15px;inset-inline-start:50%;transform:translateX(50%);background:var(--vio-g);color:#fff;font-size:12px;font-weight:800;padding:5px 16px;border-radius:99px;white-space:nowrap;z-index:3;box-shadow:0 10px 20px -8px rgba(168,85,247,.9)}
.plan h3{font-size:21px;font-weight:900;margin-top:18px}
.plan .muted{font-size:13.5px;min-height:26px}
.plan-price{margin:18px 0 4px}
.plan-price .amt{font-size:36px}
.plan-mo{font-size:13px;color:var(--dim);margin-bottom:20px}
.plan ul{list-style:none;display:grid;gap:11px;margin-bottom:26px;flex:1}
.plan li{display:flex;gap:10px;font-size:14px;color:var(--ink2)}
.plan li .ic{width:19px;height:19px;color:#C084FC;margin-top:4px}
.feats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:34px}
.feat{padding:20px}
.feat .ic{width:26px;height:26px;color:#C084FC;margin-bottom:12px}
.feat b{display:block;font-size:15px;font-weight:800;margin-bottom:4px}
.feat span{font-size:13px;color:var(--dim);line-height:1.8}

/* گیفت */
.gift-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.gift{text-align:center;padding:20px 12px 16px;display:flex;flex-direction:column;align-items:center;gap:4px}
.gift:hover{border-color:rgba(236,72,153,.45)}
.gift-e{font-size:48px;line-height:1;width:88px;height:88px;border-radius:28px;display:grid;place-items:center;margin-bottom:10px;transition:transform .35s cubic-bezier(.2,.9,.3,1.4);
  background:radial-gradient(circle at 30% 25%,rgba(236,72,153,.28),rgba(168,85,247,.14) 60%,transparent 75%)}
.gift:hover .gift-e{transform:scale(1.12) rotate(-6deg)}
.gift h3{font-size:14.5px;font-weight:800}
.gift .st{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--gold);font-weight:800}
.gift .st .ic{width:13px;height:13px;fill:var(--gold);stroke:none}
.gift .amt{font-size:15px}
.gift>div:last-of-type{margin-bottom:10px}
.gift .btn{margin-top:auto;height:38px;font-size:13px;width:100%}

/* شماره مجازی */
.num-bar{display:flex;gap:12px;align-items:center;margin-bottom:22px;flex-wrap:wrap}
.search{flex:1;min-width:240px;display:flex;align-items:center;gap:10px;height:56px;padding:0 18px;border-radius:18px}
.search:focus-within{border-color:rgba(34,197,94,.6);box-shadow:0 0 0 4px rgba(34,197,94,.12)}
.search .ic{width:20px;height:20px;color:var(--dim)}
.search input{flex:1;min-width:0;border:0;outline:0;background:transparent;font-size:15px}
.num-meta{display:flex;gap:10px;flex-wrap:wrap}
.num-meta span{display:inline-flex;align-items:center;gap:7px;height:56px;padding:0 18px;border-radius:18px;font-size:13.5px;font-weight:800}
.num-meta .ic{color:var(--green);width:18px;height:18px}
.ctry-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.ctry{display:flex;align-items:center;gap:14px;padding:14px 16px;cursor:pointer;text-align:start;width:100%;border-radius:18px}
.ctry:hover{border-color:rgba(34,197,94,.5)}
.flag{font-size:30px;line-height:1;width:52px;height:52px;border-radius:15px;background:var(--glass2);border:1px solid var(--line);display:grid;place-items:center;flex:none}
.ctry-t{flex:1;min-width:0}
.ctry-t b{display:block;font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctry-t span{font-size:12px;color:var(--dim)}
.ctry-p{text-align:end;font-size:12px;color:var(--dim);white-space:nowrap}
.ctry-p .amt{display:block;font-size:14.5px;color:var(--ink)}
.empty{display:none;text-align:center;padding:40px;color:var(--dim);border:1px dashed var(--line2);border-radius:var(--r);margin-top:12px}
.num-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:26px}
.num-step{display:flex;gap:14px;align-items:flex-start;padding:18px}
.num-step i{font-style:normal;width:36px;height:36px;border-radius:12px;background:var(--grn-g);color:#fff;display:grid;place-items:center;font-weight:900;flex:none}
.num-step b{display:block;font-size:14.5px}
.num-step span{font-size:13px;color:var(--dim)}

/* اینستاگرام */
.ig-band{border-radius:34px;padding:46px;overflow:hidden}
.ig-band .halo{position:absolute;width:520px;height:520px;inset-inline-end:-160px;top:-220px;border-radius:50%;background:var(--ig);filter:blur(90px);opacity:.35;z-index:-1}
.ig-band .halo.b{inset-inline-end:auto;inset-inline-start:-200px;top:auto;bottom:-260px;opacity:.22}
.ig-top{display:grid;grid-template-columns:1fr 410px;gap:34px;align-items:center;margin-bottom:34px}
.ig-logo{width:72px;height:72px;border-radius:24px;background:var(--ig);display:grid;place-items:center;color:#fff;margin-bottom:18px;box-shadow:0 18px 38px -14px rgba(221,42,123,.95),inset 0 1px 0 rgba(255,255,255,.4)}
.ig-logo .ic{width:38px;height:38px}
.ig-points{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.ig-points span{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:99px;font-size:13px;font-weight:800}
.ig-points .ic{width:16px;height:16px;color:#F472B6}
.ig-calc{padding:24px;border-radius:26px}
.ig-calc h3{font-size:17px;font-weight:900;margin-bottom:14px}
.field{display:block;margin-bottom:12px}
.field>span{display:block;font-size:12.5px;font-weight:800;color:var(--dim);margin-bottom:6px}
.inp{width:100%;height:50px;border-radius:14px;border:1px solid var(--line2);background:var(--field);padding:0 14px;font-size:14.5px;font-weight:700;outline:0;transition:border-color .15s,box-shadow .15s}
textarea.inp{height:96px;padding:12px 14px;resize:vertical;line-height:1.8}
.inp:focus{border-color:var(--indigo);box-shadow:0 0 0 4px rgba(99,102,241,.16)}
.inp.ltr{direction:ltr;text-align:left}
select.inp option{background:#0E1230;color:#fff}
:root[data-theme=light] select.inp option{background:#fff;color:#0A0F26}
.ig-total{display:flex;align-items:center;justify-content:space-between;margin:16px 0;padding:14px 16px;border-radius:15px;background:var(--glass2);border:1px solid var(--line)}
.ig-total b{font-size:23px;font-weight:900}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
.tab{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 17px;border-radius:14px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;font-weight:800;font-size:14px;color:var(--ink2)}
.tab .ic{width:17px;height:17px}
.tab:hover{color:var(--ink);background:var(--glass2)}
.tab.on{background:var(--ink);color:var(--bg0);border-color:var(--ink)}
.offer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.offer{display:flex;flex-direction:column;gap:8px;padding:20px}
.offer.hide{display:none}
.offer-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.offer .orb{width:46px;height:46px}
.offer h3{font-size:15.5px;font-weight:900;line-height:1.7}
.offer .muted{font-size:13px;line-height:1.8;flex:1}
.offer-price{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 8px;margin-top:4px}
.offer-price .amt{font-size:20px}
.per{font-size:12px;color:var(--dim);font-weight:700}
.offer-range{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--dim)}
.offer-range .ic{width:14px;height:14px}
.offer .btn{margin-top:8px;height:44px}

/* مراحل، مزایا، پرداخت، آمار */
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;position:relative}
.steps::before{content:"";position:absolute;top:40px;inset-inline:12%;height:2px;background:linear-gradient(90deg,transparent,var(--sky),var(--violet),var(--pink),transparent);opacity:.5}
.step{position:relative;text-align:center;padding:0 10px}
.step-n{width:80px;height:80px;margin:0 auto 18px;border-radius:26px;display:grid;place-items:center}
.step-n .ic{width:34px;height:34px;color:var(--sky)}
.step-n i{position:absolute;top:-9px;inset-inline-end:-9px;width:30px;height:30px;border-radius:99px;background:var(--grad);color:#fff;font-style:normal;font-weight:900;font-size:13px;display:grid;place-items:center;z-index:3;box-shadow:0 6px 14px -4px rgba(99,102,241,.8)}
.step b{display:block;font-size:17px;font-weight:900;margin-bottom:6px}
.step span{font-size:14px;color:var(--dim)}
.why{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.why-c{padding:26px;border-radius:var(--r2)}
.why-c .orb{margin-bottom:16px}
.why-c b{display:block;font-size:17px;font-weight:900;margin-bottom:6px}
.why-c span{font-size:14px;color:var(--dim);line-height:1.9}
.pay{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.pay-c{display:flex;align-items:center;gap:14px;padding:20px}
.pay-c b{display:block;font-size:15px}
.pay-c span{font-size:12.5px;color:var(--dim)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:40px}
.stat{padding:26px;text-align:center}
.stat b{display:block;font-size:38px;font-weight:900;line-height:1.4}
.stat span{font-size:13.5px;color:var(--dim);font-weight:700}

/* سوالات */
.faq{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
.faq details{border-radius:var(--r)}
.faq details[open]{border-color:var(--line2)}
.faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:14px;padding:18px 20px;font-weight:800;font-size:15px}
.faq summary::-webkit-details-marker{display:none}
.faq summary .q{width:36px;height:36px;border-radius:12px;background:rgba(99,102,241,.16);color:#A5B4FC;display:grid;place-items:center;flex:none}
.faq summary .q .ic{width:18px;height:18px}
.faq summary .chev{margin-inline-start:auto;width:18px;height:18px;color:var(--dim);transition:transform .25s}
.faq details[open] .chev{transform:rotate(180deg)}
.faq details p{padding:0 20px 20px;padding-inline-start:70px;color:var(--ink2);font-size:14.5px;line-height:2}

/* CTA و فوتر */
.cta{position:relative;border-radius:36px;padding:62px 50px;overflow:hidden;display:grid;grid-template-columns:1fr auto;gap:30px;align-items:center;isolation:isolate;
  background:linear-gradient(135deg,rgba(56,189,248,.9),rgba(99,102,241,.9) 50%,rgba(168,85,247,.9));color:#fff;box-shadow:0 40px 90px -40px rgba(99,102,241,.9),inset 0 1px 0 rgba(255,255,255,.4)}
.cta::before{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(420px 300px at 8% 120%,rgba(255,255,255,.3),transparent 70%),radial-gradient(320px 240px at 96% -10%,rgba(253,230,138,.55),transparent 70%)}
.cta h2{font-size:clamp(26px,3vw,40px);font-weight:900;line-height:1.4}
.cta p{opacity:.92;margin-top:8px;font-size:16px}
.cta .btn{background:#fff;color:#3730A3;box-shadow:0 16px 34px -14px rgba(0,0,0,.5)}
.cta .btn-line{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.4)}
.cta-acts{display:flex;gap:12px;flex-wrap:wrap}
.ftr{margin:100px 20px 20px;border-radius:32px;padding:60px 0 26px}
.ftr-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:34px}
.ftr p{color:var(--dim);font-size:14px;margin-top:14px;max-width:340px}
.ftr h4{font-size:15px;font-weight:900;margin-bottom:14px}
.ftr ul{list-style:none;display:grid;gap:9px}
.ftr li a{color:var(--dim);font-size:14px;display:inline-flex;align-items:center;gap:8px}
.ftr li a:hover{color:var(--ink)}
.ftr li .ic{width:16px;height:16px}
.ftr-bot{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:48px;padding-top:22px;border-top:1px solid var(--line);color:var(--dim);font-size:13px}

/* ═════════ پنجره‌ی خرید / حساب ═════════ */
.sheet{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:16px}
.sheet.open{display:flex}
.sheet-bg{position:absolute;inset:0;background:rgba(3,5,14,.55);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);animation:fade .25s}
.sheet-card{position:relative;width:100%;max-width:470px;max-height:calc(100dvh - 32px);overflow:auto;border-radius:30px;padding:26px;animation:pop .35s cubic-bezier(.2,.9,.3,1.2);
  background:linear-gradient(160deg,var(--glass3),var(--glass2));overscroll-behavior:contain}
:root[data-theme=dark] .sheet-card{background:linear-gradient(160deg,rgba(30,34,70,.78),rgba(14,16,40,.82))}
@keyframes fade{from{opacity:0}}
@keyframes pop{from{opacity:0;transform:translateY(24px) scale(.96)}}
.sheet-x{position:absolute;top:16px;inset-inline-end:16px;z-index:3}
.s-head{display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-inline-end:52px}
.s-head .orb,.s-head .emo{width:54px;height:54px;border-radius:18px;font-size:32px}
.s-head h3{font-size:19px;font-weight:900;line-height:1.55}
.s-head .muted{font-size:13px}
.s-box{padding:14px 16px;border-radius:17px;background:var(--glass);border:1px solid var(--line);margin:14px 0}
.s-row{display:flex;justify-content:space-between;align-items:baseline;gap:12px;font-size:14px;padding:4px 0}
.s-row b{font-weight:900}
.s-row.total{border-top:1px dashed var(--line2);margin-top:6px;padding-top:10px;font-size:15px}
.s-row.total b{font-size:21px}
.s-row.total:first-child{border-top:0;margin-top:0;padding-top:4px}
.s-row.disc b{color:var(--green)}
.s-err{display:flex;gap:9px;align-items:flex-start;padding:12px 14px;border-radius:14px;background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.3);color:#FDA4AF;font-size:13.5px;font-weight:700;margin:12px 0;line-height:1.8;white-space:pre-line}
:root[data-theme=light] .s-err{color:#BE123C}
.s-err .ic{width:18px;height:18px;margin-top:3px}
.s-note{display:flex;gap:8px;font-size:12.5px;color:var(--dim);line-height:1.8;margin-top:12px}
.s-note .ic{width:16px;height:16px;margin-top:3px;color:var(--green)}
.s-acts{display:grid;gap:10px;margin-top:16px}
.s-acts.two{grid-template-columns:1fr 1fr}
.chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}
.chip{padding:6px 12px;border-radius:11px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;font-size:12.5px;font-weight:800}
.chip:hover{border-color:var(--sky);color:var(--sky)}
.qrow{display:flex;gap:8px;align-items:center}
.qrow .inp{text-align:center;font-size:18px;font-weight:900;direction:ltr}
.qrow button{width:50px;height:50px;border-radius:14px;border:1px solid var(--line2);background:var(--glass2);cursor:pointer;font-size:22px;font-weight:800;flex:none}
.hint{font-size:12px;color:var(--dim);margin-top:6px}
.cpn{display:flex;gap:8px}
.cpn .inp{height:44px}
.cpn .btn{height:44px}
.link-btn{background:none;border:0;color:var(--sky);font-weight:800;font-size:13px;cursor:pointer;padding:4px 0}
.big-code{font-size:44px;font-weight:900;letter-spacing:10px;text-align:center;direction:ltr;padding:8px 0;font-variant-numeric:tabular-nums}
.wait{display:flex;align-items:center;justify-content:center;gap:10px;color:var(--dim);font-size:13.5px;font-weight:700;margin-top:12px}
.wait .spin{border-color:rgba(127,127,160,.3);border-top-color:var(--sky)}
.okmark{width:86px;height:86px;border-radius:50%;margin:6px auto 16px;display:grid;place-items:center;background:var(--grn-g);color:#fff;box-shadow:0 20px 40px -14px rgba(34,197,94,.9);animation:pop .5s cubic-bezier(.2,.9,.3,1.5)}
.okmark .ic{width:44px;height:44px;stroke-width:2.6}
.center{text-align:center}
.copy{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-radius:15px;background:var(--glass2);border:1px solid var(--line2);font-weight:900;direction:ltr;font-size:17px;word-break:break-all}
.copy button{flex:none}
.olist{display:grid;gap:8px;margin-top:10px}
.oitem{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:15px;background:var(--glass);border:1px solid var(--line)}
.oitem .e{font-size:22px;width:38px;height:38px;border-radius:12px;background:var(--glass2);display:grid;place-items:center;flex:none}
.oitem .t{flex:1;min-width:0}
.oitem .t b{display:block;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.oitem .t span{font-size:11.5px;color:var(--dim)}
.oitem .p{text-align:end;font-size:12px;color:var(--dim);white-space:nowrap}
.oitem .p b{display:block;color:var(--ink);font-size:13.5px}
.svc-pick{display:grid;gap:8px;margin-top:6px}
.svc-pick button{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-radius:16px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;text-align:start}
.svc-pick button:hover{border-color:rgba(34,197,94,.55)}
.svc-pick b{font-size:14.5px}
.svc-pick small{display:block;color:var(--dim);font-size:12px}
.bal{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px;border-radius:20px;background:linear-gradient(135deg,rgba(56,189,248,.18),rgba(168,85,247,.14));border:1px solid var(--line2);margin:12px 0}
.bal b{font-size:26px;font-weight:900}
.num-phone{font-size:28px;font-weight:900;direction:ltr;letter-spacing:1px}
.toast{position:fixed;bottom:24px;left:50%;z-index:200;transform:translate(-50%,20px);opacity:0;pointer-events:none;padding:13px 20px;border-radius:16px;font-weight:800;font-size:14px;transition:opacity .3s,transform .3s;max-width:calc(100% - 32px)}
.toast.show{opacity:1;transform:translate(-50%,0)}
.fab{position:fixed;bottom:22px;inset-inline-start:22px;z-index:60;display:flex;flex-direction:column;gap:10px}
.fab a,.fab button{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;border:0;cursor:pointer}
.fab .sup{background:var(--grad);color:#fff;box-shadow:0 16px 34px -12px rgba(99,102,241,.9),inset 0 1px 0 rgba(255,255,255,.4)}
.fab .sup .ic{width:26px;height:26px}
.fab .top{color:var(--ink);opacity:0;pointer-events:none;transform:translateY(10px);transition:opacity .2s,transform .2s}
.fab .top.show{opacity:1;pointer-events:auto;transform:none}
.fab .top .ic{width:22px;height:22px}

/* ═════════ ظاهر شدن با اسکرول ═════════ */
.js .rv{opacity:0;transform:translateY(26px) scale(.98);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)}
.js .rv.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}
  .js .rv{opacity:1;transform:none}
}

/* ═════════ واکنش‌گرا ═════════ */
@media (max-width:1140px){
  .nav{display:none}
  .menu-btn{display:grid}
  .hdr-act{margin-inline-start:auto}
  .drawer{position:fixed;top:92px;left:20px;right:20px;z-index:49;border-radius:22px;padding:12px;display:none;flex-direction:column;gap:4px}
  .drawer.open{display:flex}
  .drawer a{padding:12px 14px;border-radius:13px;font-weight:800}
  .drawer a:hover{background:var(--glass2)}
  .gift-grid{grid-template-columns:repeat(4,1fr)}
  .offer-grid{grid-template-columns:repeat(3,1fr)}
  .ctry-grid{grid-template-columns:repeat(3,1fr)}
  .feats{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:960px){
  .hero-in{grid-template-columns:1fr}
  .hv{max-width:560px;margin:0 auto;width:100%}
  .stars-lay{grid-template-columns:1fr}
  .calc{position:relative;top:0}
  .svc-grid,.why{grid-template-columns:repeat(2,1fr)}
  .plans{grid-template-columns:1fr;max-width:460px;margin:0 auto}
  .ig-top{grid-template-columns:1fr}
  .steps{grid-template-columns:repeat(2,1fr);row-gap:34px}
  .steps::before{display:none}
  .pay,.uses{grid-template-columns:repeat(2,1fr)}
  .faq{grid-template-columns:1fr}
  .cta{grid-template-columns:1fr;padding:44px 30px}
  .ftr-grid{grid-template-columns:1fr 1fr}
  .num-steps{grid-template-columns:1fr}
}
@media (max-width:640px){
  body{font-size:15px}
  .wrap{padding:0 16px}
  .hdr{padding:0 12px;top:8px;margin-top:8px}
  .hdr-in{height:62px;gap:10px;border-radius:19px}
  .drawer{top:78px;left:12px;right:12px}
  .hdr-act .btn,.acct .t{display:none}
  .acct{padding:0 7px}
  .logo small{display:none}
  .hero{padding:36px 0 20px}
  .hero-cta .btn{flex:1}
  .bento{gap:10px}
  .bt{padding:14px;border-radius:20px}
  .bt-star{padding:18px}
  .bt-star .bt-t{font-size:23px}
  .big-ic{width:54px;height:54px;border-radius:17px;margin-bottom:12px}
  .bt-row{flex-direction:column;align-items:flex-start;gap:8px}
  .bt-t{font-size:13.5px}
  .bt-p{flex-direction:column;gap:0}
  .sec{padding:72px 0}
  .svc-grid,.why,.pay,.uses{grid-template-columns:1fr}
  .pk-grid{grid-template-columns:repeat(2,1fr)}
  .gift-grid{grid-template-columns:repeat(2,1fr)}
  .offer-grid,.ctry-grid{grid-template-columns:1fr}
  .ig-band{padding:26px 16px;border-radius:26px}
  .steps{grid-template-columns:1fr}
  .stats{grid-template-columns:repeat(2,1fr)}
  .ftr{margin:72px 12px 12px;border-radius:26px}
  .ftr-grid{grid-template-columns:1fr}
  .faq details p{padding-inline-start:20px}
  .fab{bottom:14px;inset-inline-start:14px}
  .fab a,.fab button{width:48px;height:48px;border-radius:16px}
  .sheet{align-items:flex-end;padding:0}
  .sheet-card{max-width:none;border-radius:28px 28px 0 0;max-height:92dvh;padding:22px 18px calc(22px + env(safe-area-inset-bottom));animation:up .35s cubic-bezier(.2,.9,.3,1)}
  @keyframes up{from{transform:translateY(100%)}}
}
</style>
</head>
<body>

<div class="bg" aria-hidden="true">
  <div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><div class="aur a4"></div><div class="aur a5"></div>
  <canvas id="sky"></canvas>
  <div class="bg-grid"></div>
  <div class="bg-spot" id="spot"></div>
  <div class="bg-noise"></div>
</div>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-star" viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.7 6.3.9-4.55 4.43 1.07 6.27L12 17.13 6.38 20.1l1.07-6.27L2.9 9.4l6.3-.9z"/></symbol>
  <symbol id="i-crown" viewBox="0 0 24 24"><path d="M3 7.5l4.5 4L12 5l4.5 6.5L21 7.5 19 18H5z"/><path d="M5 21h14"/></symbol>
  <symbol id="i-gift" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></symbol>
  <symbol id="i-insta" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20.5s-7.5-4.6-9.2-9.3C1.6 7.8 3.8 4.5 7.2 4.5c2 0 3.6 1.1 4.8 2.8 1.2-1.7 2.8-2.8 4.8-2.8 3.4 0 5.6 3.3 4.4 6.7-1.7 4.7-9.2 9.3-9.2 9.3z"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 2.5l8 3v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10v-6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
  <symbol id="i-headset" viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13" width="4" height="7" rx="1.5"/><rect x="17.5" y="13" width="4" height="7" rx="1.5"/><path d="M20 20c0 1.1-1.3 2-3 2h-3"/></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="3" y="8" width="18" height="12" rx="2.5"/><circle cx="16.5" cy="14" r=".8"/></symbol>
  <symbol id="i-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/></symbol>
  <symbol id="i-coin" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.4-1-1.4-1.5-2.5-1.5-1.5 0-2.5.8-2.5 2s1 1.6 2.5 2 2.5.8 2.5 2-1 2-2.5 2c-1.1 0-2.1-.5-2.5-1.5M12 6.5V8M12 16v1.5"/></symbol>
  <symbol id="i-ton" viewBox="0 0 24 24"><path d="M4.5 4.5h15L12 20.5z"/><path d="M12 4.5v16"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="M21.2 3.6L2.9 10.8c-.8.3-.8 1.5.1 1.8l4.6 1.5 1.8 5.5c.3.8 1.3 1 1.9.4l2.6-2.6 4.8 3.5c.7.5 1.6.1 1.8-.7l2.9-14.3c.2-.9-.7-1.7-1.6-1.3z"/><path d="M7.6 14.1L17.5 7.5l-7.4 8.1"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
  <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/></symbol>
  <symbol id="i-up" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></symbol>
  <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3l1.8 5.4L19 10l-5.2 1.6L12 17l-1.8-5.4L5 10l5.2-1.6z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/></symbol>
  <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></symbol>
  <symbol id="i-noad" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></symbol>
  <symbol id="i-mic" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></symbol>
  <symbol id="i-smile" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14a4 4 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/></symbol>
  <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2h8.5A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z"/></symbol>
  <symbol id="i-tag" viewBox="0 0 24 24"><path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/></symbol>
  <symbol id="i-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
  <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12.5H6z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></symbol>
  <symbol id="i-copy" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/></symbol>
  <symbol id="i-list" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></symbol>
</svg>

<?php if ($d['notice'] !== ''): ?>
<div class="notice"><?= siteIcon('bolt') ?><?= siteE($d['notice']) ?></div>
<?php endif; ?>

<header class="hdr" id="hdr">
  <div class="hdr-in g blur">
    <a href="#top" class="logo" aria-label="<?= siteE($name) ?>">
      <span class="logo-mark"><?= siteIcon('star') ?></span>
      <span><?= siteE($name) ?><small>خدمات تلگرام و اینستاگرام</small></span>
    </a>
    <nav class="nav" aria-label="بخش‌ها">
      <?php foreach ($nav as [$href, $label]): ?><a href="<?= $href ?>"><?= siteE($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="hdr-act">
      <button type="button" class="icon-btn" id="theme" aria-label="تغییر تم"><?= siteIcon('sun', 'i-sun') ?><?= siteIcon('moon', 'i-moon') ?></button>
      <?php if ($shop): ?>
      <button type="button" class="btn btn-pri" id="loginBtn"><?= siteIcon('send') ?> ورود با تلگرام</button>
      <button type="button" class="acct" id="acctBtn" hidden aria-label="حساب من"><span class="t"><b id="acctName">—</b><span id="acctBal">—</span></span><span class="av" id="acctAv">؟</span></button>
      <?php elseif ($bot !== ''): ?>
      <a class="btn btn-pri" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ورود به ربات</a>
      <?php endif; ?>
      <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="منو" aria-expanded="false"><?= siteIcon('menu') ?></button>
    </div>
  </div>
</header>
<nav class="drawer g blur" id="drawer" aria-label="منوی موبایل">
  <?php foreach ($nav as [$href, $label]): ?><a href="<?= $href ?>"><?= siteE($label) ?></a><?php endforeach; ?>
</nav>

<main id="top">

<!-- ═════════════ هیرو ═════════════ -->
<section class="hero">
  <div class="wrap hero-in">
    <div>
      <span class="pill g"><span class="dot"></span>خرید مستقیم · تحویل خودکار · ۲۴ ساعته</span>
      <h1>خرید <span class="shine rot" id="rot" data-words="<?= siteE(json_encode($words, JSON_UNESCAPED_UNICODE)) ?>"><?= siteE($words[0]) ?></span><br>تلگرام در چند ثانیه</h1>
      <p class="hero-sub">
        استارز، پریمیوم، گیفت، شماره مجازی<?= $d['countries'] ? ' از ' . siteFa(count($d['countries'])) . ' کشور' : '' ?>
        و فالوور اینستاگرام — مستقیم از همین سایت، با قیمت لحظه‌ای و تحویل خودکار.
        فقط با حساب تلگرام وارد شوید؛ بدون رمز.
      </p>
      <div class="hero-cta">
        <a class="btn btn-pri btn-lg" href="#services"><?= siteIcon('bag') ?> شروع خرید</a>
        <?php if ($bot !== ''): ?>
        <a class="btn btn-glass btn-lg" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ربات تلگرام</a>
        <?php endif; ?>
      </div>
      <div class="trust">
        <span class="g"><?= siteIcon('check') ?>بدون نیاز به رمز</span>
        <span class="g"><?= siteIcon('check') ?>پرداخت امن</span>
        <span class="g"><?= siteIcon('check') ?>بازگشت وجه در صورت خطا</span>
      </div>
    </div>

    <div class="hv" aria-hidden="true">
      <div class="bento">
        <div class="bt bt-star g blur">
          <i class="ring"></i>
          <div class="big-ic"><?= siteIcon('star') ?></div>
          <div class="bt-k">استارز تلگرام</div>
          <div class="bt-t"><?= $heroStar ? siteE($heroStar['name']) : 'استارز' ?></div>
          <div class="bt-k" style="margin-top:6px">تحویل مستقیم به آیدی شما</div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= $heroStar ? siteMoney($heroStar['price']) : siteMoney($d['star_unit']) ?></span></div>
          <span class="btn btn-gold btn-block"><?= siteIcon('bolt') ?> خرید فوری</span>
        </div>
        <div class="bt bt-prem g blur">
          <div class="bt-row"><span class="orb o-vio"><?= siteIcon('crown') ?></span>
            <div><div class="bt-k">تلگرام پریمیوم</div><div class="bt-t"><?= $heroPrem ? siteE($heroPrem['name']) : 'اشتراک پریمیوم' ?></div></div></div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= siteMoney($heroPrem['price'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-gift g blur">
          <div class="bt-row"><span class="emo"><?= siteE($heroGift['emoji'] ?? '🎁') ?></span>
            <div><div class="bt-k">گیفت تلگرام</div><div class="bt-t"><?= siteE($heroGift['name'] ?? 'گیفت تلگرام') ?></div></div></div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= siteMoney($heroGift['price'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-num g blur">
          <div class="bt-row"><span class="emo"><?= siteE($heroCtry['flag'] ?? '🌍') ?></span>
            <div><div class="bt-k">شماره مجازی</div><div class="bt-t"><?= siteE($heroCtry['name'] ?? 'کشورهای مختلف') ?></div></div></div>
          <div class="bt-p"><span class="muted">از</span><span><?= siteMoney($heroCtry['from'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-ig g blur">
          <div class="bt-row"><span class="orb o-ig"><?= siteIcon('insta') ?></span>
            <div><div class="bt-k">اینستاگرام</div><div class="bt-t">فالوور · لایک · بازدید</div></div></div>
          <div class="bt-p"><span class="muted">از</span><span><?= siteMoney($igMin) ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($ticker): ?>
  <div class="ticker g" aria-label="قیمت‌های لحظه‌ای">
    <div class="ticker-track">
      <?php for ($rep = 0; $rep < 2; $rep++): foreach ($ticker as [$ic, $lbl, $val]): ?>
      <div class="tk"<?= $rep ? ' aria-hidden="true"' : '' ?>><?= siteIcon($ic) ?><span class="muted"><?= siteE($lbl) ?></span><b><?= siteE($val) ?></b><?php if ($d['live']): ?><span class="live"></span><?php endif; ?></div>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<!-- ═════════════ همه‌ی خدمات ═════════════ -->
<section class="sec" id="services">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= siteIcon('bag') ?> همه‌ی خدمات</span>
      <h2>هرچه برای <span class="gt">تلگرام و اینستاگرام</span> لازم دارید</h2>
      <p>یک حساب، یک کیف پول، خرید مستقیم از همین صفحه — سفارش در کمتر از یک دقیقه ثبت می‌شود.</p>
    </div>
    <div class="svc-grid">
      <?php
      $svcs = [
        ['#stars', 'star', 'o-gold', '#FBBF24', 'استارز تلگرام', 'برای گیفت، ری‌اکشن پولی و پرداخت داخل ربات‌ها و مینی‌اپ‌ها. هر مقدار که بخواهید.', $starFrom, 'هر استارز از', $hasStars],
        ['#premium', 'crown', 'o-vio', '#A855F7', 'تلگرام پریمیوم', 'فعال‌سازی مستقیم روی آیدی شما یا دوستتان، بدون نیاز به رمز یا کد ورود.', $premMin, 'از', (bool)$d['premium']],
        ['#gifts', 'gift', 'o-pink', '#EC4899', 'گیفت تلگرام', 'تدی، قلب، رز، جام، الماس و گیفت‌های کمیاب مارکت — مستقیم برای هر کسی.', $giftMin, 'از', (bool)$d['gifts']],
        ['#numbers', 'phone', 'o-grn', '#22C55E', 'شماره مجازی', 'شماره برای تلگرام، واتساپ و سرویس‌های دیگر؛ کد تایید همین‌جا نمایش داده می‌شود.', $d['num_from'], 'از', (bool)$d['countries']],
        ['#instagram', 'insta', 'o-ig', '#DD2A7B', 'فالوور اینستاگرام', 'فالوور، لایک، بازدید ریلز و کامنت — فقط با لینک پیج یا پست، بدون رمز.', $igMin, 'از', true],
        ['#growth', 'heart', 'o-tg', '#6366F1', 'ری‌اکشن و بازدید کانال', 'ری‌اکشن پست، بازدید استوری، اشتراک‌گذاری و ممبر برای رشد کانال تلگرام.', $grMin, 'از', (bool)$d['tgrowth']],
      ];
      foreach ($svcs as [$href, $ic, $orb, $c, $t, $p, $from, $fromLbl, $show]): if (!$show) continue; ?>
      <a class="svc g lift rv" href="<?= $href ?>" style="--c:<?= $c ?>">
        <span class="halo"></span>
        <span class="orb <?= $orb ?>"><?= siteIcon($ic) ?></span>
        <h3><?= siteE($t) ?></h3>
        <p><?= siteE($p) ?></p>
        <div class="svc-foot">
          <span><?php if ($from > 0): ?><span class="muted"><?= siteE($fromLbl) ?></span> <?= siteMoney($from) ?><?php else: ?><span class="ask">قیمت در ربات</span><?php endif; ?></span>
          <span class="svc-go">مشاهده <?= siteIcon('arrow') ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($hasStars): ?>
<!-- ═════════════ استارز ═════════════ -->
<section class="sec" id="stars">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker gold g"><?= siteIcon('star') ?> استارز تلگرام</span>
        <h2>خرید <span class="gt-gold">استارز تلگرام</span> با قیمت لحظه‌ای</h2>
        <p>استارز مستقیم به آیدی شما (یا هرکسی که بخواهید) واریز می‌شود. فقط یوزرنیم لازم است؛ هیچ‌وقت کد ورود را به کسی ندهید.</p>
      </div>
    </div>
    <div class="stars-lay">
      <?php if ($d['star_unit'] > 0): ?>
      <div class="calc g blur rv" id="starCalc" data-unit="<?= siteE((string)$d['star_unit']) ?>" data-min="<?= (int)$d['star_min'] ?>" data-max="<?= (int)min($d['star_max'], 1000000) ?>">
        <i class="ring"></i>
        <h3><?= siteIcon('star') ?> مقدار دلخواه</h3>
        <label for="starQty">چند استارز می‌خواهید؟</label>
        <div class="qty-box">
          <button type="button" data-step="-50" aria-label="کمتر">−</button>
          <input id="starQty" type="number" inputmode="numeric" min="<?= (int)$d['star_min'] ?>" step="1" value="<?= max((int)$d['star_min'], 500) ?>">
          <button type="button" data-step="50" aria-label="بیشتر">+</button>
        </div>
        <input type="range" id="starRange" min="<?= (int)$d['star_min'] ?>" max="10000" step="50" value="<?= max((int)$d['star_min'], 500) ?>" aria-label="مقدار استارز">
        <div class="quick">
          <?php foreach ([50, 100, 250, 500, 1000, 5000] as $q): if ($q < $d['star_min']) continue; ?>
          <button type="button" data-q="<?= $q ?>"><?= siteFa($q) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="calc-total">
          <div class="t">مبلغ قابل پرداخت</div>
          <div class="v"><span id="starTotal">—</span> <small>تومان</small></div>
          <div class="t" style="margin-top:4px">هر استارز <?= siteE(siteMoneyText($d['star_unit'])) ?></div>
        </div>
        <button type="button" class="btn btn-gold btn-lg btn-block" id="starBuy" <?= siteBuy($d['star_k'], 'استارز — مقدار دلخواه', '', 'آیدی تلگرام گیرنده + تعداد استارز') ?>><?= siteIcon('bolt') ?> خرید <span id="starBuyQ">—</span> استارز</button>
        <div class="calc-note"><?= siteIcon('shield') ?> حداقل <?= siteFa($d['star_min']) ?> استارز. مبلغ نهایی موقع پرداخت از نو محاسبه می‌شود.</div>
      </div>
      <?php endif; ?>
      <div<?= $d['star_unit'] > 0 ? '' : ' style="grid-column:1/-1"' ?>>
        <div class="pk-grid">
          <?php foreach ($d['stars'] as $s): $per = $s['qty'] > 0 && $s['price'] > 0 ? $s['price'] / $s['qty'] : 0; ?>
          <article class="pk g lift rv">
            <div class="pk-top">
              <span class="pk-star"><?= siteIcon('star') ?></span>
              <?php if ($s['badge'] !== ''): ?><span class="tag gold"><?= siteE($s['badge']) ?></span><?php endif; ?>
            </div>
            <div class="pk-q"><?= $s['qty'] > 0 ? siteFa($s['qty']) . ' <small>استارز</small>' : siteE($s['name']) ?></div>
            <div><?= siteMoney($s['price']) ?></div>
            <?php if ($per > 0): ?><div class="pk-unit">هر استارز <?= siteE(siteMoneyText($per)) ?></div><?php endif; ?>
            <button type="button" class="btn btn-glass btn-block" <?= siteBuy($s['k'], $s['name'], siteMoneyText($s['price']), 'آیدی تلگرام گیرنده') ?>>خرید <?= siteIcon('arrow') ?></button>
          </article>
          <?php endforeach; ?>
        </div>
        <div class="uses">
          <div class="use g rv"><?= siteIcon('gift') ?> ارسال گیفت به دوستان</div>
          <div class="use g rv"><?= siteIcon('heart') ?> ری‌اکشن پولی روی پست‌ها</div>
          <div class="use g rv"><?= siteIcon('bag') ?> پرداخت در ربات و مینی‌اپ</div>
          <div class="use g rv"><?= siteIcon('users') ?> حمایت از کانال‌ها</div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($d['premium']): ?>
<!-- ═════════════ پریمیوم ═════════════ -->
<section class="sec" id="premium">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker vio g"><?= siteIcon('crown') ?> تلگرام پریمیوم</span>
      <h2>اشتراک <span class="gt">تلگرام پریمیوم</span> — روی آیدی شما</h2>
      <p>بدون ورود به حساب، بدون رمز: فقط آیدی را بدهید، پریمیوم به‌صورت گیفت رسمی تلگرام فعال می‌شود.</p>
    </div>
    <div class="plans">
      <?php
      // پرچمِ «بهترین انتخاب» روی طولانی‌ترین اشتراک — همان که ماهانه ارزان‌تر درمی‌آید
      $hotIdx = 0;
      foreach ($d['premium'] as $ix => $p) if ($p['months'] > $d['premium'][$hotIdx]['months']) $hotIdx = $ix;
      foreach ($d['premium'] as $ix => $p):
        $hot = $ix === $hotIdx;
        $mo = $p['months'] > 0 && $p['price'] > 0 ? $p['price'] / $p['months'] : 0;
        $mo = $mo >= 10000 ? round($mo, -3) : round($mo, -2); ?>
      <article class="plan g lift rv<?= $hot ? ' hot' : '' ?>">
        <?php if ($hot): ?><i class="ring"></i><span class="plan-badge"><?= siteE($p['badge'] !== '' ? $p['badge'] : 'بهترین انتخاب') ?></span><?php endif; ?>
        <span class="orb o-vio"><?= siteIcon('crown') ?></span>
        <h3><?= siteE($p['name']) ?></h3>
        <p class="muted"><?= siteE($p['desc']) ?></p>
        <div class="plan-price"><?= siteMoney($p['price']) ?></div>
        <div class="plan-mo"><?= $mo > 0 ? 'ماهانه حدود ' . siteE(siteMoneyText($mo)) : '&nbsp;' ?></div>
        <ul>
          <li><?= siteIcon('check') ?> <?= $p['months'] > 0 ? siteFa($p['months']) . ' ماه اشتراک کامل' : 'اشتراک کامل پریمیوم' ?></li>
          <li><?= siteIcon('check') ?> فعال‌سازی با آیدی، بدون رمز</li>
          <li><?= siteIcon('check') ?> قابل خرید برای دوستان</li>
          <li><?= siteIcon('check') ?> همه‌ی امکانات پریمیوم</li>
        </ul>
        <button type="button" class="btn <?= $hot ? 'btn-pri' : 'btn-glass' ?> btn-lg btn-block" <?= siteBuy($p['k'], $p['name'], siteMoneyText($p['price']), 'آیدی تلگرام گیرنده') ?>>خرید اشتراک <?= siteIcon('arrow') ?></button>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="feats">
      <div class="feat g rv"><?= siteIcon('upload') ?><b>آپلود تا ۴ گیگابایت</b><span>فایل‌های حجیم را بدون تقسیم بفرستید.</span></div>
      <div class="feat g rv"><?= siteIcon('bolt') ?><b>دانلود با حداکثر سرعت</b><span>بدون محدودیت سرعت برای فایل و رسانه.</span></div>
      <div class="feat g rv"><?= siteIcon('mic') ?><b>تبدیل ویس به متن</b><span>پیام صوتی را بخوانید، بدون گوش دادن.</span></div>
      <div class="feat g rv"><?= siteIcon('noad') ?><b>بدون تبلیغات</b><span>تبلیغات کانال‌های عمومی حذف می‌شود.</span></div>
      <div class="feat g rv"><?= siteIcon('smile') ?><b>ایموجی و استیکر ویژه</b><span>ایموجی متحرک، استیکر اختصاصی و ایموجی وضعیت.</span></div>
      <div class="feat g rv"><?= siteIcon('folder') ?><b>محدودیت‌های دوبرابر</b><span>کانال، پوشه، پین و حساب‌های بیشتر.</span></div>
      <div class="feat g rv"><?= siteIcon('star') ?><b>نشان پریمیوم</b><span>نشان ویژه کنار نام شما در همه‌جا.</span></div>
      <div class="feat g rv"><?= siteIcon('globe') ?><b>ترجمه‌ی کل گفتگو</b><span>ترجمه‌ی فوری کانال‌ها و گفتگوها.</span></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($d['gifts']): ?>
<!-- ═════════════ گیفت ═════════════ -->
<section class="sec" id="gifts">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker pink g"><?= siteIcon('gift') ?> گیفت تلگرام</span>
        <h2>گیفت تلگرام برای <span class="gt">هر مناسبتی</span></h2>
        <p>گیفت مستقیم روی پروفایل گیرنده می‌نشیند. آیدی گیرنده را بدهید، بقیه با ما.</p>
      </div>
    </div>
    <div class="gift-grid">
      <?php foreach ($d['gifts'] as $g): ?>
      <article class="gift g lift rv">
        <div class="gift-e"><?= siteE($g['emoji']) ?></div>
        <h3><?= siteE($g['name']) ?></h3>
        <?php if ($g['stars'] > 0): ?><span class="st"><?= siteIcon('star') ?><?= siteFa($g['stars']) ?> استارز</span><?php endif; ?>
        <div><?= siteMoney($g['price']) ?></div>
        <button type="button" class="btn btn-glass" <?= siteBuy($g['k'], $g['name'], siteMoneyText($g['price']), $g['price'] > 0 ? 'آیدی تلگرام گیرنده' : 'نام گیفت موردنظر') ?>>ارسال گیفت</button>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($d['countries']): ?>
<!-- ═════════════ شماره مجازی ═════════════ -->
<section class="sec" id="numbers">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker grn g"><?= siteIcon('phone') ?> شماره مجازی</span>
        <h2>شماره مجازی <span class="gt">کشورهای مختلف</span></h2>
        <p>کشور را انتخاب کنید، شماره فوری تحویل می‌شود و کد تایید همین‌جا، به‌صورت زنده نمایش داده می‌شود.</p>
      </div>
    </div>
    <div class="num-bar rv">
      <label class="search g"><?= siteIcon('search') ?><input id="ctrySearch" type="search" placeholder="جستجوی کشور… مثلا آمریکا" aria-label="جستجوی کشور"></label>
      <div class="num-meta">
        <span class="g"><?= siteIcon('globe') ?><?= siteFa(count($d['countries'])) ?> کشور</span>
        <?php if ($d['num_total'] > 0): ?><span class="g"><?= siteIcon('phone') ?><?= siteFa($d['num_total']) ?> سرویس فعال</span><?php endif; ?>
      </div>
    </div>
    <div class="ctry-grid" id="ctryGrid">
      <?php foreach ($d['countries'] as $c): ?>
      <button type="button" class="ctry g lift" data-name="<?= siteE(mb_strtolower($c['name'])) ?>" data-num="<?= siteE($shop ? $c['cid'] : '') ?>" data-flag="<?= siteE($c['flag']) ?>"
        <?= siteBuy('', 'شماره مجازی ' . $c['name'], $c['from'] > 0 ? 'از ' . siteMoneyText($c['from']) : 'استعلام قیمت', 'انتخاب سرویس (تلگرام، واتساپ، …)') ?>>
        <span class="flag"><?= siteE($c['flag']) ?></span>
        <span class="ctry-t"><b><?= siteE($c['name']) ?></b><span><?= siteFa($c['n']) ?> سرویس</span></span>
        <span class="ctry-p"><?php if ($c['from'] > 0): ?>از<b class="amt"><?= siteFa($c['from']) ?></b>تومان<?php else: ?><span class="ask">استعلام</span><?php endif; ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="empty" id="ctryEmpty">کشوری با این نام در فهرست نیست — در ربات بین همه‌ی کشورها جستجو کنید.</div>
    <div class="num-steps">
      <div class="num-step g rv"><i>۱</i><div><b>کشور و سرویس را انتخاب کنید</b><span>تلگرام، واتساپ و سرویس‌های دیگر.</span></div></div>
      <div class="num-step g rv"><i>۲</i><div><b>شماره فوری تحویل می‌شود</b><span>شماره را در اپ موردنظر وارد کنید.</span></div></div>
      <div class="num-step g rv"><i>۳</i><div><b>کد تایید را همین‌جا بگیرید</b><span>کد به‌صورت زنده در همین صفحه نمایش داده می‌شود.</span></div></div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═════════════ اینستاگرام ═════════════ -->
<section class="sec" id="instagram">
  <div class="wrap">
    <div class="ig-band g">
      <span class="halo"></span><span class="halo b"></span>
      <div class="ig-top">
        <div class="rv">
          <span class="ig-logo"><?= siteIcon('insta') ?></span>
          <h2>افزایش <span class="gt-ig">فالوور، لایک و بازدید</span> اینستاگرام</h2>
          <p class="muted" style="margin-top:12px;max-width:560px;font-size:15.5px">پیج و پست‌هایتان را دیده‌تر کنید. فقط لینک پیج یا پست را بدهید — هیچ‌وقت رمز اینستاگرام از شما خواسته نمی‌شود. پیج باید در حالت عمومی (Public) باشد.</p>
          <div class="ig-points">
            <span class="g"><?= siteIcon('lock') ?> بدون نیاز به رمز</span>
            <span class="g"><?= siteIcon('clock') ?> شروع سریع</span>
            <span class="g"><?= siteIcon('refresh') ?> پیگیری سفارش</span>
          </div>
        </div>
        <?php if ($igQty): ?>
        <div class="ig-calc g blur rv" id="igCalc">
          <i class="ring" style="--r1:#F58529;--r2:#DD2A7B;--r3:#515BD4"></i>
          <h3>محاسبه‌ی سریع قیمت</h3>
          <label class="field"><span>نوع خدمت</span>
            <select id="igSvc" class="inp">
              <?php foreach ($igQty as $ix => $o): ?>
              <option value="<?= $ix ?>" data-k="<?= siteE($o['k']) ?>" data-unit="<?= siteE((string)$o['unit_price']) ?>" data-min="<?= siteE((string)$o['min']) ?>" data-max="<?= siteE((string)$o['max']) ?>"><?= siteE($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>تعداد</span><input id="igQty" class="inp ltr" type="number" inputmode="numeric" value="1000" min="1"></label>
          <div class="ig-total"><span class="muted">مبلغ</span><b><span id="igTotal">—</span> <small class="cur">تومان</small></b></div>
          <button type="button" class="btn btn-ig btn-lg btn-block" id="igBuy" <?= siteBuy('', 'خدمات اینستاگرام', '', 'لینک پیج یا پست + تعداد') ?>><?= siteIcon('insta') ?> ثبت سفارش</button>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($d['insta']): ?>
        <?php if (count($igKinds) > 1): ?>
        <div class="tabs" data-tabs="igGrid">
          <button type="button" class="tab on" data-k="all"><?= siteIcon('spark') ?> همه</button>
          <?php foreach (['follow', 'like', 'view', 'comment', 'other'] as $k): if (empty($igKinds[$k])) continue; ?>
          <button type="button" class="tab" data-k="<?= $k ?>"><?= siteIcon(siteKindIcon($k)) ?> <?= siteE(['follow' => 'فالوور', 'like' => 'لایک', 'view' => 'بازدید', 'comment' => 'کامنت'][$k] ?? 'سایر') ?></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="offer-grid" id="igGrid">
          <?php foreach ($d['insta'] as $o) echo siteOfferCard($o, 'ig'); ?>
        </div>
      <?php else: ?>
        <div class="offer-grid">
          <?php foreach ([['follow', 'فالوور اینستاگرام', 'فالوور برای پیج شخصی و کاری'], ['like', 'لایک پست و ریلز', 'لایک برای پست، ریلز و کاروسل'],
                          ['view', 'بازدید ریلز و استوری', 'بازدید ویدیو، ریلز و استوری'], ['comment', 'کامنت', 'کامنت برای پست‌ها و ریلزها']] as [$k, $t, $p]): ?>
          <article class="offer g rv">
            <div class="offer-top"><span class="orb o-ig"><?= siteIcon(siteKindIcon($k)) ?></span></div>
            <h3><?= siteE($t) ?></h3>
            <p class="muted"><?= siteE($p) ?></p>
            <div class="offer-price"><span class="ask">قیمت و موجودی در ربات</span></div>
            <button type="button" class="btn btn-glass btn-block" <?= siteBuy('', $t, '', 'لینک پیج یا پست + تعداد') ?>>سفارش در ربات <?= siteIcon('arrow') ?></button>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($d['tgrowth']): ?>
<!-- ═════════════ رشد کانال تلگرام ═════════════ -->
<section class="sec" id="growth">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker g"><?= siteIcon('heart') ?> رشد کانال تلگرام</span>
        <h2>ری‌اکشن، بازدید و <span class="gt">ممبر کانال</span></h2>
        <p>لینک پست یا استوری را بدهید؛ سفارش بعد از پرداخت بررسی و خودکار اجرا می‌شود.</p>
      </div>
    </div>
    <?php if (count($grKinds) > 1): ?>
    <div class="tabs" data-tabs="grGrid">
      <button type="button" class="tab on" data-k="all"><?= siteIcon('spark') ?> همه</button>
      <?php foreach (['follow', 'like', 'view', 'comment', 'other'] as $k): if (empty($grKinds[$k])) continue; ?>
      <button type="button" class="tab" data-k="<?= $k ?>"><?= siteIcon(siteKindIcon($k)) ?> <?= siteE(siteKindLabel($k)) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="offer-grid" id="grGrid">
      <?php foreach ($d['tgrowth'] as $o) echo siteOfferCard($o, 'tg'); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═════════════ مراحل خرید ═════════════ -->
<section class="sec" id="how">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= siteIcon('bolt') ?> مراحل خرید</span>
      <h2>خرید مستقیم در <span class="gt">چهار قدم</span></h2>
      <p>بدون ثبت‌نام و فرم طولانی — ورود فقط با تایید داخل تلگرام.</p>
    </div>
    <div class="steps">
      <div class="step rv"><div class="step-n g blur"><?= siteIcon('send') ?><i>۱</i></div><b>ورود با تلگرام</b><span>یک ضربه در ربات، و وارد شده‌اید — بدون رمز.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= siteIcon('bag') ?><i>۲</i></div><b>انتخاب خدمت</b><span>بسته را انتخاب و آیدی یا لینک را وارد کنید.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= siteIcon('wallet') ?><i>۳</i></div><b>پرداخت امن</b><span>از کیف پول؛ شارژ با درگاه یا کارت به کارت.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= siteIcon('check') ?><i>۴</i></div><b>تحویل خودکار</b><span>سفارش خودکار انجام و وضعیتش اطلاع داده می‌شود.</span></div>
    </div>
  </div>
</section>

<!-- ═════════════ چرا ما ═════════════ -->
<section class="sec" id="why">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker grn g"><?= siteIcon('shield') ?> چرا <?= siteE($name) ?>؟</span>
      <h2>خریدی که <span class="gt">خیالتان از آن راحت است</span></h2>
    </div>
    <div class="why">
      <div class="why-c g lift rv"><span class="orb o-gold"><?= siteIcon('bolt') ?></span><b>تحویل خودکار</b><span><?= siteE($tgNote) ?></span></div>
      <div class="why-c g lift rv"><span class="orb o-tg"><?= siteIcon('tag') ?></span><b>قیمت لحظه‌ای و شفاف</b><span>قیمت‌ها بر اساس نرخ روز به‌روز می‌شوند؛ مبلغ نهایی موقع پرداخت از نو و دقیق حساب می‌شود.</span></div>
      <div class="why-c g lift rv"><span class="orb o-grn"><?= siteIcon('lock') ?></span><b>بدون رمز، بدون کد ورود</b><span>فقط آیدی یا لینک لازم است. هیچ‌وقت رمز یا کد ورود حسابتان را از شما نمی‌خواهیم.</span></div>
      <div class="why-c g lift rv"><span class="orb o-vio"><?= siteIcon('refresh') ?></span><b>بازگشت وجه</b><span>سفارش‌ها خودکار پردازش می‌شوند؛ اگر مشکلی پیش بیاید مبلغ به کیف پول شما برمی‌گردد.</span></div>
      <div class="why-c g lift rv"><span class="orb o-pink"><?= siteIcon('wallet') ?></span><b>کیف پول مشترک</b><span>همان کیف پول ربات — در سایت و ربات یکی است؛ اطلاعات بانکی هیچ‌جا ذخیره نمی‌شود.</span></div>
      <div class="why-c g lift rv"><span class="orb o-ig"><?= siteIcon('headset') ?></span><b>پشتیبانی همیشه در دسترس</b><span>برای هر سوال یا مشکلی، از داخل ربات با پشتیبانی در ارتباط باشید.</span></div>
    </div>
    <?php
    $stats = [];
    if (count($d['countries']) > 0) $stats[] = [siteFa(count($d['countries'])), 'کشور برای شماره مجازی'];
    if ($services > 0)              $stats[] = [siteFa($services), 'خدمت و بسته‌ی فعال'];
    if ($d['stats']['users'] >= 100)  $stats[] = [siteFa($d['stats']['users']), 'کاربر ربات'];
    if ($d['stats']['orders'] >= 100) $stats[] = [siteFa($d['stats']['orders']), 'سفارش تحویل‌شده'];
    if ($stats): ?>
    <div class="stats">
      <?php foreach ($stats as [$v, $l]): ?><div class="stat g rv"><b class="shine"><?= $v ?></b><span><?= siteE($l) ?></span></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═════════════ روش‌های پرداخت ═════════════ -->
<section class="sec" id="pay">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= siteIcon('card') ?> روش‌های پرداخت</span>
      <h2>هر طور راحت‌ترید <span class="gt">پرداخت کنید</span></h2>
    </div>
    <div class="pay">
      <div class="pay-c g lift rv"><span class="orb o-tg"><?= siteIcon('wallet') ?></span><div><b>کیف پول</b><span>پرداخت با یک ضربه از موجودی</span></div></div>
      <?php if ($d['pay']['crypto'] || !$d['live']): ?>
      <div class="pay-c g lift rv"><span class="orb o-gold"><?= siteIcon('coin') ?></span><div><b>درگاه آنلاین</b><span>شارژ خودکار با ارز دیجیتال</span></div></div>
      <?php endif; ?>
      <?php if ($d['pay']['card'] || !$d['live']): ?>
      <div class="pay-c g lift rv"><span class="orb o-grn"><?= siteIcon('card') ?></span><div><b>کارت به کارت</b><span>شارژ کیف پول با کارت بانکی</span></div></div>
      <?php endif; ?>
      <?php if ($d['pay']['ton']): ?>
      <div class="pay-c g lift rv"><span class="orb" style="background:linear-gradient(135deg,#38BDF8,#0088CC)"><?= siteIcon('ton') ?></span><div><b>تون (TON)</b><span>خرید و انتقال تون به ولت شما</span></div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ═════════════ سوالات متداول ═════════════ -->
<section class="sec" id="faq">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= siteIcon('help') ?> سوالات متداول</span>
      <h2>جواب سوال‌های <span class="gt">پرتکرار</span></h2>
    </div>
    <div class="faq">
      <?php
      $faq = [
        ['خرید از سایت چطور انجام می‌شود؟', 'روی «ورود با تلگرام» بزنید؛ ربات باز می‌شود و با یک ضربه ورود را تایید می‌کنید. بعد هر خدمتی را همین‌جا انتخاب و از کیف پول پرداخت کنید — سفارش خودکار انجام می‌شود.'],
        ['برای ورود یا خرید رمز یا کد ورود لازم است؟', 'خیر. ورود فقط با تایید داخل ربات است و برای خرید هم فقط آیدی یا لینک لازم است. کد ورود تلگرام را هرگز به هیچ‌کس — حتی پشتیبانی — ندهید.'],
        ['کیف پول سایت با ربات یکی است؟', 'بله. همان حساب و همان کیف پول ربات است؛ هرچه در ربات شارژ کرده‌اید در سایت هم هست و برعکس.'],
        ['شارژ کیف پول چطور است؟', 'از حساب من یا هنگام خرید، «شارژ کیف پول» را بزنید. اگر درگاه آنلاین فعال باشد مستقیم به صفحه‌ی پرداخت می‌روید و کیف پول خودکار شارژ می‌شود؛ در غیر این صورت کارت به کارت کنید و رسید را داخل ربات بفرستید.'],
        ['تحویل سفارش چقدر طول می‌کشد؟', $tgNote . ' وضعیت هر سفارش را در «حساب من» می‌بینید.'],
        ['می‌توانم برای شخص دیگری استارز، پریمیوم یا گیفت بخرم؟', 'بله. کافی است هنگام خرید آیدی تلگرام او را وارد کنید؛ استارز، اشتراک یا گیفت مستقیم به حساب او می‌رسد.'],
        ['شماره مجازی چطور کار می‌کند؟', 'کشور و سرویس را انتخاب می‌کنید، شماره همین‌جا تحویل می‌شود و بعد از وارد کردنش در اپ، کد تایید به‌صورت زنده در همین صفحه نمایش داده می‌شود. تا پیش از رسیدن کد می‌توانید لغو کنید و مبلغ برمی‌گردد.'],
        ['اگر سفارشم انجام نشد چه می‌شود؟', 'سفارش‌ها خودکار پردازش می‌شوند؛ اگر مشکلی پیش بیاید مبلغ به کیف پول شما برمی‌گردد و از داخل ربات می‌توانید با پشتیبانی در ارتباط باشید.'],
      ];
      foreach ($faq as $ix => [$q, $a]): ?>
      <details class="g rv"<?= $ix === 0 ? ' open' : '' ?>>
        <summary><span class="q"><?= siteIcon('help') ?></span><?= siteE($q) ?><?= siteIcon('chev', 'chev') ?></summary>
        <p><?= siteE($a) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═════════════ CTA ═════════════ -->
<section class="wrap rv">
  <div class="cta">
    <div>
      <h2>همین حالا اولین سفارشتان را ثبت کنید</h2>
      <p>استارز، پریمیوم، گیفت، شماره مجازی و فالوور — خرید مستقیم، پرداخت امن، تحویل خودکار.</p>
    </div>
    <div class="cta-acts">
      <a class="btn btn-lg" href="#services"><?= siteIcon('bag') ?> شروع خرید</a>
      <?php if ($channel !== ''): ?><a class="btn btn-lg btn-line" href="<?= siteE($channel) ?>" target="_blank" rel="noopener">کانال ما</a><?php endif; ?>
    </div>
  </div>
</section>

</main>

<footer class="ftr g">
  <div class="wrap">
    <div class="ftr-grid">
      <div>
        <a href="#top" class="logo"><span class="logo-mark"><?= siteIcon('star') ?></span><span><?= siteE($name) ?></span></a>
        <p>فروشگاه خدمات تلگرام و اینستاگرام: استارز، پریمیوم، گیفت، شماره مجازی، فالوور و ری‌اکشن — خرید مستقیم با قیمت لحظه‌ای و تحویل خودکار.</p>
      </div>
      <div>
        <h4>خدمات</h4>
        <ul>
          <?php foreach ($nav as [$href, $label]): if ($href === '#faq') continue; ?><li><a href="<?= $href ?>"><?= siteE($label) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>راهنما</h4>
        <ul>
          <li><a href="#how">مراحل خرید</a></li>
          <li><a href="#pay">روش‌های پرداخت</a></li>
          <li><a href="#faq">سوالات متداول</a></li>
        </ul>
      </div>
      <div>
        <h4>ارتباط با ما</h4>
        <ul>
          <?php if ($bot !== ''): ?><li><a href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> @<?= siteE($d['bot']) ?></a></li><?php endif; ?>
          <?php if ($d['support'] !== ''): ?><li><a href="<?= siteE($support) ?>" target="_blank" rel="noopener"><?= siteIcon('headset') ?> پشتیبانی</a></li><?php endif; ?>
          <?php if ($channel !== ''): ?><li><a href="<?= siteE($channel) ?>" target="_blank" rel="noopener"><?= siteIcon('users') ?> کانال اطلاع‌رسانی</a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="ftr-bot">
      <span>© <?= $year ?> <?= siteE($name) ?> — همه‌ی حقوق محفوظ است.</span>
      <span>قیمت‌ها لحظه‌ای و بر اساس نرخ روز هستند.</span>
    </div>
  </div>
</footer>

<div class="fab">
  <button type="button" class="top g blur" id="toTop" aria-label="بازگشت به بالا"><?= siteIcon('up') ?></button>
  <?php if ($support !== ''): ?><a class="sup" href="<?= siteE($support) ?>" target="_blank" rel="noopener" aria-label="پشتیبانی"><?= siteIcon('headset') ?></a><?php endif; ?>
</div>

<div class="sheet" id="sheet" role="dialog" aria-modal="true" aria-label="خرید">
  <div class="sheet-bg" data-close></div>
  <div class="sheet-card g blur">
    <button type="button" class="icon-btn sheet-x" data-close aria-label="بستن"><?= siteIcon('close') ?></button>
    <div id="sheetBody"></div>
  </div>
</div>
<div class="toast g blur" id="toast" role="status" aria-live="polite"></div>

<script type="application/json" id="boot"><?= $bootJson ?></script>
<script>
(function(){
  'use strict';
  var $ = function(s, r){ return (r || document).querySelector(s); };
  var $$ = function(s, r){ return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var BOOT = {}; try { BOOT = JSON.parse($('#boot').textContent) || {}; } catch(e){}
  var ITEMS = BOOT.items || {};
  var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function fa(n, dec){ n = Number(n) || 0; try { return n.toLocaleString('fa-IR', {maximumFractionDigits: dec || 0}); } catch(e){ return String(Math.round(n)); } }
  function num(v){ v = String(v == null ? '' : v).replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٫]/g, '.').replace(/[^\d.]/g, ''); return Number(v) || 0; }
  function ic(id){ var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.setAttribute('class', 'ic'); s.setAttribute('aria-hidden', 'true');
    var u = document.createElementNS('http://www.w3.org/2000/svg', 'use'); u.setAttribute('href', '#i-' + id); s.appendChild(u); return s; }
  // سازنده‌ی DOM — متن همیشه با textContent، هرگز innerHTML
  function h(tag, attrs){
    var el = document.createElement(tag);
    if (attrs) for (var k in attrs) {
      var v = attrs[k]; if (v == null || v === false) continue;
      if (k === 'text') el.textContent = v;
      else if (k === 'on') { for (var ev in v) el.addEventListener(ev, v[ev]); }
      else if (k === 'cls') el.className = v;
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (var i = 2; i < arguments.length; i++) {
      var c = arguments[i]; if (c == null || c === false) continue;
      if (Array.isArray(c)) c.forEach(function(x){ if (x) el.appendChild(typeof x === 'string' ? document.createTextNode(x) : x); });
      else el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    }
    return el;
  }
  var toastT = 0;
  function toast(msg){ var t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(toastT); toastT = setTimeout(function(){ t.classList.remove('show'); }, 3200); }
  function copy(txt){
    function ok(){ toast('کپی شد'); }
    function fallback(){ var ta = h('textarea', {style: 'position:fixed;opacity:0'}); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch(e){} ta.remove(); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(ok, fallback);
    else fallback();
  }

  // ═════════ آسمانِ پرستاره با شهاب ═════════
  var root = document.documentElement;
  var sky = (function(){
    var c = $('#sky'), ctx = c && c.getContext ? c.getContext('2d') : null;
    var api = {theme: function(){}, mouse: function(){}};
    if (!ctx) return api;
    var dpr = Math.min(2, window.devicePixelRatio || 1), W = 0, H = 0, stars = [], shoots = [], mx = 0, my = 0, tx = 0, ty = 0, rgb = '255,255,255', raf = 0, next = 0;
    var cols = ['255,255,255', '255,214,120', '147,197,253', '216,180,254'];
    function readTheme(){ rgb = getComputedStyle(root).getPropertyValue('--star').trim() || '255,255,255'; }
    function size(){
      W = window.innerWidth; H = window.innerHeight;
      c.width = W * dpr; c.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.round(Math.min(190, W * H / 7500)); stars = [];
      for (var i = 0; i < n; i++) stars.push({x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.25 + .25,
        z: Math.random() * .9 + .1, p: Math.random() * 6.28, s: Math.random() * 1.6 + .4, c: cols[(Math.random() * cols.length) | 0]});
    }
    function draw(t){
      tx += (mx - tx) * .04; ty += (my - ty) * .04;
      ctx.clearRect(0, 0, W, H);
      var sy = (window.scrollY || 0) * .06, light = rgb !== '255,255,255';
      for (var i = 0; i < stars.length; i++) {
        var s = stars[i], a = .25 + .75 * (.5 + .5 * Math.sin(s.p + t * .001 * s.s));
        var x = s.x + tx * s.z * 14, y = ((s.y - sy * s.z) % H + H) % H + ty * s.z * 10;
        ctx.globalAlpha = a * (light ? .35 : 1);
        ctx.fillStyle = 'rgb(' + (light ? rgb : s.c) + ')';
        ctx.beginPath(); ctx.arc(x, y, s.r, 0, 6.2832); ctx.fill();
        if (s.r > 1.2 && !light) { ctx.globalAlpha = a * .18; ctx.beginPath(); ctx.arc(x, y, s.r * 4, 0, 6.2832); ctx.fill(); }
      }
      if (!light && !REDUCE && t > next) { next = t + 2600 + Math.random() * 4200;
        shoots.push({x: Math.random() * W * .8 + W * .2, y: Math.random() * H * .4, vx: -(6 + Math.random() * 4), vy: 2.2 + Math.random() * 1.5, l: 1}); }
      for (var j = shoots.length - 1; j >= 0; j--) {
        var m = shoots[j]; m.x += m.vx; m.y += m.vy; m.l -= .012;
        if (m.l <= 0) { shoots.splice(j, 1); continue; }
        var gr = ctx.createLinearGradient(m.x, m.y, m.x - m.vx * 16, m.y - m.vy * 16);
        gr.addColorStop(0, 'rgba(255,255,255,' + m.l + ')'); gr.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.globalAlpha = 1; ctx.strokeStyle = gr; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - m.vx * 16, m.y - m.vy * 16); ctx.stroke();
      }
      ctx.globalAlpha = 1;
    }
    function loop(t){ draw(t); raf = requestAnimationFrame(loop); }
    readTheme(); size();
    var rt = 0; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(function(){ size(); if (REDUCE) draw(0); }, 150); });
    if (REDUCE) draw(0);
    else {
      raf = requestAnimationFrame(loop);
      document.addEventListener('visibilitychange', function(){
        cancelAnimationFrame(raf); if (!document.hidden) raf = requestAnimationFrame(loop);
      });
    }
    api.theme = function(){ readTheme(); if (REDUCE) draw(0); };
    api.mouse = function(x, y){ mx = (x / W - .5) * 2; my = (y / H - .5) * 2; };
    return api;
  })();

  // ═════════ تم ═════════
  $('#theme').addEventListener('click', function(){
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('site-theme', next); } catch(e){}
    sky.theme();
  });

  // ═════════ منو و بالا ═════════
  var topBtn = $('#toTop');
  window.addEventListener('scroll', function(){ topBtn.classList.toggle('show', (window.scrollY || 0) > 700); }, {passive: true});
  topBtn.addEventListener('click', function(){ window.scrollTo({top: 0, behavior: 'smooth'}); });
  var drawer = $('#drawer'), mb = $('#menuBtn');
  mb.addEventListener('click', function(){ var o = drawer.classList.toggle('open'); mb.setAttribute('aria-expanded', o ? 'true' : 'false'); });
  $$('#drawer a').forEach(function(a){ a.addEventListener('click', function(){ drawer.classList.remove('open'); mb.setAttribute('aria-expanded', 'false'); }); });

  // ═════════ ظاهر شدن با اسکرول ═════════
  var rv = $$('.rv');
  if ('IntersectionObserver' in window && !REDUCE) {
    var io = new IntersectionObserver(function(es){
      es.forEach(function(e){ if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, {rootMargin: '0px 0px -40px 0px', threshold: 0.06});
    rv.forEach(function(el){ io.observe(el); });
  } else rv.forEach(function(el){ el.classList.add('in'); });

  // ═════════ نورِ دنبال‌کننده + اسپات‌لایتِ کارت‌ها ═════════
  var spot = $('#spot'), pend = 0;
  if (window.matchMedia && window.matchMedia('(hover: hover)').matches && !REDUCE) {
    document.addEventListener('pointermove', function(e){
      if (pend) return;
      pend = requestAnimationFrame(function(){
        pend = 0;
        spot.style.setProperty('--cx', e.clientX + 'px'); spot.style.setProperty('--cy', e.clientY + 'px');
        var g = e.target && e.target.closest ? e.target.closest('.g') : null;
        if (g) { var r = g.getBoundingClientRect(); g.style.setProperty('--mx', (e.clientX - r.left) + 'px'); g.style.setProperty('--my', (e.clientY - r.top) + 'px'); }
        sky.mouse(e.clientX, e.clientY);
      });
    }, {passive: true});
  }

  // ═════════ کلمه‌ی چرخان تیتر ═════════
  (function(){
    var el = $('#rot'); if (!el || REDUCE) return;
    var words = []; try { words = JSON.parse(el.getAttribute('data-words')) || []; } catch(e){}
    if (words.length < 2) return;
    var i = 0;
    setInterval(function(){
      el.classList.add('out');
      setTimeout(function(){
        i = (i + 1) % words.length; el.textContent = words[i];
        el.classList.remove('out'); el.classList.add('pre');
        void el.offsetWidth; el.classList.remove('pre');
      }, 350);
    }, 2600);
  })();

  // ═════════ ماشین‌حساب استارز ═════════
  var sc = $('#starCalc');
  if (sc) {
    var unit = Number(sc.getAttribute('data-unit')) || 0, smin = Number(sc.getAttribute('data-min')) || 1, smax = Number(sc.getAttribute('data-max')) || 1e6;
    var qi = $('#starQty'), rg = $('#starRange'), sbuy = $('#starBuy');
    var setQ = function(q, from){
      q = Math.max(smin, Math.min(smax, Math.round(q) || smin));
      if (from !== 'input') qi.value = q;
      rg.value = Math.min(Number(rg.max), q);
      $('#starTotal').textContent = fa(q * unit);
      $('#starBuyQ').textContent = fa(q);
      sbuy.setAttribute('data-qty', q);
      sbuy.setAttribute('data-name', fa(q) + ' استارز');
      sbuy.setAttribute('data-price', fa(q * unit) + ' تومان');
      $$('.quick button', sc).forEach(function(b){ b.classList.toggle('on', Number(b.getAttribute('data-q')) === q); });
    };
    qi.addEventListener('input', function(){ setQ(num(qi.value), 'input'); });
    qi.addEventListener('blur', function(){ setQ(num(qi.value)); });
    rg.addEventListener('input', function(){ setQ(Number(rg.value)); });
    $$('[data-step]', sc).forEach(function(b){ b.addEventListener('click', function(){ setQ(num(qi.value) + Number(b.getAttribute('data-step'))); }); });
    $$('.quick button', sc).forEach(function(b){ b.addEventListener('click', function(){ setQ(Number(b.getAttribute('data-q'))); }); });
    setQ(num(qi.value));
  }

  // ═════════ ماشین‌حساب اینستاگرام ═════════
  var ig = $('#igCalc');
  if (ig) {
    var sel = $('#igSvc'), iq = $('#igQty'), ib = $('#igBuy');
    var igUpd = function(){
      var o = sel.options[sel.selectedIndex]; if (!o) return;
      var u = Number(o.getAttribute('data-unit')) || 0, mn = Number(o.getAttribute('data-min')) || 1, mx2 = Number(o.getAttribute('data-max')) || 1e9;
      var q = Math.max(mn, Math.min(mx2, num(iq.value) || mn));
      iq.min = mn;
      $('#igTotal').textContent = fa(q * u);
      var k = o.getAttribute('data-k') || '';
      if (k) ib.setAttribute('data-item', k); else ib.removeAttribute('data-item');
      ib.setAttribute('data-qty', q);
      ib.setAttribute('data-name', o.textContent + ' — ' + fa(q) + ' عدد');
      ib.setAttribute('data-price', fa(q * u) + ' تومان');
    };
    sel.addEventListener('change', igUpd); iq.addEventListener('input', igUpd); igUpd();
  }

  // ═════════ تب‌ها و جستجوی کشور ═════════
  $$('[data-tabs]').forEach(function(bar){
    var grid = document.getElementById(bar.getAttribute('data-tabs'));
    bar.addEventListener('click', function(e){
      var t = e.target.closest('.tab'); if (!t) return;
      $$('.tab', bar).forEach(function(x){ x.classList.toggle('on', x === t); });
      var k = t.getAttribute('data-k');
      $$('.offer', grid).forEach(function(o){ o.classList.toggle('hide', k !== 'all' && o.getAttribute('data-kind') !== k); });
    });
  });
  var cs = $('#ctrySearch');
  if (cs) {
    var citems = $$('#ctryGrid .ctry'), cempty = $('#ctryEmpty');
    cs.addEventListener('input', function(){
      var q = cs.value.trim().toLowerCase(), n = 0;
      citems.forEach(function(it){ var ok = !q || it.getAttribute('data-name').indexOf(q) !== -1; it.style.display = ok ? '' : 'none'; if (ok) n++; });
      cempty.style.display = n ? 'none' : 'block';
    });
  }

  // ═══════════════════════════════════════════════
  // 🛒 خریدِ مستقیم
  // ═══════════════════════════════════════════════
  var S = {user: null, csrf: ''};
  var sheet = $('#sheet'), body = $('#sheetBody'), lastFocus = null, timers = [], after = null;

  function api(op, data){
    data = data || {};
    // مهمان تا وقتی کاری نکرده نشستی نمی‌سازد — توکن همین لحظه گرفته می‌شود
    if (op !== 'hello' && !S.csrf) return hello().then(function(){ return S.csrf ? api(op, data) : {ok: false, message: 'اتصال برقرار نشد.'}; });
    return fetch('?sapi=' + encodeURIComponent(op), {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-CSRF': S.csrf},
      body: JSON.stringify(data)
    }).then(function(r){
      return r.json().catch(function(){ return {ok: false, message: 'پاسخ نامعتبر از سرور.'}; }).then(function(j){ j._status = r.status; return j; });
    }, function(){ return {ok: false, _status: 0, message: 'اتصال برقرار نشد. اینترنت را بررسی کنید.'}; })
    .then(function(j){
      if (j && j.error === 'csrf' && !data.__retry) return hello().then(function(){ data.__retry = 1; return api(op, data); });
      if (j && j.error === 'login') setUser(null);
      return j;
    });
  }
  function shop(action, app, extra){ var b = {action: action, app: app}; for (var k in (extra || {})) b[k] = extra[k]; return api('api', b); }
  function hello(){ return api('hello', {}).then(function(j){ if (j && j.ok) { S.csrf = j.csrf; setUser(j.user); } return j; }); }
  function setUser(u){
    S.user = u || null;
    var lb = $('#loginBtn'), ab = $('#acctBtn'); if (!lb || !ab) return;
    lb.hidden = !!S.user; ab.hidden = !S.user;
    if (S.user) {
      $('#acctName').textContent = S.user.name;
      $('#acctBal').textContent = fa(S.user.balance) + ' تومان';
      $('#acctAv').textContent = (S.user.name || '؟').replace('@', '').charAt(0).toUpperCase();
    }
  }
  function setBal(b){ if (S.user && b != null) { S.user.balance = Number(b) || 0; setUser(S.user); } }

  function clearTimers(){ timers.forEach(function(t){ clearTimeout(t); clearInterval(t); }); timers = []; }
  function open(){ if (!sheet.classList.contains('open')) { lastFocus = document.activeElement; sheet.classList.add('open'); document.body.style.overflow = 'hidden'; } }
  function close(){ clearTimers(); sheet.classList.remove('open'); document.body.style.overflow = ''; after = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); }
  function render(){
    clearTimers(); body.textContent = '';
    for (var i = 0; i < arguments.length; i++) if (arguments[i]) body.appendChild(arguments[i]);
    open();
    var f = body.querySelector('input,textarea'); if (f && window.innerWidth > 640) f.focus();
  }
  sheet.addEventListener('click', function(e){ if (e.target.closest('[data-close]')) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && sheet.classList.contains('open')) close(); });

  function head(iconEl, title, sub){ return h('div', {cls: 's-head'}, iconEl, h('div', null, h('h3', {text: title}), sub ? h('div', {cls: 'muted', text: sub}) : null)); }
  function orb(id, cls){ return h('span', {cls: 'orb ' + (cls || 'o-tg')}, ic(id)); }
  function emo(e){ return h('span', {cls: 'emo', text: e || '✨'}); }
  function err(msg){ return h('div', {cls: 's-err'}, ic('alert'), h('span', {text: msg})); }
  function note(msg, id){ return h('div', {cls: 's-note'}, ic(id || 'shield'), h('span', {text: msg})); }
  function row(a, b, cls){ return h('div', {cls: 's-row' + (cls ? ' ' + cls : '')}, h('span', {cls: 'muted', text: a}), h('b', {text: b})); }
  function btn(label, cls, onclick, iconId){ return h('button', {type: 'button', cls: 'btn ' + (cls || 'btn-pri') + ' btn-lg btn-block', on: {click: onclick}}, iconId ? ic(iconId) : null, label); }
  function busy(b, on, label){
    if (on) { b.disabled = true; b._kids = Array.prototype.slice.call(b.childNodes); b.textContent = ''; b.appendChild(h('span', {cls: 'spin'})); if (label) b.appendChild(document.createTextNode(label)); }
    else { b.disabled = false; b.textContent = ''; (b._kids || []).forEach(function(n){ b.appendChild(n); }); }
  }
  function botLink(label){ return BOOT.bot ? h('a', {cls: 'btn btn-glass btn-lg btn-block', href: BOOT.bot, target: '_blank', rel: 'noopener'}, ic('send'), label || 'باز کردن ربات') : null; }
  // آیدی لاتین داخلِ متنِ راست‌به‌چپ: بدونِ ایزوله‌کردن، @ به تهِ آن می‌پرد
  function ltr(s){ return '\u2066' + s + '\u2069'; }
  function faDigits(s){ return String(s).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'[d]; }); }

  // ── ۰) خریدی که فقط داخلِ ربات است ──
  function viewBot(b){
    render(
      head(orb('bag'), b.getAttribute('data-name') || 'سفارش', b.getAttribute('data-price') || ''),
      h('div', {cls: 's-box'}, row('برای این سفارش لازم است', b.getAttribute('data-need') || 'اطلاعات سفارش')),
      note('این خدمت داخل ربات تلگرام خریده می‌شود: ربات را باز کنید، همین خدمت را انتخاب کنید و پرداخت کنید.', 'send'),
      h('div', {cls: 's-acts'}, botLink('ادامه در ربات') || err('ربات هنوز تنظیم نشده است.'))
    );
  }

  // ── ۱) ورود با تلگرام ──
  function expired(){ render(head(orb('clock'), 'زمان تایید تمام شد'), err('درخواست ورود منقضی شد.'), h('div', {cls: 's-acts'}, btn('دوباره', 'btn-pri', function(){ viewLogin(after); }, 'refresh'))); }
  function viewLogin(then){
    after = then || null;
    var go = btn('ورود با تلگرام', 'btn-pri', function(){ busy(go, true, ' در حال آماده‌سازی…'); startLogin(); }, 'send');
    render(
      head(orb('lock', 'o-tg'), 'ورود با تلگرام', 'بدون رمز — فقط یک تایید داخل ربات'),
      h('p', {cls: 'muted', style: 'font-size:14px', text: 'برای خرید مستقیم، با حساب تلگرامتان وارد شوید. کیف پول و سفارش‌ها همان حساب ربات است.'}),
      h('div', {cls: 's-acts'}, go),
      note('هیچ‌وقت رمز یا کد ورود تلگرام از شما خواسته نمی‌شود.')
    );
  }
  function startLogin(){
    api('login_start', {}).then(function(j){
      if (!j || !j.ok) { render(head(orb('lock'), 'ورود با تلگرام'), err((j && j.message) || 'ورود ممکن نشد.'), h('div', {cls: 's-acts'}, btn('تلاش دوباره', 'btn-glass', function(){ viewLogin(after); }))); return; }
      var left = j.ttl || 300, cd = h('span');
      render(
        head(orb('send', 'o-tg'), 'تایید ورود در ربات', 'کد زیر را با پیام ربات مقایسه کنید'),
        h('div', {cls: 's-box center'}, h('div', {cls: 'muted', style: 'font-size:13px', text: 'کد این دستگاه'}), h('div', {cls: 'big-code', text: j.code})),
        h('div', {cls: 's-acts'}, h('a', {cls: 'btn btn-pri btn-lg btn-block', href: j.link, target: '_blank', rel: 'noopener'}, ic('send'), 'باز کردن ربات و تایید')),
        h('div', {cls: 'wait'}, h('span', {cls: 'spin'}), h('span', {text: 'منتظر تایید در ربات… '}), cd),
        note('در ربات فقط وقتی «تایید ورود» را بزنید که کد پیام با کد بالا یکی باشد.')
      );
      var tick = function(){ left--; cd.textContent = '(' + fa(Math.max(0, left)) + ' ثانیه)'; if (left <= 0) expired(); };
      tick(); timers.push(setInterval(tick, 1000));
      var poll = function(){
        api('login_poll', {}).then(function(p){
          if (!sheet.classList.contains('open')) return;
          if (p && p.st === 'ok') { S.csrf = p.csrf; setUser(p.user); toast('خوش آمدید، ' + p.user.name); var fn = after; after = null; if (fn) fn(); else viewAccount(); return; }
          if (p && p.st === 'denied') { render(head(orb('lock'), 'ورود رد شد'), err('درخواست ورود در ربات رد شد.'), h('div', {cls: 's-acts'}, btn('دوباره', 'btn-glass', function(){ viewLogin(after); }))); return; }
          if (p && (p.st === 'expired' || p.st === 'none')) { expired(); return; }
          if (p && p.ok === false && p.message) { render(head(orb('lock'), 'ورود'), err(p.message)); return; }
          timers.push(setTimeout(poll, 2000));
        });
      };
      timers.push(setTimeout(poll, 2000));
    });
  }
  function needLogin(then){ if (S.user) then(); else viewLogin(then); }

  // ── ۲) فرم خرید ──
  var ASK_QTY = {qty: 1, qty_wallet: 1, qty_username: 1, qty_link: 1};
  function viewItem(k, preset){
    var it = ITEMS[k]; if (!it) { toast('این خدمت پیدا نشد.'); return; }
    var app = it.app, ask = it.ask || 'none', frac = ask === 'qty_wallet', isQ = !!ASK_QTY[ask];
    var qty = isQ ? Math.max(it.min || 1, num(preset && preset.qty) || it.min || 1) : 1;
    var coupon = '', disc = 0, errBox = h('div'), fields = h('div'), qInp = null, fInp = null;

    function setQty(v){ v = Math.max(it.min || 1, it.max > 0 ? Math.min(it.max, v) : v); qty = frac ? Math.round(v * 10000) / 10000 : Math.floor(v); qInp.value = String(qty); disc = 0; coupon = ''; sum(); }
    if (isQ) {
      qInp = h('input', {cls: 'inp', type: 'number', inputmode: frac ? 'decimal' : 'numeric', step: frac ? '0.01' : '1', min: String(it.min || 1)});
      qInp.value = String(qty);
      var stepv = frac ? 1 : Math.max(1, Math.round(it.min || 1));
      qInp.addEventListener('input', function(){ qty = frac ? num(qInp.value) : Math.floor(num(qInp.value)); disc = 0; coupon = ''; sum(); });
      fields.appendChild(h('label', {cls: 'field'}, h('span', {text: 'تعداد' + (it.unit ? ' (' + it.unit + ')' : '')}),
        h('div', {cls: 'qrow'},
          h('button', {type: 'button', text: '+', 'aria-label': 'بیشتر', on: {click: function(){ setQty(num(qInp.value) + stepv); }}}),
          qInp,
          h('button', {type: 'button', text: '−', 'aria-label': 'کمتر', on: {click: function(){ setQty(num(qInp.value) - stepv); }}})),
        h('div', {cls: 'hint', text: 'حداقل ' + fa(it.min, 4) + (it.max > it.min ? ' · حداکثر ' + fa(it.max, 4) : '')})));
    }

    var fLabel = {username: 'آیدی تلگرام گیرنده', qty_username: 'آیدی تلگرام گیرنده', wallet: 'آدرس ولت مقصد', qty_wallet: 'آدرس ولت مقصد',
                  qty_link: 'لینک پیج، پست یا استوری', text: 'توضیحات سفارش'}[ask];
    if (fLabel) {
      var isU = ask === 'username' || ask === 'qty_username';
      fInp = ask === 'text' ? h('textarea', {cls: 'inp', maxlength: '300', placeholder: 'مثلا نام گیفت موردنظر…'})
           : h('input', {cls: 'inp ltr', type: 'text', autocomplete: 'off', spellcheck: 'false', maxlength: '300',
                         placeholder: isU ? '@username' : (ask === 'qty_link' ? 'https://…' : 'UQ… / T…')});
      if (preset && preset.field) fInp.value = preset.field;
      var chips = (isU && S.user && S.user.uname) ? h('div', {cls: 'chips'}, h('button', {type: 'button', cls: 'chip', text: 'برای خودم (' + ltr('@' + S.user.uname) + ')', on: {click: function(){ fInp.value = '@' + S.user.uname; }}})) : null;
      fields.appendChild(h('label', {cls: 'field'}, h('span', {text: fLabel}), fInp, chips));
    }

    // کد تخفیف — فقط پیش‌نمایش؛ سرور موقعِ سفارش از نو حسابش می‌کند
    var cInp = h('input', {cls: 'inp', type: 'text', placeholder: 'کد تخفیف', maxlength: '40'});
    var cBtn = h('button', {type: 'button', cls: 'btn btn-glass', text: 'اعمال'});
    var cWrap = h('div', {cls: 'cpn', hidden: true}, cInp, cBtn);
    var cToggle = h('button', {type: 'button', cls: 'link-btn', text: 'کد تخفیف دارید؟', on: {click: function(){ cWrap.hidden = false; cToggle.hidden = true; cInp.focus(); }}});
    cBtn.addEventListener('click', function(){
      var code = cInp.value.trim(); if (!code) return;
      busy(cBtn, true);
      shop('coupon_check', app, {code: code, subtotal: subtotal()}).then(function(j){
        busy(cBtn, false);
        if (j && j.ok) { coupon = j.code || code; disc = Number(j.discount) || 0; toast('کد تخفیف اعمال شد'); }
        else { coupon = ''; disc = 0; toast((j && j.message) || 'کد تخفیف معتبر نیست.'); }
        sum();
      });
    });

    var sumBox = h('div', {cls: 's-box'});
    function subtotal(){ return Math.ceil((it.price || 0) * (isQ ? qty : 1)); }
    function sum(){
      sumBox.textContent = '';
      if (isQ) sumBox.appendChild(row('قیمت واحد', fa(it.price, 2) + ' تومان'));
      if (disc > 0) sumBox.appendChild(row('تخفیف', '−' + fa(disc) + ' تومان', 'disc'));
      sumBox.appendChild(row('مبلغ قابل پرداخت', it.price > 0 ? fa(Math.max(0, subtotal() - disc)) + ' تومان' : 'استعلام قیمت', 'total'));
      if (S.user) sumBox.appendChild(row('موجودی کیف پول', fa(S.user.balance) + ' تومان'));
    }
    sum();

    var pay = btn('پرداخت از کیف پول', 'btn-pri', function(){
      errBox.textContent = '';
      var field = fInp ? fInp.value.trim() : '';
      if (fInp && !field) { errBox.appendChild(err('لطفا ' + fLabel + ' را وارد کنید.')); return; }
      if (isQ && (!qty || qty < (it.min || 1))) { errBox.appendChild(err('حداقل مقدار ' + fa(it.min, 4) + ' است.')); return; }
      busy(pay, true, ' در حال پرداخت…');
      shop('order', app, {item: it.id, qty: qty, field: field, coupon: coupon, seen_price: it.price}).then(function(j){
        busy(pay, false);
        if (j && j.ok) { setBal(j.balance); if (j.num) viewNum(j.num, it); else viewDone(j, it); return; }
        var e = (j && j.error) || '', again = function(){ viewItem(k, {qty: qty, field: field}); };
        if (e === 'login') { viewLogin(again); return; }
        if (e === 'no_balance') { setBal(j.balance); viewTopup(j.need, again); return; }
        if (e === 'price_changed' && j.price) { it.price = Number(j.price); disc = 0; coupon = ''; sum(); errBox.appendChild(err('قیمت به‌روز شد؛ مبلغ جدید را ببینید و دوباره پرداخت کنید.')); return; }
        if (e === 'join_required') { errBox.appendChild(err(j.message || 'اول در کانال‌های ربات عضو شوید.')); var bl = botLink('عضویت از داخل ربات'); if (bl) errBox.appendChild(bl); return; }
        errBox.appendChild(err((j && j.message) || 'ثبت سفارش انجام نشد. دوباره تلاش کنید.'));
      });
    }, 'wallet');

    render(
      head(it.emoji ? emo(it.emoji) : orb('bag'), it.name, it.price > 0 ? (isQ ? 'هر ' + (it.unit || 'عدد') + ' ' + fa(it.price, 2) + ' تومان' : fa(it.price) + ' تومان') : ''),
      fields, sumBox, h('div', null, cToggle, cWrap), errBox,
      h('div', {cls: 's-acts'}, pay),
      note('مبلغ نهایی سمت سرور و با نرخ همین لحظه حساب می‌شود؛ اگر سفارش انجام نشود، مبلغ به کیف پول برمی‌گردد.')
    );
  }

  // ── ۳) موفق ──
  function viewDone(j, it){
    render(
      h('div', {cls: 'okmark'}, ic('check')),
      h('h3', {cls: 'center', style: 'font-size:21px;font-weight:900', text: j.done ? 'سفارش انجام شد' : 'پرداخت انجام شد'}),
      h('p', {cls: 'center muted', style: 'margin-top:6px', text: j.message || 'سفارش در حال پردازش است.'}),
      h('div', {cls: 's-box'}, row('خدمت', it.name), row('مبلغ', fa(j.total) + ' تومان'), j.discount > 0 ? row('تخفیف', fa(j.discount) + ' تومان', 'disc') : null,
        row('کد سفارش', j.order || '—'), row('موجودی کیف پول', fa(j.balance) + ' تومان')),
      h('div', {cls: 's-acts two'}, btn('سفارش‌های من', 'btn-glass', viewOrders, 'list'), btn('خرید دیگر', 'btn-pri', close, 'bag'))
    );
  }

  // ── ۴) شارژ کیف پول ──
  function viewTopup(need, then){
    var min = Number((BOOT.pay || {}).min) || 10000;
    var amt = Math.max(min, Math.ceil((Number(need) || 0) / 1000) * 1000);
    var inp = h('input', {cls: 'inp ltr', type: 'number', inputmode: 'numeric', min: String(min), step: '1000'}); inp.value = String(amt);
    var chips = h('div', {cls: 'chips'});
    [amt, 100000, 200000, 500000, 1000000].filter(function(v, i, a){ return v >= min && a.indexOf(v) === i; }).slice(0, 5).forEach(function(v){
      chips.appendChild(h('button', {type: 'button', cls: 'chip', text: fa(v), on: {click: function(){ inp.value = String(v); }}}));
    });
    var e = h('div');
    var go = btn('ادامه‌ی شارژ', 'btn-grn', function(){
      e.textContent = '';
      var a = Math.round(num(inp.value));
      if (a < min) { e.appendChild(err('حداقل مبلغ شارژ ' + fa(min) + ' تومان است.')); return; }
      busy(go, true, ' در حال ساخت فاکتور…');
      shop('topup', 'unified', {amount: a}).then(function(j){
        busy(go, false);
        if (!j || !j.ok) { if (j && j.error === 'login') { viewLogin(function(){ viewTopup(need, then); }); return; } e.appendChild(err((j && j.message) || 'ثبت درخواست شارژ انجام نشد.')); return; }
        viewTopupDone(j, then);
      });
    }, 'plus');
    render(
      head(orb('wallet', 'o-grn'), 'شارژ کیف پول', need > 0 ? 'برای این خرید ' + fa(need) + ' تومان کم دارید' : 'موجودی فعلی: ' + fa(S.user ? S.user.balance : 0) + ' تومان'),
      h('label', {cls: 'field'}, h('span', {text: 'مبلغ شارژ (تومان)'}), inp, chips), e,
      h('div', {cls: 's-acts'}, go),
      note((BOOT.pay || {}).crypto ? 'پرداخت آنلاین با درگاه؛ کیف پول بعد از پرداخت خودکار شارژ می‌شود.' : 'بعد از ثبت، شماره کارت نمایش داده می‌شود و رسید را داخل ربات می‌فرستید.', 'wallet')
    );
  }
  function viewTopupDone(j, then){
    var url = String(j.pay_url || '');
    var check = btn('بررسی موجودی', 'btn-glass', function(){
      busy(check, true);
      hello().then(function(){ busy(check, false); toast('موجودی: ' + fa(S.user ? S.user.balance : 0) + ' تومان'); if (then) then(); });
    }, 'refresh');
    if (/^https:\/\//i.test(url)) {
      render(
        head(orb('coin', 'o-gold'), 'پرداخت آنلاین', fa(j.amount) + ' تومان'),
        h('div', {cls: 's-acts'}, h('a', {cls: 'btn btn-gold btn-lg btn-block', href: url, target: '_blank', rel: 'noopener noreferrer'}, ic('card'), 'رفتن به صفحه‌ی پرداخت')),
        note('بعد از پرداخت، کیف پول خودکار شارژ می‌شود و خبرش در ربات هم می‌آید. بعد «بررسی موجودی» را بزنید.', 'check'),
        h('div', {cls: 's-acts'}, check),
        h('div', {cls: 's-box'}, row('کد پیگیری', j.order || '—'))
      );
      return;
    }
    render(
      head(orb('card', 'o-grn'), 'کارت به کارت', fa(j.amount) + ' تومان'),
      j.card ? h('div', {cls: 'field'}, h('span', {text: 'شماره کارت'}), h('div', {cls: 'copy'}, h('span', {text: j.card}),
        h('button', {type: 'button', cls: 'btn btn-glass btn-sm', on: {click: function(){ copy(String(j.card).replace(/\s+/g, '')); }}}, ic('copy'), 'کپی'))) : null,
      h('div', {cls: 's-box'}, j.holder ? row('به نام', j.holder) : null, row('مبلغ', fa(j.amount) + ' تومان'), row('کد پیگیری', j.order || '—')),
      note('مبلغ را واریز کنید، بعد در ربات روی «ارسال رسید» بزنید و عکس رسید را بفرستید — فاکتورش همین حالا در ربات برایتان ارسال شد. بعد از تایید، کیف پول شارژ می‌شود.', 'send'),
      h('div', {cls: 's-acts'}, botLink('ارسال رسید در ربات'), check)
    );
  }

  // ── ۵) شماره مجازی: انتخاب سرویس ← شماره و کد ──
  function viewCountry(cid, cname, flag){
    render(head(emo(flag), 'شماره مجازی ' + cname, 'در حال دریافت سرویس‌ها…'), h('div', {cls: 'wait'}, h('span', {cls: 'spin'}), h('span', {text: 'لطفا صبر کنید'})));
    shop('num_cat', 'num', {cat: cid}).then(function(j){
      if (!j || !j.ok) { if (j && j.error === 'login') { viewLogin(function(){ viewCountry(cid, cname, flag); }); return; } render(head(emo(flag), 'شماره مجازی ' + cname), err((j && j.message) || 'دریافت سرویس‌ها ممکن نشد.')); return; }
      var list = h('div', {cls: 'svc-pick'});
      (j.items || []).forEach(function(x){
        var k = 'num:' + x.id;
        ITEMS[k] = {app: 'num', id: x.id, name: 'شماره ' + cname + ' — ' + x.name, emoji: flag, price: Number(x.price) || 0, ask: x.ask || 'none', min: Number(x.min) || 1, max: Number(x.max) || 1, unit: x.unit || ''};
        list.appendChild(h('button', {type: 'button', on: {click: function(){ viewItem(k); }}},
          h('span', null, h('b', {text: x.name}), x.desc ? h('small', {text: x.desc}) : null),
          h('b', {text: Number(x.price) > 0 ? fa(x.price) + ' تومان' : 'استعلام'})));
      });
      render(head(emo(flag), 'شماره مجازی ' + cname, 'سرویس را انتخاب کنید'),
        (j.items || []).length ? list : err('فعلا شماره‌ای برای این کشور موجود نیست.'),
        note('بعد از پرداخت، شماره همین‌جا نمایش داده می‌شود و کد تایید به‌صورت زنده می‌آید.', 'phone'));
    });
  }
  function viewNum(st, it){
    var box = h('div');
    function draw(s){
      box.textContent = '';
      var waiting = s.status === 'waiting', code = s.code || '';
      box.appendChild(h('div', {cls: 's-box center'}, h('div', {cls: 'muted', style: 'font-size:13px', text: 'شماره‌ی شما'}),
        h('div', {cls: 'num-phone', text: s.phone || '—'}),
        s.phone ? h('button', {type: 'button', cls: 'btn btn-glass btn-sm', style: 'margin-top:8px', on: {click: function(){ copy(String(s.phone).replace(/\s+/g, '')); }}}, ic('copy'), 'کپی شماره') : null));
      if (code) box.appendChild(h('div', {cls: 's-box center'}, h('div', {cls: 'muted', style: 'font-size:13px', text: 'کد تایید'}), h('div', {cls: 'big-code', text: code}),
        h('button', {type: 'button', cls: 'btn btn-grn btn-sm', on: {click: function(){ copy(code); }}}, ic('copy'), 'کپی کد')));
      else if (waiting) box.appendChild(h('div', {cls: 'wait'}, h('span', {cls: 'spin'}),
        h('span', {text: 'منتظر رسیدن کد… ' + (s.left > 0 ? '(' + faDigits(Math.floor(s.left / 60) + ':' + ('0' + (s.left % 60)).slice(-2)) + ')' : '')})));
      var acts = h('div', {cls: 's-acts two'});
      if (waiting && !code) {
        acts.appendChild(btn('دریافت کد', 'btn-pri', function(e){ var b = e.currentTarget; busy(b, true); shop('num_code', 'num', {order: s.order}).then(function(j){ busy(b, false); if (j && j.ok && j.num) draw(j.num); else toast((j && j.message) || 'هنوز کدی نرسیده.'); }); }, 'refresh'));
        acts.appendChild(btn('لغو و بازگشت وجه', 'btn-glass', function(e){ var b = e.currentTarget; if (!window.confirm('شماره لغو شود و مبلغ برگردد؟')) return; busy(b, true);
          shop('num_cancel', 'num', {order: s.order}).then(function(j){ busy(b, false); if (j && j.ok) { setBal(j.balance); toast('لغو شد؛ مبلغ برگشت.'); close(); } else toast((j && j.message) || 'لغو ممکن نشد.'); }); }, 'close'));
      } else acts.appendChild(btn('بستن', 'btn-glass', close));
      box.appendChild(acts);
    }
    render(head(emo(it.emoji), it.name, 'شماره تحویل شد'), box, note('شماره را در اپ موردنظر وارد کنید؛ کد به‌محض رسیدن همین‌جا نمایش داده می‌شود.', 'phone'));
    draw(st);
    var poll = function(){
      shop('num_state', 'num', {order: st.order}).then(function(j){
        if (!sheet.classList.contains('open')) return;
        if (j && j.ok && j.num) { st = j.num; draw(st); }
        if (st.status === 'waiting' && !st.code) timers.push(setTimeout(poll, 5000));
      });
    };
    if (st.status === 'waiting' && !st.code) timers.push(setTimeout(poll, 5000));
  }

  // ── ۶) حساب من و سفارش‌ها ──
  function viewAccount(){
    if (!S.user) { viewLogin(); return; }
    render(
      head(h('span', {cls: 'orb o-tg', style: 'font-size:22px;font-weight:900', text: (S.user.name || '؟').replace('@', '').charAt(0).toUpperCase()}), S.user.name, S.user.uname ? ltr('@' + S.user.uname) : 'حساب تلگرام'),
      h('div', {cls: 'bal'}, h('div', null, h('div', {cls: 'muted', style: 'font-size:13px', text: 'موجودی کیف پول'}), h('b', {text: fa(S.user.balance) + ' تومان'})), orb('wallet', 'o-grn')),
      h('div', {cls: 's-acts two'}, btn('شارژ کیف پول', 'btn-grn', function(){ viewTopup(0); }, 'plus'), btn('سفارش‌های من', 'btn-glass', viewOrders, 'list')),
      h('div', {cls: 's-acts'}, btn('خروج از حساب', 'btn-glass', function(){ api('logout', {}).then(function(j){ if (j && j.csrf) S.csrf = j.csrf; setUser(null); toast('خارج شدید'); close(); }); }, 'logout'))
    );
  }
  function viewOrders(){
    render(head(orb('list'), 'سفارش‌های من', 'آخرین سفارش‌های سایت و مینی‌اپ'), h('div', {cls: 'wait'}, h('span', {cls: 'spin'}), h('span', {text: 'در حال دریافت…'})));
    shop('me_all', 'unified', {}).then(function(j){
      if (!j || !j.ok) { if (j && j.error === 'login') { viewLogin(viewOrders); return; } render(head(orb('list'), 'سفارش‌های من'), err((j && j.message) || 'دریافت سفارش‌ها ممکن نشد.')); return; }
      setBal(j.balance);
      var list = h('div', {cls: 'olist'});
      (j.orders || []).forEach(function(o){
        list.appendChild(h('div', {cls: 'oitem'}, h('span', {cls: 'e', text: o.emoji || '•'}),
          h('div', {cls: 't'}, h('b', {text: o.name}), h('span', {text: (o.date || '') + (o.id ? ' · ' + o.id : '')})),
          h('div', {cls: 'p'}, h('b', {text: fa(o.total) + ' تومان'}), h('span', {text: o.status || ''}))));
      });
      render(head(orb('list'), 'سفارش‌های من', 'موجودی: ' + fa(j.balance) + ' تومان'),
        (j.orders || []).length ? list : h('p', {cls: 'muted center', style: 'padding:24px 0', text: 'هنوز سفارشی ثبت نکرده‌اید.'}),
        h('div', {cls: 's-acts'}, btn('بازگشت به حساب', 'btn-glass', viewAccount, 'back')));
    });
  }

  // ── کلیک روی هر دکمه‌ی خرید ──
  document.addEventListener('click', function(e){
    var b = e.target.closest('[data-buy]'); if (!b) return;
    e.preventDefault();
    var k = b.getAttribute('data-item'), cid = b.getAttribute('data-num');
    if (BOOT.shop && cid) { var nm = (b.querySelector('.ctry-t b') || {}).textContent || ''; needLogin(function(){ viewCountry(cid, nm, b.getAttribute('data-flag') || ''); }); return; }
    if (BOOT.shop && k && ITEMS[k]) { needLogin(function(){ viewItem(k, {qty: b.getAttribute('data-qty')}); }); return; }
    viewBot(b);
  });
  var lb = $('#loginBtn'); if (lb) lb.addEventListener('click', function(){ viewLogin(); });
  var ab = $('#acctBtn'); if (ab) ab.addEventListener('click', viewAccount);
  if (BOOT.shop && /(?:^|;\s*)shop_in=1/.test(document.cookie)) hello();
})();
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
