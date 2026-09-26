<?php
/**
 * 🎨 نمای سایتِ فروشگاه — همه‌ی HTML/CSS/JSِ صفحه‌ی اصلی (index.php)
 *
 * قاعده‌های این فایل:
 *   • صفحه سمتِ سرور ساخته می‌شود، نه با جاوااسکریپت — موتورِ جستجو
 *     همه‌ی قیمت‌ها و متن‌ها را می‌بیند و صفحه بدونِ JS هم کامل است.
 *     JS فقط برای ماشین‌حساب، فیلتر، تب، منوی موبایل و پنجره‌ی خرید است.
 *   • هر داده‌ای که از پنل می‌آید (اسم، توضیح، ایموجی) از siteE رد
 *     می‌شود؛ JS هم فقط با textContent می‌نویسد، هرگز innerHTML.
 *   • آیکون‌ها SVGِ درون‌خطی‌اند، نه فونت یا فایلِ بیرونی؛ تنها چیزِ
 *     بیرونی فونتِ وزیرمتن است که اگر نیامد، فونتِ سیستم جایش می‌نشیند.
 *   • تمِ روشن/تیره: پیش‌فرض از سیستمِ کاربر، با دکمه عوض می‌شود.
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
    if ((float)$n <= 0) return '<span class="ask">استعلام در ربات</span>';
    $dec = in_array($cur, ['تومان', 'ریال'], true) ? 0 : 2;
    return '<b class="amt">' . siteFa($n, $dec) . '</b> <small class="cur">' . siteE($cur) . '</small>';
}

/** همان قیمت، متنِ ساده — برای data-attributeها و پنجره‌ی خرید */
function siteMoneyText($n, $cur = 'تومان') {
    if ((float)$n <= 0) return 'استعلام در ربات';
    $dec = in_array($cur, ['تومان', 'ریال'], true) ? 0 : 2;
    return siteFa($n, $dec) . ' ' . $cur;
}

function siteIcon($id, $cls = '') {
    return '<svg class="ic' . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#i-' . $id . '"/></svg>';
}

/** ویژگی‌های دکمه‌ی خرید — پنجره‌ی خرید فقط همین‌ها را می‌خواند */
function siteBuy($name, $priceText, $need) {
    return 'data-buy data-name="' . siteE($name) . '" data-price="' . siteE($priceText) .
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

/** برچسبِ تب‌های رشد */
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
    <article class="offer rv" data-kind="<?= siteE($o['kind']) ?>">
      <div class="offer-top">
        <span class="offer-ic <?= $platform ?>"><?= siteIcon(siteKindIcon($o['kind'])) ?></span>
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
      <button type="button" class="btn btn-soft btn-block" <?= siteBuy($o['name'], $priceTxt, $need) ?>>ثبت سفارش <?= siteIcon('arrow') ?></button>
    </article>
    <?php return ob_get_clean();
}

function siteView($d) {
    $bot     = siteBotUrl($d);
    $name    = (string)$d['name'];
    $support = $d['support'] !== '' ? 'https://t.me/' . rawurlencode($d['support']) : $bot;
    $channel = $d['channel'] !== '' ? 'https://t.me/' . rawurlencode($d['channel']) : '';

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

    $tgNote = 'سفارش بعد از تایید پرداخت، خودکار پردازش و تحویل می‌شود.';
    // سالِ شمسی — نوروز حدودِ ۲۱ مارس است؛ یک روز جابه‌جایی برای سالِ کپی‌رایت مهم نیست
    $gy = (int)date('Y');
    $year = siteFa((int)date('n') > 3 || ((int)date('n') === 3 && (int)date('j') >= 21) ? $gy - 621 : $gy - 622);
    $desc = 'خرید استارز تلگرام، پریمیوم، گیفت، شماره مجازی و فالوور اینستاگرام با قیمت لحظه‌ای، پرداخت امن و تحویل خودکار — ' . $name;

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
<meta name="theme-color" content="#070B16">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2AABEE"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient></defs><rect width="64" height="64" rx="18" fill="url(#g)"/><path d="M32 13l5.6 11.4 12.6 1.8-9.1 8.9 2.1 12.5L32 41.7l-11.2 5.9 2.1-12.5-9.1-8.9 12.6-1.8z" fill="#fff"/></svg>') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<script>
(function(){var t=null;try{t=localStorage.getItem('site-theme')}catch(e){}
if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);
document.documentElement.classList.add('js');})();
</script>
<style>
:root{
  --bg:#060A14; --bg2:#0A1020; --card:#0D1427; --card2:#111A31; --line:rgba(148,163,209,.13); --line2:rgba(148,163,209,.24);
  --ink:#EEF2FF; --ink2:#C7D0E8; --dim:#8591B2; --glass:rgba(10,16,32,.72);
  --sh:0 18px 40px -18px rgba(0,0,0,.65); --sh2:0 30px 60px -24px rgba(0,0,0,.75);
  --blue:#2F6FED; --sky:#2AABEE; --gold:#FFB224; --amber:#FF7A00; --violet:#8B5CF6; --pink:#EC4899;
  --red:#F23557; --green:#17C978; --cyan:#22D3EE;
  --grad:linear-gradient(135deg,#2AABEE 0%,#2F6FED 45%,#8B5CF6 100%);
  --gold-g:linear-gradient(135deg,#FFD66B 0%,#FFB224 45%,#FF7A00 100%);
  --vio-g:linear-gradient(135deg,#C084FC 0%,#8B5CF6 50%,#6366F1 100%);
  --pink-g:linear-gradient(135deg,#FB7185 0%,#EC4899 50%,#A855F7 100%);
  --grn-g:linear-gradient(135deg,#5EEAD4 0%,#17C978 55%,#059669 100%);
  --ig:linear-gradient(45deg,#F58529 0%,#DD2A7B 40%,#8134AF 75%,#515BD4 100%);
  --r:18px; --r2:26px;
  color-scheme:dark;
}
:root[data-theme=light]{
  --bg:#F4F6FB; --bg2:#FFFFFF; --card:#FFFFFF; --card2:#F2F5FB; --line:rgba(15,23,42,.08); --line2:rgba(15,23,42,.15);
  --ink:#0A1124; --ink2:#26304C; --dim:#5D6985; --glass:rgba(255,255,255,.8);
  --sh:0 14px 34px -18px rgba(30,41,80,.28); --sh2:0 30px 60px -28px rgba(30,41,80,.35);
  color-scheme:light;
}
@media (prefers-color-scheme:light){
  :root:not([data-theme=dark]){
    --bg:#F4F6FB; --bg2:#FFFFFF; --card:#FFFFFF; --card2:#F2F5FB; --line:rgba(15,23,42,.08); --line2:rgba(15,23,42,.15);
    --ink:#0A1124; --ink2:#26304C; --dim:#5D6985; --glass:rgba(255,255,255,.8);
    --sh:0 14px 34px -18px rgba(30,41,80,.28); --sh2:0 30px 60px -28px rgba(30,41,80,.35);
    color-scheme:light;
  }
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{background:var(--bg);color:var(--ink);font-family:Vazirmatn,Vazir,'IRANSans',system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;
  font-size:15.5px;line-height:1.85;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button,input,select{font:inherit;color:inherit}
img,svg{display:block}
.ic{width:1.15em;height:1.15em;flex:none;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.wrap{width:100%;max-width:1220px;margin:0 auto;padding:0 20px}
.muted{color:var(--dim)}
.gt{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-gold{background:var(--gold-g);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-ig{background:var(--ig);-webkit-background-clip:text;background-clip:text;color:transparent}
:focus-visible{outline:2px solid var(--sky);outline-offset:3px;border-radius:8px}
::selection{background:rgba(47,111,237,.35)}

/* ───── دکمه‌ها ───── */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;height:46px;padding:0 20px;border-radius:14px;
  border:1px solid transparent;font-weight:700;font-size:14.5px;cursor:pointer;white-space:nowrap;
  transition:transform .18s,box-shadow .18s,background .18s,border-color .18s}
.btn:hover{transform:translateY(-2px)}
.btn:active{transform:translateY(0)}
.btn-pri{background:var(--grad);color:#fff;box-shadow:0 12px 28px -10px rgba(47,111,237,.7)}
.btn-pri:hover{box-shadow:0 16px 34px -10px rgba(47,111,237,.85)}
.btn-gold{background:var(--gold-g);color:#2A1600;box-shadow:0 12px 28px -12px rgba(255,160,0,.7)}
.btn-ig{background:var(--ig);color:#fff;box-shadow:0 12px 28px -12px rgba(221,42,123,.7)}
.btn-ghost{background:transparent;border-color:var(--line2);color:var(--ink)}
.btn-ghost:hover{background:var(--card2)}
.btn-soft{background:var(--card2);border-color:var(--line);color:var(--ink)}
.btn-soft:hover{border-color:var(--sky);color:var(--sky)}
.btn-lg{height:54px;padding:0 26px;font-size:15.5px;border-radius:16px}
.btn-block{width:100%}
.btn .ic{width:18px;height:18px}

/* ───── نوار اعلان و هدر ───── */
.notice{background:var(--grad);color:#fff;font-size:13px;font-weight:600;text-align:center;padding:8px 16px}
.notice .ic{display:inline-block;vertical-align:-3px;margin-left:6px;width:15px;height:15px}
.hdr{position:sticky;top:0;z-index:50;background:transparent;transition:background .25s,box-shadow .25s,border-color .25s;border-bottom:1px solid transparent}
.hdr.scrolled{background:var(--glass);backdrop-filter:blur(16px) saturate(1.4);-webkit-backdrop-filter:blur(16px) saturate(1.4);border-color:var(--line)}
.hdr-in{display:flex;align-items:center;gap:22px;height:74px}
.logo{display:flex;align-items:center;gap:11px;font-weight:900;font-size:19px;letter-spacing:-.2px}
.logo-mark{width:40px;height:40px;border-radius:13px;background:var(--grad);display:grid;place-items:center;color:#fff;
  box-shadow:0 10px 24px -10px rgba(47,111,237,.8)}
.logo-mark .ic{width:22px;height:22px;fill:#fff;stroke:none}
.logo small{display:block;font-size:11px;font-weight:600;color:var(--dim);letter-spacing:0;line-height:1.3}
.nav{display:flex;align-items:center;gap:4px;margin-inline-start:auto}
.nav a{padding:8px 13px;border-radius:11px;font-size:14px;font-weight:600;color:var(--ink2);transition:background .15s,color .15s}
.nav a:hover{background:var(--card2);color:var(--ink)}
.hdr-act{display:flex;align-items:center;gap:10px}
.icon-btn{width:44px;height:44px;border-radius:13px;border:1px solid var(--line2);background:transparent;display:grid;place-items:center;cursor:pointer;color:var(--ink)}
.icon-btn:hover{background:var(--card2)}
.icon-btn .ic{width:20px;height:20px}
.i-sun{display:none}
:root[data-theme=light] .i-sun{display:block} :root[data-theme=light] .i-moon{display:none}
@media (prefers-color-scheme:light){:root:not([data-theme=dark]) .i-sun{display:block}:root:not([data-theme=dark]) .i-moon{display:none}}
.menu-btn{display:none}
.drawer{display:none}

/* ───── هیرو ───── */
.hero{position:relative;padding:56px 0 40px;overflow:hidden}
.hero::before{content:"";position:absolute;inset:-20% -10% auto -10%;height:900px;pointer-events:none;
  background:radial-gradient(520px 380px at 18% 30%,rgba(139,92,246,.30),transparent 70%),
             radial-gradient(560px 420px at 82% 18%,rgba(42,171,238,.28),transparent 70%),
             radial-gradient(420px 300px at 60% 75%,rgba(255,178,36,.14),transparent 70%)}
.hero::after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.5;
  background-image:linear-gradient(var(--line) 1px,transparent 1px),linear-gradient(90deg,var(--line) 1px,transparent 1px);
  background-size:56px 56px;-webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 30%,#000 30%,transparent 75%);
  mask-image:radial-gradient(ellipse 70% 60% at 50% 30%,#000 30%,transparent 75%)}
.hero-in{position:relative;z-index:1;display:grid;grid-template-columns:1.08fr .92fr;gap:48px;align-items:center}
.pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px 6px 8px;border-radius:99px;background:var(--card);
  border:1px solid var(--line2);font-size:13px;font-weight:600;color:var(--ink2);box-shadow:var(--sh)}
.pill .dot{width:22px;height:22px;border-radius:99px;background:rgba(23,201,120,.16);display:grid;place-items:center}
.pill .dot::after{content:"";width:8px;height:8px;border-radius:99px;background:var(--green);box-shadow:0 0 0 0 rgba(23,201,120,.6);animation:ping 2s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(23,201,120,.55)}80%,100%{box-shadow:0 0 0 9px rgba(23,201,120,0)}}
.hero h1{font-size:clamp(32px,4.6vw,58px);line-height:1.28;font-weight:900;letter-spacing:-.8px;margin:22px 0 18px}
.hero-sub{font-size:clamp(15px,1.4vw,18px);color:var(--ink2);max-width:590px;line-height:2}
.hero-cta{display:flex;flex-wrap:wrap;gap:12px;margin:30px 0 26px}
.trust{display:flex;flex-wrap:wrap;gap:10px 22px}
.trust span{display:inline-flex;align-items:center;gap:7px;font-size:13.5px;font-weight:600;color:var(--ink2)}
.trust .ic{width:18px;height:18px;color:var(--green)}

.hv{position:relative}
.hv-glow{position:absolute;inset:8% 6%;border-radius:40px;background:conic-gradient(from 200deg,rgba(255,178,36,.35),rgba(139,92,246,.35),rgba(42,171,238,.35),rgba(236,72,153,.3),rgba(255,178,36,.35));filter:blur(60px);opacity:.55;pointer-events:none}
.bento{position:relative;display:grid;grid-template-columns:1.12fr 1fr;gap:14px}
.bt{position:relative;display:flex;flex-direction:column;background:var(--card);border:1px solid var(--line2);border-radius:24px;box-shadow:var(--sh2);padding:18px;overflow:hidden}
.bt::before{content:"";position:absolute;inset:0 0 auto 0;height:3px;background:var(--a,var(--grad))}
.bt-row{display:flex;align-items:center;gap:13px;margin-bottom:14px}
.bt-k{font-size:12px;color:var(--dim);font-weight:600;line-height:1.6}
.bt-t{font-size:15px;font-weight:800;line-height:1.6}
.bt-p{margin-top:auto;display:flex;align-items:baseline;justify-content:space-between;gap:8px;padding-top:12px;border-top:1px dashed var(--line2);font-size:12.5px}
.bt-p .amt{font-size:16px}
.bt-star{grid-row:1/3;--a:var(--gold-g);padding:24px;display:flex;flex-direction:column;animation:float 7s ease-in-out infinite}
.bt-star .big-ic{width:66px;height:66px;border-radius:21px;background:var(--gold-g);display:grid;place-items:center;box-shadow:0 16px 32px -12px rgba(255,160,0,.85);margin-bottom:18px}
.bt-star .big-ic .ic{width:36px;height:36px;fill:#fff;stroke:none}
.bt-star .bt-t{font-size:30px;font-weight:900;line-height:1.35;margin-top:2px}
.bt-star .bt-p{margin:22px 0 16px;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--card2)}
.bt-star .bt-p .amt{font-size:21px}
.bt-star::after{content:"";position:absolute;width:220px;height:220px;top:-90px;inset-inline-end:-90px;border-radius:99px;background:radial-gradient(circle,rgba(255,178,36,.28),transparent 70%);pointer-events:none}
.bt-prem{--a:var(--vio-g);animation:float 8s ease-in-out -2s infinite}
.bt-gift{--a:var(--pink-g)}
.bt-num{--a:var(--grn-g)}
.bt-ig{--a:var(--ig);animation:float 9s ease-in-out -4s infinite}
@keyframes float{0%,100%{translate:0 0}50%{translate:0 -8px}}
.mini-ic{width:46px;height:46px;border-radius:15px;display:grid;place-items:center;color:#fff;flex:none}
.mini-ic .ic{width:23px;height:23px}
.emo{font-size:30px;line-height:1;width:46px;height:46px;border-radius:15px;background:var(--card2);display:grid;place-items:center;flex:none}

/* ───── نوار قیمت لحظه‌ای ───── */
.ticker{position:relative;z-index:1;margin-top:34px;border-block:1px solid var(--line);background:var(--glass);overflow:hidden;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.ticker-track{display:flex;width:max-content;animation:tick 42s linear infinite}
.ticker:hover .ticker-track{animation-play-state:paused}
.tk{display:flex;align-items:center;gap:10px;padding:14px 28px;font-size:14px;white-space:nowrap;border-inline-end:1px solid var(--line)}
.tk .ic{width:18px;height:18px;color:var(--sky)}
.tk b{font-weight:800}
.tk .live{width:7px;height:7px;border-radius:99px;background:var(--green);box-shadow:0 0 10px var(--green)}
@keyframes tick{from{transform:translateX(0)}to{transform:translateX(50%)}}

/* ───── بخش‌ها ───── */
.sec{padding:96px 0;position:relative}
.sec-alt{background:var(--bg2);border-block:1px solid var(--line)}
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:40px;flex-wrap:wrap}
.sec-head.center{flex-direction:column;align-items:center;text-align:center}
.kicker{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:800;padding:6px 14px;border-radius:99px;
  background:rgba(47,111,237,.12);color:var(--sky);margin-bottom:14px}
.kicker .ic{width:16px;height:16px}
.kicker.gold{background:rgba(255,178,36,.13);color:var(--gold)}
.kicker.vio{background:rgba(139,92,246,.14);color:#A78BFA}
.kicker.pink{background:rgba(236,72,153,.13);color:#F472B6}
.kicker.grn{background:rgba(23,201,120,.13);color:var(--green)}
.sec h2{font-size:clamp(26px,3.2vw,40px);line-height:1.35;font-weight:900;letter-spacing:-.5px}
.sec-head p{color:var(--dim);max-width:560px;margin-top:10px;font-size:15.5px}
.sec-head.center p{margin-inline:auto}

/* ───── کارت خدمات ───── */
.svc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.svc{position:relative;display:flex;flex-direction:column;gap:14px;padding:26px;border-radius:var(--r2);background:var(--card);
  border:1px solid var(--line);overflow:hidden;transition:transform .22s,border-color .22s,box-shadow .22s}
.svc::after{content:"";position:absolute;inset:auto -40px -60px auto;width:200px;height:200px;border-radius:99px;opacity:.14;filter:blur(30px);background:var(--c,var(--sky))}
.svc:hover{transform:translateY(-5px);border-color:var(--line2);box-shadow:var(--sh2)}
.svc-ic{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;color:#fff;box-shadow:0 12px 26px -12px var(--c,#000)}
.svc-ic .ic{width:28px;height:28px}
.svc h3{font-size:19px;font-weight:800}
.svc p{color:var(--dim);font-size:14px;line-height:1.9;flex:1}
.svc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:14px;border-top:1px dashed var(--line2);font-size:13.5px}
.svc-foot .amt{font-size:15.5px}
.svc-go{display:inline-flex;align-items:center;gap:6px;font-weight:700;color:var(--sky)}
.svc-go .ic{width:16px;height:16px;transition:transform .2s}
.svc:hover .svc-go .ic{transform:translateX(-4px)}
.amt{font-weight:900;font-variant-numeric:tabular-nums}
.cur{font-size:.78em;color:var(--dim);font-weight:600}
.ask{font-weight:700;color:var(--dim);font-size:.92em}

/* ───── استارز ───── */
.stars-lay{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start}
.calc{position:sticky;top:96px;padding:28px;border-radius:var(--r2);background:var(--card);border:1px solid var(--line2);box-shadow:var(--sh2);overflow:hidden}
.calc::before{content:"";position:absolute;inset:0 0 auto 0;height:5px;background:var(--gold-g)}
.calc h3{font-size:20px;font-weight:900;display:flex;align-items:center;gap:10px}
.calc h3 .ic{color:var(--gold);fill:var(--gold);stroke:none;width:24px;height:24px}
.calc label{display:block;font-size:13px;font-weight:700;color:var(--dim);margin:20px 0 8px}
.qty-box{display:flex;align-items:center;gap:8px;background:var(--card2);border:1px solid var(--line2);border-radius:16px;padding:6px}
.qty-box input{flex:1;min-width:0;background:transparent;border:0;outline:0;font-size:24px;font-weight:900;text-align:center;padding:4px 0;direction:ltr}
.qty-box button{width:44px;height:44px;border-radius:12px;border:1px solid var(--line2);background:var(--card);cursor:pointer;font-size:22px;font-weight:700;line-height:1}
.qty-box button:hover{border-color:var(--gold);color:var(--gold)}
input[type=range]{width:100%;margin-top:16px;accent-color:var(--gold);height:6px}
.quick{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
.quick button{padding:6px 12px;border-radius:10px;border:1px solid var(--line2);background:transparent;cursor:pointer;font-size:13px;font-weight:700}
.quick button:hover,.quick button.on{border-color:var(--gold);color:var(--gold);background:rgba(255,178,36,.08)}
.calc-total{margin:22px 0 16px;padding:18px;border-radius:16px;background:linear-gradient(135deg,rgba(255,178,36,.12),rgba(255,122,0,.06));border:1px solid rgba(255,178,36,.25)}
.calc-total .t{font-size:12.5px;color:var(--dim);font-weight:700}
.calc-total .v{font-size:30px;font-weight:900;line-height:1.5}
.calc-total .v small{font-size:14px;color:var(--dim);font-weight:700}
.calc-note{font-size:12px;color:var(--dim);margin-top:12px;display:flex;gap:6px;line-height:1.8}
.calc-note .ic{width:15px;height:15px;margin-top:4px}
.pk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(172px,1fr));gap:14px}
.pk{position:relative;padding:20px;border-radius:var(--r);background:var(--card);border:1px solid var(--line);transition:transform .2s,border-color .2s,box-shadow .2s;display:flex;flex-direction:column;gap:6px}
.pk:hover{transform:translateY(-4px);border-color:rgba(255,178,36,.5);box-shadow:var(--sh)}
.pk-top{display:flex;align-items:center;justify-content:space-between}
.pk-star{width:42px;height:42px;border-radius:13px;background:rgba(255,178,36,.13);display:grid;place-items:center}
.pk-star .ic{width:22px;height:22px;fill:var(--gold);stroke:none}
.pk-q{font-size:28px;font-weight:900;line-height:1.35;margin-top:8px}
.pk-q small{font-size:14px;color:var(--dim);font-weight:700}
.pk .amt{font-size:17px}
.pk-unit{font-size:12px;color:var(--dim)}
.pk .btn{margin-top:10px;height:42px}
.tag{font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:99px;background:rgba(242,53,87,.13);color:#FB7185;white-space:nowrap}
.tag.gold{background:rgba(255,178,36,.14);color:var(--gold)}
.uses{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:26px}
.use{display:flex;align-items:center;gap:12px;padding:16px;border-radius:16px;background:var(--card);border:1px solid var(--line);font-size:13.5px;font-weight:700}
.use .ic{width:22px;height:22px;color:var(--gold)}

/* ───── پریمیوم ───── */
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;align-items:stretch}
.plan{position:relative;padding:30px 26px;border-radius:var(--r2);background:var(--card);border:1px solid var(--line);display:flex;flex-direction:column;transition:transform .22s,box-shadow .22s}
.plan:hover{transform:translateY(-5px);box-shadow:var(--sh2)}
.plan.hot{background:linear-gradient(var(--card),var(--card)) padding-box,var(--vio-g) border-box;border:2px solid transparent;box-shadow:0 30px 60px -30px rgba(139,92,246,.7)}
.plan-badge{position:absolute;top:-14px;inset-inline-start:50%;transform:translateX(50%);background:var(--vio-g);color:#fff;font-size:12px;font-weight:800;padding:5px 16px;border-radius:99px;white-space:nowrap}
.plan-ic{width:54px;height:54px;border-radius:17px;background:var(--vio-g);display:grid;place-items:center;color:#fff;margin-bottom:18px;box-shadow:0 12px 26px -12px rgba(139,92,246,.9)}
.plan-ic .ic{width:28px;height:28px}
.plan h3{font-size:21px;font-weight:900}
.plan .muted{font-size:13.5px;min-height:26px}
.plan-price{margin:18px 0 4px}
.plan-price .amt{font-size:34px}
.plan-mo{font-size:13px;color:var(--dim);margin-bottom:20px}
.plan ul{list-style:none;display:grid;gap:11px;margin-bottom:26px;flex:1}
.plan li{display:flex;gap:10px;font-size:14px;color:var(--ink2)}
.plan li .ic{width:19px;height:19px;color:var(--violet);margin-top:4px}
.feats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:34px}
.feat{padding:20px;border-radius:var(--r);background:var(--card);border:1px solid var(--line)}
.feat .ic{width:26px;height:26px;color:#A78BFA;margin-bottom:12px}
.feat b{display:block;font-size:15px;font-weight:800;margin-bottom:4px}
.feat span{font-size:13px;color:var(--dim);line-height:1.8}

/* ───── گیفت ───── */
.gift-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.gift{text-align:center;padding:20px 12px 16px;border-radius:var(--r);background:var(--card);border:1px solid var(--line);transition:transform .2s,box-shadow .2s,border-color .2s;display:flex;flex-direction:column;align-items:center;gap:4px}
.gift:hover{transform:translateY(-5px) rotate(-1deg);border-color:rgba(236,72,153,.45);box-shadow:var(--sh)}
.gift-e{font-size:46px;line-height:1;width:84px;height:84px;border-radius:26px;display:grid;place-items:center;margin-bottom:10px;
  background:radial-gradient(circle at 30% 25%,rgba(236,72,153,.22),rgba(139,92,246,.12) 60%,transparent 75%)}
.gift h3{font-size:14.5px;font-weight:800}
.gift .st{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--gold);font-weight:700}
.gift .st .ic{width:13px;height:13px;fill:var(--gold);stroke:none}
.gift .amt{font-size:15px}
.gift .btn{margin-top:auto;height:38px;font-size:13px;width:100%}
.gift>div:last-of-type{margin-bottom:10px}

/* ───── شماره مجازی ───── */
.num-bar{display:flex;gap:12px;align-items:center;margin-bottom:22px;flex-wrap:wrap}
.search{flex:1;min-width:240px;display:flex;align-items:center;gap:10px;height:54px;padding:0 18px;border-radius:16px;background:var(--card);border:1px solid var(--line2)}
.search:focus-within{border-color:var(--green);box-shadow:0 0 0 4px rgba(23,201,120,.12)}
.search .ic{width:20px;height:20px;color:var(--dim)}
.search input{flex:1;min-width:0;border:0;outline:0;background:transparent;font-size:15px}
.num-meta{display:flex;gap:10px;flex-wrap:wrap}
.num-meta span{display:inline-flex;align-items:center;gap:7px;height:54px;padding:0 18px;border-radius:16px;background:var(--card);border:1px solid var(--line);font-size:13.5px;font-weight:700}
.num-meta .ic{color:var(--green);width:18px;height:18px}
.ctry-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.ctry{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:16px;background:var(--card);border:1px solid var(--line);cursor:pointer;text-align:start;transition:border-color .18s,transform .18s,box-shadow .18s;width:100%}
.ctry:hover{border-color:rgba(23,201,120,.55);transform:translateY(-3px);box-shadow:var(--sh)}
.flag{font-size:30px;line-height:1;width:50px;height:50px;border-radius:14px;background:var(--card2);display:grid;place-items:center;flex:none}
.ctry-t{flex:1;min-width:0}
.ctry-t b{display:block;font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctry-t span{font-size:12px;color:var(--dim)}
.ctry-p{text-align:end;font-size:12px;color:var(--dim);white-space:nowrap}
.ctry-p .amt{display:block;font-size:14.5px;color:var(--ink)}
.empty{display:none;text-align:center;padding:40px;color:var(--dim);border:1px dashed var(--line2);border-radius:var(--r);margin-top:12px}
.num-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:26px}
.num-step{display:flex;gap:14px;align-items:flex-start;padding:18px;border-radius:16px;background:linear-gradient(135deg,rgba(23,201,120,.08),transparent);border:1px solid rgba(23,201,120,.2)}
.num-step i{font-style:normal;width:34px;height:34px;border-radius:11px;background:var(--grn-g);color:#fff;display:grid;place-items:center;font-weight:900;flex:none}
.num-step b{display:block;font-size:14.5px}
.num-step span{font-size:13px;color:var(--dim)}

/* ───── اینستاگرام ───── */
.ig-band{position:relative;border-radius:32px;padding:44px;overflow:hidden;background:var(--card);border:1px solid var(--line)}
.ig-band::before{content:"";position:absolute;inset:0;background:var(--ig);opacity:.10}
.ig-band::after{content:"";position:absolute;width:420px;height:420px;inset-inline-end:-120px;top:-160px;border-radius:99px;background:var(--ig);filter:blur(80px);opacity:.28}
.ig-band>*{position:relative;z-index:1}
.ig-top{display:grid;grid-template-columns:1fr 400px;gap:34px;align-items:center;margin-bottom:34px}
.ig-logo{width:68px;height:68px;border-radius:22px;background:var(--ig);display:grid;place-items:center;color:#fff;margin-bottom:18px;box-shadow:0 16px 34px -14px rgba(221,42,123,.9)}
.ig-logo .ic{width:36px;height:36px}
.ig-points{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.ig-points span{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:99px;background:var(--card);border:1px solid var(--line2);font-size:13px;font-weight:700}
.ig-points .ic{width:16px;height:16px;color:#F472B6}
.ig-calc{padding:24px;border-radius:24px;background:var(--glass);border:1px solid var(--line2);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:var(--sh2)}
.ig-calc h3{font-size:17px;font-weight:900;margin-bottom:14px}
.field{display:block;margin-bottom:12px}
.field span{display:block;font-size:12.5px;font-weight:700;color:var(--dim);margin-bottom:6px}
.field select,.field input{width:100%;height:48px;border-radius:13px;border:1px solid var(--line2);background:var(--card);padding:0 14px;font-size:14.5px;font-weight:700;outline:0}
.field select:focus,.field input:focus{border-color:#DD2A7B}
.ig-total{display:flex;align-items:center;justify-content:space-between;margin:16px 0;padding:14px 16px;border-radius:14px;background:var(--card2)}
.ig-total b{font-size:22px;font-weight:900}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
.tab{display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 16px;border-radius:12px;border:1px solid var(--line2);background:var(--card);cursor:pointer;font-weight:700;font-size:14px;color:var(--ink2)}
.tab .ic{width:17px;height:17px}
.tab:hover{color:var(--ink)}
.tab.on{background:var(--ink);color:var(--bg);border-color:var(--ink)}
.offer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.offer{display:flex;flex-direction:column;gap:8px;padding:20px;border-radius:var(--r);background:var(--card);border:1px solid var(--line);transition:transform .2s,box-shadow .2s,border-color .2s}
.offer:hover{transform:translateY(-4px);box-shadow:var(--sh);border-color:var(--line2)}
.offer.hide{display:none}
.offer-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.offer-ic{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;color:#fff}
.offer-ic.ig{background:var(--ig)}
.offer-ic.tg{background:var(--grad)}
.offer-ic .ic{width:22px;height:22px}
.offer h3{font-size:15.5px;font-weight:800;line-height:1.7}
.offer .muted{font-size:13px;line-height:1.8;flex:1}
.offer-price{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 8px;margin-top:4px}
.offer-price .amt{font-size:19px}
.per{font-size:12px;color:var(--dim);font-weight:600}
.offer-range{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--dim)}
.offer-range .ic{width:14px;height:14px}
.offer .btn{margin-top:8px;height:42px}

/* ───── مراحل، مزایا، پرداخت ───── */
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;position:relative}
.steps::before{content:"";position:absolute;top:38px;inset-inline:12%;height:2px;background:repeating-linear-gradient(90deg,var(--line2) 0 10px,transparent 10px 18px)}
.step{position:relative;text-align:center;padding:0 10px}
.step-n{position:relative;width:76px;height:76px;margin:0 auto 18px;border-radius:24px;background:var(--card);border:1px solid var(--line2);display:grid;place-items:center;box-shadow:var(--sh)}
.step-n .ic{width:32px;height:32px;color:var(--sky)}
.step-n i{position:absolute;top:-8px;inset-inline-end:-8px;width:28px;height:28px;border-radius:99px;background:var(--grad);color:#fff;font-style:normal;font-weight:900;font-size:13px;display:grid;place-items:center}
.step b{display:block;font-size:17px;font-weight:800;margin-bottom:6px}
.step span{font-size:14px;color:var(--dim)}
.why{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.why-c{padding:26px;border-radius:var(--r2);background:var(--card);border:1px solid var(--line);transition:border-color .2s}
.why-c:hover{border-color:var(--line2)}
.why-ic{width:50px;height:50px;border-radius:16px;display:grid;place-items:center;margin-bottom:16px}
.why-ic .ic{width:26px;height:26px}
.why-c b{display:block;font-size:17px;font-weight:800;margin-bottom:6px}
.why-c span{font-size:14px;color:var(--dim);line-height:1.9}
.pay{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.pay-c{display:flex;align-items:center;gap:14px;padding:20px;border-radius:var(--r);background:var(--card);border:1px solid var(--line)}
.pay-c .mini-ic .ic{width:24px;height:24px}
.pay-c b{display:block;font-size:15px}
.pay-c span{font-size:12.5px;color:var(--dim)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:40px}
.stat{padding:24px;border-radius:var(--r);background:var(--card);border:1px solid var(--line);text-align:center}
.stat b{display:block;font-size:34px;font-weight:900;line-height:1.4}
.stat span{font-size:13.5px;color:var(--dim);font-weight:600}

/* ───── سوالات ───── */
.faq{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
.faq details{border-radius:var(--r);background:var(--card);border:1px solid var(--line);transition:border-color .2s}
.faq details[open]{border-color:var(--line2);box-shadow:var(--sh)}
.faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:14px;padding:18px 20px;font-weight:800;font-size:15px}
.faq summary::-webkit-details-marker{display:none}
.faq summary .q{width:34px;height:34px;border-radius:11px;background:rgba(47,111,237,.12);color:var(--sky);display:grid;place-items:center;flex:none}
.faq summary .q .ic{width:18px;height:18px}
.faq summary .chev{margin-inline-start:auto;width:18px;height:18px;color:var(--dim);transition:transform .2s}
.faq details[open] .chev{transform:rotate(180deg)}
.faq details p{padding:0 20px 20px 20px;padding-inline-start:68px;color:var(--ink2);font-size:14.5px;line-height:2}

/* ───── CTA و فوتر ───── */
.cta{position:relative;border-radius:34px;padding:60px 48px;overflow:hidden;background:var(--grad);color:#fff;display:grid;grid-template-columns:1fr auto;gap:30px;align-items:center}
.cta::before{content:"";position:absolute;inset:0;background:radial-gradient(400px 300px at 10% 110%,rgba(255,255,255,.25),transparent 70%),radial-gradient(300px 240px at 95% -10%,rgba(255,214,107,.45),transparent 70%)}
.cta>*{position:relative}
.cta h2{font-size:clamp(26px,3vw,38px);font-weight:900;line-height:1.4}
.cta p{opacity:.9;margin-top:8px;font-size:16px}
.cta .btn{background:#fff;color:#1D3FB8;box-shadow:0 16px 34px -14px rgba(0,0,0,.5)}
.cta .btn-line{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.35)}
.cta-acts{display:flex;gap:12px;flex-wrap:wrap}
.ftr{padding:70px 0 30px;border-top:1px solid var(--line);margin-top:96px;background:var(--bg2)}
.ftr-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:34px}
.ftr p{color:var(--dim);font-size:14px;margin-top:14px;max-width:340px}
.ftr h4{font-size:15px;font-weight:800;margin-bottom:14px}
.ftr ul{list-style:none;display:grid;gap:9px}
.ftr li a{color:var(--dim);font-size:14px;display:inline-flex;align-items:center;gap:8px}
.ftr li a:hover{color:var(--ink)}
.ftr li .ic{width:16px;height:16px}
.ftr-bot{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:50px;padding-top:24px;border-top:1px solid var(--line);color:var(--dim);font-size:13px}

/* ───── پنجره‌ی خرید و دکمه‌های شناور ───── */
.modal{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:16px}
.modal.open{display:flex}
.modal-bg{position:absolute;inset:0;background:rgba(3,6,14,.62);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);animation:fade .2s}
.modal-card{position:relative;width:100%;max-width:440px;border-radius:28px;background:var(--card);border:1px solid var(--line2);box-shadow:var(--sh2);padding:28px;animation:pop .25s cubic-bezier(.2,.9,.3,1.2)}
@keyframes fade{from{opacity:0}}
@keyframes pop{from{opacity:0;transform:translateY(20px) scale(.97)}}
.modal-x{position:absolute;top:16px;inset-inline-end:16px}
.modal-ic{width:58px;height:58px;border-radius:18px;background:var(--grad);display:grid;place-items:center;color:#fff;margin-bottom:16px}
.modal-ic .ic{width:30px;height:30px}
.modal h3{font-size:20px;font-weight:900;line-height:1.6;padding-inline-end:40px}
.modal-price{margin:14px 0;padding:14px 16px;border-radius:14px;background:var(--card2);display:flex;justify-content:space-between;gap:12px;font-size:14px}
.modal-price b{font-weight:900}
.modal ol{list-style:none;display:grid;gap:10px;margin:18px 0 22px;counter-reset:s}
.modal li{display:flex;gap:12px;font-size:14px;color:var(--ink2);counter-increment:s}
.modal li::before{content:counter(s,persian);width:26px;height:26px;border-radius:9px;background:rgba(47,111,237,.14);color:var(--sky);font-weight:900;display:grid;place-items:center;flex:none;font-size:13px}
.modal-warn{font-size:12.5px;color:var(--dim);margin-top:12px;display:flex;gap:8px;line-height:1.8}
.modal-warn .ic{width:16px;height:16px;color:var(--green);margin-top:3px}
.fab{position:fixed;bottom:22px;inset-inline-start:22px;z-index:60;display:flex;flex-direction:column;gap:10px}
.fab a,.fab button{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;border:0;cursor:pointer;box-shadow:var(--sh2)}
.fab .sup{background:var(--grad);color:#fff}
.fab .sup .ic{width:26px;height:26px}
.fab .top{background:var(--card);color:var(--ink);border:1px solid var(--line2);opacity:0;pointer-events:none;transform:translateY(10px);transition:opacity .2s,transform .2s}
.fab .top.show{opacity:1;pointer-events:auto;transform:none}
.fab .top .ic{width:22px;height:22px}

/* ───── ظاهر شدن با اسکرول ───── */
.js .rv{opacity:0;transform:translateY(22px);transition:opacity .6s ease,transform .6s cubic-bezier(.2,.8,.2,1)}
.js .rv.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){
  *{animation:none!important;transition:none!important;scroll-behavior:auto!important}
  .js .rv{opacity:1;transform:none}
}

/* ───── واکنش‌گرا ───── */
@media (max-width:1100px){
  .nav{display:none}
  .menu-btn{display:grid}
  .hdr-act{margin-inline-start:auto}
  .drawer{position:fixed;inset:74px 0 auto 0;z-index:49;background:var(--glass);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
    border-bottom:1px solid var(--line);padding:12px 20px 20px;display:none;flex-direction:column;gap:4px}
  .drawer.open{display:flex}
  .drawer a{padding:12px 14px;border-radius:12px;font-weight:700}
  .drawer a:hover{background:var(--card2)}
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
  .hdr-in{height:66px;gap:12px}
  .drawer{inset:66px 0 auto 0}
  .hdr-act .btn{display:none}
  .logo small{display:none}
  .hero{padding:34px 0 20px}
  .hero-cta .btn{flex:1}
  .bento{gap:10px}
  .bt{padding:14px;border-radius:20px}
  .bt-star{padding:18px}
  .bt-star .bt-t{font-size:23px}
  .bt-star .big-ic{width:54px;height:54px;border-radius:17px;margin-bottom:12px}
  .bt-row{flex-direction:column;align-items:flex-start;gap:8px}
  .bt-t{font-size:13.5px}
  .bt-p{flex-direction:column;gap:0}
  .sec{padding:70px 0}
  .svc-grid,.why,.pay,.uses{grid-template-columns:1fr}
  .pk-grid{grid-template-columns:repeat(2,1fr)}
  .gift-grid{grid-template-columns:repeat(2,1fr)}
  .offer-grid,.ctry-grid{grid-template-columns:1fr}
  .ig-band{padding:26px 18px;border-radius:26px}
  .steps{grid-template-columns:1fr}
  .stats{grid-template-columns:repeat(2,1fr)}
  .ftr-grid{grid-template-columns:1fr}
  .faq details p{padding-inline-start:20px}
  .fab{bottom:14px;inset-inline-start:14px}
  .fab a,.fab button{width:46px;height:46px;border-radius:15px}
}
</style>
</head>
<body>

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
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="M21.2 3.6L2.9 10.8c-.8.3-.8 1.5.1 1.8l4.6 1.5 1.8 5.5c.3.8 1.3 1 1.9.4l2.6-2.6 4.8 3.5c.7.5 1.6.1 1.8-.7l2.9-14.3c.2-.9-.7-1.7-1.6-1.3z"/><path d="M7.6 14.1L17.5 7.5l-7.4 8.1"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/></symbol>
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
  <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></symbol>
  <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12.5H6z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></symbol>
</svg>

<?php if ($d['notice'] !== ''): ?>
<div class="notice"><?= siteIcon('bolt') ?><?= siteE($d['notice']) ?></div>
<?php endif; ?>

<header class="hdr" id="hdr">
  <div class="wrap hdr-in">
    <a href="#top" class="logo" aria-label="<?= siteE($name) ?>">
      <span class="logo-mark"><?= siteIcon('star') ?></span>
      <span><?= siteE($name) ?><small>خدمات تلگرام و اینستاگرام</small></span>
    </a>
    <nav class="nav" aria-label="بخش‌ها">
      <?php foreach ($nav as [$href, $label]): ?><a href="<?= $href ?>"><?= siteE($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="hdr-act">
      <button type="button" class="icon-btn" id="theme" aria-label="تغییر تم"><?= siteIcon('sun', 'i-sun') ?><?= siteIcon('moon', 'i-moon') ?></button>
      <?php if ($bot !== ''): ?>
      <a class="btn btn-pri" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ورود به ربات</a>
      <?php endif; ?>
      <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="منو" aria-expanded="false"><?= siteIcon('menu') ?></button>
    </div>
  </div>
</header>
<nav class="drawer" id="drawer" aria-label="منوی موبایل">
  <?php foreach ($nav as [$href, $label]): ?><a href="<?= $href ?>"><?= siteE($label) ?></a><?php endforeach; ?>
  <?php if ($bot !== ''): ?><a class="btn btn-pri" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ورود به ربات</a><?php endif; ?>
</nav>

<main id="top">

<!-- ═════════════ هیرو ═════════════ -->
<section class="hero">
  <div class="wrap hero-in">
    <div>
      <span class="pill"><span class="dot"></span>تحویل خودکار · قیمت لحظه‌ای · ۲۴ ساعته</span>
      <h1>خرید <span class="gt">استارز، پریمیوم و گیفت</span> تلگرام در چند ثانیه</h1>
      <p class="hero-sub">
        شماره مجازی<?= $d['countries'] ? ' از ' . siteFa(count($d['countries'])) . ' کشور' : '' ?>،
        فالوور و لایک اینستاگرام، ری‌اکشن و بازدید کانال — با قیمت لحظه‌ای،
        پرداخت امن و تحویل خودکار. فقط آیدی لازم است؛ بدون رمز.
      </p>
      <div class="hero-cta">
        <?php if ($bot !== ''): ?>
        <a class="btn btn-pri btn-lg" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> شروع خرید در ربات</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="#services"><?= siteIcon('tag') ?> مشاهده قیمت‌ها</a>
      </div>
      <div class="trust">
        <span><?= siteIcon('check') ?>بدون نیاز به رمز عبور</span>
        <span><?= siteIcon('check') ?>تحویل خودکار و سریع</span>
        <span><?= siteIcon('check') ?>بازگشت وجه در صورت خطا</span>
      </div>
    </div>

    <div class="hv" aria-hidden="true">
      <div class="hv-glow"></div>
      <div class="bento">
        <div class="bt bt-star">
          <div class="big-ic"><?= siteIcon('star') ?></div>
          <div class="bt-k">استارز تلگرام</div>
          <div class="bt-t"><?= $heroStar ? siteE($heroStar['name']) : 'استارز' ?></div>
          <div class="bt-k" style="margin-top:6px">تحویل مستقیم به آیدی شما</div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= $heroStar ? siteMoney($heroStar['price']) : siteMoney($d['star_unit']) ?></span></div>
          <span class="btn btn-gold btn-block"><?= siteIcon('bolt') ?> خرید فوری</span>
        </div>
        <div class="bt bt-prem">
          <div class="bt-row"><span class="mini-ic" style="background:var(--vio-g)"><?= siteIcon('crown') ?></span>
            <div><div class="bt-k">تلگرام پریمیوم</div><div class="bt-t"><?= $heroPrem ? siteE($heroPrem['name']) : 'اشتراک پریمیوم' ?></div></div></div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= siteMoney($heroPrem['price'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-gift">
          <div class="bt-row"><span class="emo"><?= siteE($heroGift['emoji'] ?? '🎁') ?></span>
            <div><div class="bt-k">گیفت تلگرام</div><div class="bt-t"><?= siteE($heroGift['name'] ?? 'گیفت تلگرام') ?></div></div></div>
          <div class="bt-p"><span class="muted">قیمت</span><span><?= siteMoney($heroGift['price'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-num">
          <div class="bt-row"><span class="emo"><?= siteE($heroCtry['flag'] ?? '🌍') ?></span>
            <div><div class="bt-k">شماره مجازی</div><div class="bt-t"><?= siteE($heroCtry['name'] ?? 'کشورهای مختلف') ?></div></div></div>
          <div class="bt-p"><span class="muted">از</span><span><?= siteMoney($heroCtry['from'] ?? 0) ?></span></div>
        </div>
        <div class="bt bt-ig">
          <div class="bt-row"><span class="mini-ic" style="background:var(--ig)"><?= siteIcon('insta') ?></span>
            <div><div class="bt-k">اینستاگرام</div><div class="bt-t">فالوور · لایک · بازدید</div></div></div>
          <div class="bt-p"><span class="muted">از</span><span><?= siteMoney($igMin) ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($ticker): ?>
  <div class="ticker" aria-label="قیمت‌های لحظه‌ای">
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
      <span class="kicker"><?= siteIcon('bag') ?> همه‌ی خدمات</span>
      <h2>هرچه برای <span class="gt">تلگرام و اینستاگرام</span> لازم دارید</h2>
      <p>شش دسته خدمت، یک حساب کاربری و یک کیف پول — سفارش در کمتر از یک دقیقه ثبت می‌شود.</p>
    </div>
    <div class="svc-grid">
      <?php
      $svcs = [
        ['#stars', 'star', 'var(--gold-g)', '#FFB224', 'استارز تلگرام', 'برای گیفت، ری‌اکشن پولی و پرداخت داخل ربات‌ها و مینی‌اپ‌ها. هر مقدار که بخواهید.', $starFrom, 'هر استارز از', $hasStars],
        ['#premium', 'crown', 'var(--vio-g)', '#8B5CF6', 'تلگرام پریمیوم', 'فعال‌سازی مستقیم روی آیدی شما یا دوستتان، بدون نیاز به رمز یا کد ورود.', $premMin, 'از', (bool)$d['premium']],
        ['#gifts', 'gift', 'var(--pink-g)', '#EC4899', 'گیفت تلگرام', 'تدی، قلب، رز، جام، الماس و گیفت‌های کمیاب مارکت — مستقیم برای هر کسی.', $giftMin, 'از', (bool)$d['gifts']],
        ['#numbers', 'phone', 'var(--grn-g)', '#17C978', 'شماره مجازی', 'شماره برای تلگرام، واتساپ و سرویس‌های دیگر؛ کد تایید همان‌جا نمایش داده می‌شود.', $d['num_from'], 'از', (bool)$d['countries']],
        ['#instagram', 'insta', 'var(--ig)', '#DD2A7B', 'فالوور اینستاگرام', 'فالوور، لایک، بازدید ریلز و کامنت — فقط با لینک پیج یا پست، بدون رمز.', $igMin, 'از', true],
        ['#growth', 'heart', 'var(--grad)', '#2F6FED', 'ری‌اکشن و بازدید کانال', 'ری‌اکشن پست، بازدید استوری، اشتراک‌گذاری و ممبر برای رشد کانال تلگرام.', $grMin, 'از', (bool)$d['tgrowth']],
      ];
      foreach ($svcs as [$href, $ic, $bg, $c, $t, $p, $from, $fromLbl, $show]): if (!$show) continue; ?>
      <a class="svc rv" href="<?= $href ?>" style="--c:<?= $c ?>">
        <span class="svc-ic" style="background:<?= $bg ?>"><?= siteIcon($ic) ?></span>
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
<section class="sec sec-alt" id="stars">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker gold"><?= siteIcon('star') ?> استارز تلگرام</span>
        <h2>خرید <span class="gt-gold">استارز تلگرام</span> با قیمت لحظه‌ای</h2>
        <p>استارز مستقیم به آیدی شما (یا هرکسی که بخواهید) واریز می‌شود. فقط یوزرنیم لازم است؛ هیچ‌وقت کد ورود را به کسی ندهید.</p>
      </div>
    </div>
    <div class="stars-lay">
      <?php if ($d['star_unit'] > 0): ?>
      <div class="calc rv" id="starCalc" data-unit="<?= siteE((string)$d['star_unit']) ?>" data-min="<?= (int)$d['star_min'] ?>" data-max="<?= (int)min($d['star_max'], 1000000) ?>">
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
        <button type="button" class="btn btn-gold btn-lg btn-block" id="starBuy" <?= siteBuy('استارز — مقدار دلخواه', '', 'آیدی تلگرام گیرنده + تعداد استارز') ?>><?= siteIcon('bolt') ?> خرید <span id="starBuyQ">—</span> استارز</button>
        <div class="calc-note"><?= siteIcon('shield') ?> حداقل <?= siteFa($d['star_min']) ?> استارز. مبلغ نهایی همان است که در فاکتور ربات می‌بینید.</div>
      </div>
      <?php endif; ?>
      <div<?= $d['star_unit'] > 0 ? '' : ' style="grid-column:1/-1"' ?>>
        <div class="pk-grid">
          <?php foreach ($d['stars'] as $s): $per = $s['qty'] > 0 && $s['price'] > 0 ? $s['price'] / $s['qty'] : 0; ?>
          <article class="pk rv">
            <div class="pk-top">
              <span class="pk-star"><?= siteIcon('star') ?></span>
              <?php if ($s['badge'] !== ''): ?><span class="tag gold"><?= siteE($s['badge']) ?></span><?php endif; ?>
            </div>
            <div class="pk-q"><?= $s['qty'] > 0 ? siteFa($s['qty']) . ' <small>استارز</small>' : siteE($s['name']) ?></div>
            <div><?= siteMoney($s['price']) ?></div>
            <?php if ($per > 0): ?><div class="pk-unit">هر استارز <?= siteE(siteMoneyText($per)) ?></div><?php endif; ?>
            <button type="button" class="btn btn-soft btn-block" <?= siteBuy($s['name'], siteMoneyText($s['price']), 'آیدی تلگرام گیرنده') ?>>خرید <?= siteIcon('arrow') ?></button>
          </article>
          <?php endforeach; ?>
        </div>
        <div class="uses">
          <div class="use rv"><?= siteIcon('gift') ?> ارسال گیفت به دوستان</div>
          <div class="use rv"><?= siteIcon('heart') ?> ری‌اکشن پولی روی پست‌ها</div>
          <div class="use rv"><?= siteIcon('bag') ?> پرداخت در ربات و مینی‌اپ</div>
          <div class="use rv"><?= siteIcon('users') ?> حمایت از کانال‌ها</div>
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
      <span class="kicker vio"><?= siteIcon('crown') ?> تلگرام پریمیوم</span>
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
      <article class="plan rv<?= $hot ? ' hot' : '' ?>">
        <?php if ($hot): ?><span class="plan-badge"><?= siteE($p['badge'] !== '' ? $p['badge'] : 'بهترین انتخاب') ?></span><?php endif; ?>
        <span class="plan-ic"><?= siteIcon('crown') ?></span>
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
        <button type="button" class="btn <?= $hot ? 'btn-pri' : 'btn-soft' ?> btn-lg btn-block" <?= siteBuy($p['name'], siteMoneyText($p['price']), 'آیدی تلگرام گیرنده') ?>>خرید اشتراک <?= siteIcon('arrow') ?></button>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="feats">
      <div class="feat rv"><?= siteIcon('upload') ?><b>آپلود تا ۴ گیگابایت</b><span>فایل‌های حجیم را بدون تقسیم بفرستید.</span></div>
      <div class="feat rv"><?= siteIcon('bolt') ?><b>دانلود با حداکثر سرعت</b><span>بدون محدودیت سرعت برای فایل و رسانه.</span></div>
      <div class="feat rv"><?= siteIcon('mic') ?><b>تبدیل ویس به متن</b><span>پیام صوتی را بخوانید، بدون گوش دادن.</span></div>
      <div class="feat rv"><?= siteIcon('noad') ?><b>بدون تبلیغات</b><span>تبلیغات کانال‌های عمومی حذف می‌شود.</span></div>
      <div class="feat rv"><?= siteIcon('smile') ?><b>ایموجی و استیکر ویژه</b><span>ایموجی متحرک، استیکر اختصاصی و ایموجی وضعیت.</span></div>
      <div class="feat rv"><?= siteIcon('folder') ?><b>محدودیت‌های دوبرابر</b><span>کانال، پوشه، پین و حساب‌های بیشتر.</span></div>
      <div class="feat rv"><?= siteIcon('star') ?><b>نشان پریمیوم</b><span>نشان ویژه کنار نام شما در همه‌جا.</span></div>
      <div class="feat rv"><?= siteIcon('globe') ?><b>ترجمه‌ی کل گفتگو</b><span>ترجمه‌ی فوری کانال‌ها و گفتگوها.</span></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($d['gifts']): ?>
<!-- ═════════════ گیفت ═════════════ -->
<section class="sec sec-alt" id="gifts">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker pink"><?= siteIcon('gift') ?> گیفت تلگرام</span>
        <h2>گیفت تلگرام برای <span class="gt">هر مناسبتی</span></h2>
        <p>گیفت مستقیم روی پروفایل گیرنده می‌نشیند. آیدی گیرنده را بدهید، بقیه با ما.</p>
      </div>
      <?php if ($bot !== ''): ?><a class="btn btn-ghost" href="<?= siteE($bot) ?>" target="_blank" rel="noopener">همه‌ی گیفت‌ها در ربات <?= siteIcon('arrow') ?></a><?php endif; ?>
    </div>
    <div class="gift-grid">
      <?php foreach ($d['gifts'] as $g): ?>
      <article class="gift rv">
        <div class="gift-e"><?= siteE($g['emoji']) ?></div>
        <h3><?= siteE($g['name']) ?></h3>
        <?php if ($g['stars'] > 0): ?><span class="st"><?= siteIcon('star') ?><?= siteFa($g['stars']) ?> استارز</span><?php endif; ?>
        <div><?= siteMoney($g['price']) ?></div>
        <button type="button" class="btn btn-soft" <?= siteBuy($g['name'], siteMoneyText($g['price']), $g['price'] > 0 ? 'آیدی تلگرام گیرنده' : 'نام گیفت موردنظر') ?>>ارسال گیفت</button>
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
        <span class="kicker grn"><?= siteIcon('phone') ?> شماره مجازی</span>
        <h2>شماره مجازی <span class="gt">کشورهای مختلف</span></h2>
        <p>کشور را انتخاب کنید، شماره فوری تحویل می‌شود و کد تایید در همان صفحه نمایش داده می‌شود.</p>
      </div>
    </div>
    <div class="num-bar rv">
      <label class="search"><?= siteIcon('search') ?><input id="ctrySearch" type="search" placeholder="جستجوی کشور… مثلا آمریکا" aria-label="جستجوی کشور"></label>
      <div class="num-meta">
        <span><?= siteIcon('globe') ?><?= siteFa(count($d['countries'])) ?> کشور</span>
        <?php if ($d['num_total'] > 0): ?><span><?= siteIcon('phone') ?><?= siteFa($d['num_total']) ?> سرویس فعال</span><?php endif; ?>
      </div>
    </div>
    <div class="ctry-grid" id="ctryGrid">
      <?php foreach ($d['countries'] as $c): ?>
      <button type="button" class="ctry" data-name="<?= siteE(mb_strtolower($c['name'])) ?>" <?= siteBuy('شماره مجازی ' . $c['name'], $c['from'] > 0 ? 'از ' . siteMoneyText($c['from']) : 'استعلام در ربات', 'انتخاب سرویس (تلگرام، واتساپ، …)') ?>>
        <span class="flag"><?= siteE($c['flag']) ?></span>
        <span class="ctry-t"><b><?= siteE($c['name']) ?></b><span><?= siteFa($c['n']) ?> سرویس</span></span>
        <span class="ctry-p"><?php if ($c['from'] > 0): ?>از<b class="amt"><?= siteFa($c['from']) ?></b>تومان<?php else: ?><span class="ask">استعلام</span><?php endif; ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="empty" id="ctryEmpty">کشوری با این نام در فهرست نیست — در ربات بین همه‌ی کشورها جستجو کنید.</div>
    <div class="num-steps">
      <div class="num-step rv"><i>۱</i><div><b>کشور و سرویس را انتخاب کنید</b><span>تلگرام، واتساپ و سرویس‌های دیگر.</span></div></div>
      <div class="num-step rv"><i>۲</i><div><b>شماره فوری تحویل می‌شود</b><span>شماره را در اپ موردنظر وارد کنید.</span></div></div>
      <div class="num-step rv"><i>۳</i><div><b>کد تایید را همان‌جا بگیرید</b><span>کد به‌صورت زنده در مینی‌اپ نمایش داده می‌شود.</span></div></div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═════════════ اینستاگرام ═════════════ -->
<section class="sec<?= $d['countries'] ? ' sec-alt' : '' ?>" id="instagram">
  <div class="wrap">
    <div class="ig-band">
      <div class="ig-top">
        <div class="rv">
          <span class="ig-logo"><?= siteIcon('insta') ?></span>
          <h2>افزایش <span class="gt-ig">فالوور، لایک و بازدید</span> اینستاگرام</h2>
          <p class="muted" style="margin-top:12px;max-width:560px;font-size:15.5px">پیج و پست‌هایتان را دیده‌تر کنید. فقط لینک پیج یا پست را بدهید — هیچ‌وقت رمز اینستاگرام از شما خواسته نمی‌شود. پیج باید در حالت عمومی (Public) باشد.</p>
          <div class="ig-points">
            <span><?= siteIcon('lock') ?> بدون نیاز به رمز</span>
            <span><?= siteIcon('clock') ?> شروع سریع</span>
            <span><?= siteIcon('refresh') ?> پیگیری سفارش در ربات</span>
          </div>
        </div>
        <?php if ($igQty): ?>
        <div class="ig-calc rv" id="igCalc">
          <h3>محاسبه‌ی سریع قیمت</h3>
          <label class="field"><span>نوع خدمت</span>
            <select id="igSvc">
              <?php foreach ($igQty as $ix => $o): ?>
              <option value="<?= $ix ?>" data-unit="<?= siteE((string)$o['unit_price']) ?>" data-min="<?= siteE((string)$o['min']) ?>" data-max="<?= siteE((string)$o['max']) ?>"><?= siteE($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>تعداد</span><input id="igQty" type="number" inputmode="numeric" value="1000" min="1"></label>
          <div class="ig-total"><span class="muted">مبلغ</span><b><span id="igTotal">—</span> <small class="cur">تومان</small></b></div>
          <button type="button" class="btn btn-ig btn-lg btn-block" id="igBuy" <?= siteBuy('خدمات اینستاگرام', '', 'لینک پیج یا پست + تعداد') ?>><?= siteIcon('insta') ?> ثبت سفارش</button>
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
          <article class="offer rv">
            <div class="offer-top"><span class="offer-ic ig"><?= siteIcon(siteKindIcon($k)) ?></span></div>
            <h3><?= siteE($t) ?></h3>
            <p class="muted"><?= siteE($p) ?></p>
            <div class="offer-price"><span class="ask">قیمت و موجودی در ربات</span></div>
            <button type="button" class="btn btn-soft btn-block" <?= siteBuy($t, '', 'لینک پیج یا پست + تعداد') ?>>ثبت سفارش <?= siteIcon('arrow') ?></button>
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
        <span class="kicker"><?= siteIcon('heart') ?> رشد کانال تلگرام</span>
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
<section class="sec sec-alt" id="how">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker"><?= siteIcon('bolt') ?> مراحل خرید</span>
      <h2>خرید در <span class="gt">چهار قدم ساده</span></h2>
      <p>همه‌چیز داخل ربات تلگرام انجام می‌شود — بدون ثبت‌نام، بدون فرم طولانی.</p>
    </div>
    <div class="steps">
      <div class="step rv"><div class="step-n"><?= siteIcon('bag') ?><i>۱</i></div><b>انتخاب خدمت</b><span>خدمت و بسته‌ی موردنظر را انتخاب کنید.</span></div>
      <div class="step rv"><div class="step-n"><?= siteIcon('send') ?><i>۲</i></div><b>ورود به ربات</b><span>ربات باز می‌شود و سفارش در مینی‌اپ ثبت می‌شود.</span></div>
      <div class="step rv"><div class="step-n"><?= siteIcon('wallet') ?><i>۳</i></div><b>پرداخت امن</b><span>از کیف پول یا روش‌های دیگر پرداخت کنید.</span></div>
      <div class="step rv"><div class="step-n"><?= siteIcon('check') ?><i>۴</i></div><b>تحویل خودکار</b><span>سفارش خودکار انجام و وضعیتش اطلاع داده می‌شود.</span></div>
    </div>
  </div>
</section>

<!-- ═════════════ چرا ما ═════════════ -->
<section class="sec" id="why">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker grn"><?= siteIcon('shield') ?> چرا <?= siteE($name) ?>؟</span>
      <h2>خریدی که <span class="gt">خیالتان از آن راحت است</span></h2>
    </div>
    <div class="why">
      <div class="why-c rv"><span class="why-ic" style="background:rgba(255,178,36,.13);color:var(--gold)"><?= siteIcon('bolt') ?></span><b>تحویل خودکار</b><span><?= siteE($tgNote) ?></span></div>
      <div class="why-c rv"><span class="why-ic" style="background:rgba(47,111,237,.13);color:var(--sky)"><?= siteIcon('tag') ?></span><b>قیمت لحظه‌ای و شفاف</b><span>قیمت‌ها بر اساس نرخ روز به‌روز می‌شوند؛ عددی که اینجا می‌بینید همان عدد فاکتور است.</span></div>
      <div class="why-c rv"><span class="why-ic" style="background:rgba(23,201,120,.13);color:var(--green)"><?= siteIcon('lock') ?></span><b>بدون رمز، بدون کد ورود</b><span>فقط آیدی یا لینک لازم است. هیچ‌وقت رمز یا کد ورود حسابتان را از شما نمی‌خواهیم.</span></div>
      <div class="why-c rv"><span class="why-ic" style="background:rgba(139,92,246,.14);color:#A78BFA"><?= siteIcon('refresh') ?></span><b>بازگشت وجه</b><span>سفارش‌ها خودکار پردازش می‌شوند؛ اگر مشکلی پیش بیاید مبلغ به کیف پول شما برمی‌گردد.</span></div>
      <div class="why-c rv"><span class="why-ic" style="background:rgba(236,72,153,.13);color:#F472B6"><?= siteIcon('wallet') ?></span><b>کیف پول داخلی</b><span>یک‌بار شارژ کنید و هر سفارش را با یک ضربه پرداخت کنید؛ اطلاعات بانکی هیچ‌جا ذخیره نمی‌شود.</span></div>
      <div class="why-c rv"><span class="why-ic" style="background:rgba(34,211,238,.13);color:var(--cyan)"><?= siteIcon('headset') ?></span><b>پشتیبانی همیشه در دسترس</b><span>برای هر سوال یا مشکلی، از داخل ربات با پشتیبانی در ارتباط باشید.</span></div>
    </div>
    <?php
    $stats = [];
    if (count($d['countries']) > 0) $stats[] = [siteFa(count($d['countries'])), 'کشور برای شماره مجازی'];
    if ($services > 0)              $stats[] = [siteFa($services), 'خدمت و بسته‌ی فعال'];
    if ($d['stats']['users'] >= 100)  $stats[] = [siteFa($d['stats']['users']), 'کاربر ربات'];
    if ($d['stats']['orders'] >= 100) $stats[] = [siteFa($d['stats']['orders']), 'سفارش تحویل‌شده'];
    if ($stats): ?>
    <div class="stats">
      <?php foreach ($stats as [$v, $l]): ?><div class="stat rv"><b class="gt"><?= $v ?></b><span><?= siteE($l) ?></span></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═════════════ روش‌های پرداخت ═════════════ -->
<section class="sec sec-alt" id="pay">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker"><?= siteIcon('card') ?> روش‌های پرداخت</span>
      <h2>هر طور راحت‌ترید <span class="gt">پرداخت کنید</span></h2>
    </div>
    <div class="pay">
      <div class="pay-c rv"><span class="mini-ic" style="background:var(--grad)"><?= siteIcon('wallet') ?></span><div><b>کیف پول ربات</b><span>پرداخت با یک ضربه از موجودی</span></div></div>
      <?php if ($d['pay']['card'] || !$d['live']): ?>
      <div class="pay-c rv"><span class="mini-ic" style="background:var(--grn-g)"><?= siteIcon('card') ?></span><div><b>کارت به کارت</b><span>شارژ کیف پول با کارت بانکی</span></div></div>
      <?php endif; ?>
      <?php if ($d['pay']['crypto'] || !$d['live']): ?>
      <div class="pay-c rv"><span class="mini-ic" style="background:var(--gold-g)"><?= siteIcon('coin') ?></span><div><b>ارز دیجیتال</b><span>پرداخت با تتر و ارزهای دیگر</span></div></div>
      <?php endif; ?>
      <?php if ($d['pay']['ton']): ?>
      <div class="pay-c rv"><span class="mini-ic" style="background:linear-gradient(135deg,#2AABEE,#0088CC)"><?= siteIcon('ton') ?></span><div><b>تون (TON)</b><span>خرید و انتقال تون به ولت شما</span></div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ═════════════ سوالات متداول ═════════════ -->
<section class="sec" id="faq">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker"><?= siteIcon('help') ?> سوالات متداول</span>
      <h2>جواب سوال‌های <span class="gt">پرتکرار</span></h2>
    </div>
    <div class="faq">
      <?php
      $faq = [
        ['خرید از سایت چطور انجام می‌شود؟', 'روی دکمه‌ی خرید هر خدمت بزنید تا ربات تلگرام باز شود. سفارش در مینی‌اپ ربات ثبت و از کیف پول یا روش‌های دیگر پرداخت می‌شود و بعد از پرداخت خودکار تحویل می‌گیرد.'],
        ['برای خرید استارز یا پریمیوم رمز یا کد ورود لازم است؟', 'خیر. فقط آیدی (یوزرنیم) تلگرام گیرنده لازم است. کد ورود تلگرام را هرگز به هیچ‌کس — حتی پشتیبانی — ندهید.'],
        ['تحویل سفارش چقدر طول می‌کشد؟', $tgNote . ' وضعیت هر سفارش را در بخش «سفارش‌ها» داخل ربات می‌بینید.'],
        ['قیمت‌ها چطور تعیین می‌شوند؟', 'قیمت‌ها بر اساس نرخ لحظه‌ای بازار محاسبه و مرتب به‌روز می‌شوند. قیمتی که در سایت می‌بینید از همان کاتالوگ ربات می‌آید و مبلغ نهایی همان است که در فاکتور ربات نمایش داده می‌شود.'],
        ['می‌توانم برای شخص دیگری استارز، پریمیوم یا گیفت بخرم؟', 'بله. کافی است هنگام ثبت سفارش آیدی تلگرام او را وارد کنید؛ استارز، اشتراک یا گیفت مستقیم به حساب او می‌رسد.'],
        ['شماره مجازی چطور کار می‌کند؟', 'کشور و سرویس موردنظر را انتخاب می‌کنید، شماره تحویل می‌شود و بعد از وارد کردن آن در اپ، کد تایید به‌صورت زنده در مینی‌اپ نمایش داده می‌شود.'],
        ['برای فالوور اینستاگرام رمز پیج لازم است؟', 'خیر. فقط لینک پیج یا پست لازم است. پیج باید در حالت عمومی (Public) باشد تا سفارش اجرا شود.'],
        ['اگر سفارشم انجام نشد چه می‌شود؟', 'سفارش‌ها خودکار پردازش می‌شوند؛ اگر مشکلی پیش بیاید مبلغ به کیف پول شما برمی‌گردد و از داخل ربات می‌توانید با پشتیبانی در ارتباط باشید.'],
      ];
      foreach ($faq as $ix => [$q, $a]): ?>
      <details class="rv"<?= $ix === 0 ? ' open' : '' ?>>
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
      <p>استارز، پریمیوم، گیفت، شماره مجازی و فالوور — همه در یک ربات، با پرداخت امن و تحویل خودکار.</p>
    </div>
    <div class="cta-acts">
      <?php if ($bot !== ''): ?><a class="btn btn-lg" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ورود به ربات</a><?php endif; ?>
      <?php if ($channel !== ''): ?><a class="btn btn-lg btn-line" href="<?= siteE($channel) ?>" target="_blank" rel="noopener">کانال ما</a><?php endif; ?>
    </div>
  </div>
</section>

</main>

<footer class="ftr">
  <div class="wrap">
    <div class="ftr-grid">
      <div>
        <a href="#top" class="logo"><span class="logo-mark"><?= siteIcon('star') ?></span><span><?= siteE($name) ?></span></a>
        <p>فروشگاه خدمات تلگرام و اینستاگرام: استارز، پریمیوم، گیفت، شماره مجازی، فالوور و ری‌اکشن — با قیمت لحظه‌ای و تحویل خودکار.</p>
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
  <button type="button" class="top" id="toTop" aria-label="بازگشت به بالا"><?= siteIcon('up') ?></button>
  <?php if ($support !== ''): ?><a class="sup" href="<?= siteE($support) ?>" target="_blank" rel="noopener" aria-label="پشتیبانی"><?= siteIcon('headset') ?></a><?php endif; ?>
</div>

<div class="modal" id="buyModal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal-bg" data-close></div>
  <div class="modal-card">
    <button type="button" class="icon-btn modal-x" data-close aria-label="بستن"><?= siteIcon('close') ?></button>
    <div class="modal-ic"><?= siteIcon('bag') ?></div>
    <h3 id="mTitle">—</h3>
    <div class="modal-price"><span class="muted">قیمت</span><b id="mPrice">—</b></div>
    <ol>
      <li>دکمه‌ی «ادامه در ربات» را بزنید تا ربات در تلگرام باز شود.</li>
      <li>در فروشگاه ربات همین خدمت را انتخاب کنید و <span id="mNeed">اطلاعات لازم</span> را وارد کنید.</li>
      <li>پرداخت کنید؛ سفارش خودکار انجام می‌شود.</li>
    </ol>
    <?php if ($bot !== ''): ?>
    <a class="btn btn-pri btn-lg btn-block" href="<?= siteE($bot) ?>" target="_blank" rel="noopener"><?= siteIcon('send') ?> ادامه در ربات</a>
    <?php else: ?>
    <div class="btn btn-soft btn-lg btn-block" aria-disabled="true">ربات به‌زودی در دسترس است</div>
    <?php endif; ?>
    <div class="modal-warn"><?= siteIcon('shield') ?> هیچ‌وقت رمز یا کد ورود حساب خود را برای کسی نفرستید؛ برای خرید فقط آیدی یا لینک لازم است.</div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var $ = function(s, r){ return (r || document).querySelector(s); };
  var $$ = function(s, r){ return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function fa(n){ n = Math.round(Number(n) || 0); try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); } }
  function num(v){ v = String(v || '').replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[^\d.]/g, ''); return Number(v) || 0; }

  // ── تم ──
  var root = document.documentElement;
  $('#theme').addEventListener('click', function(){
    var cur = root.getAttribute('data-theme') ||
      (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    var next = cur === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('site-theme', next); } catch(e){}
  });

  // ── هدر و دکمه‌ی بالا ──
  var hdr = $('#hdr'), top = $('#toTop');
  function onScroll(){
    var y = window.scrollY || 0;
    hdr.classList.toggle('scrolled', y > 8);
    top.classList.toggle('show', y > 700);
  }
  window.addEventListener('scroll', onScroll, {passive: true}); onScroll();
  top.addEventListener('click', function(){ window.scrollTo({top: 0, behavior: 'smooth'}); });

  // ── منوی موبایل ──
  var drawer = $('#drawer'), mb = $('#menuBtn');
  mb.addEventListener('click', function(){
    var o = drawer.classList.toggle('open');
    mb.setAttribute('aria-expanded', o ? 'true' : 'false');
  });
  $$('#drawer a').forEach(function(a){ a.addEventListener('click', function(){ drawer.classList.remove('open'); mb.setAttribute('aria-expanded', 'false'); }); });

  // ── ظاهر شدن با اسکرول ──
  var rv = $$('.rv');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(es){
      es.forEach(function(e){ if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, {rootMargin: '0px 0px -40px 0px', threshold: 0.06});
    rv.forEach(function(el){ io.observe(el); });
  } else rv.forEach(function(el){ el.classList.add('in'); });

  // ── پنجره‌ی خرید ──
  var modal = $('#buyModal'), lastFocus = null;
  function openBuy(btn){
    lastFocus = btn;
    $('#mTitle').textContent = btn.getAttribute('data-name') || '';
    $('#mPrice').textContent = btn.getAttribute('data-price') || 'استعلام در ربات';
    $('#mNeed').textContent = btn.getAttribute('data-need') || 'اطلاعات لازم';
    modal.classList.add('open');
    var f = $('.modal-card a, .modal-card button', modal); if (f) f.focus();
  }
  function closeBuy(){ modal.classList.remove('open'); if (lastFocus) lastFocus.focus(); }
  document.addEventListener('click', function(e){
    var b = e.target.closest('[data-buy]'); if (b) { e.preventDefault(); openBuy(b); return; }
    if (e.target.closest('[data-close]')) closeBuy();
  });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('open')) closeBuy(); });

  // ── ماشین‌حساب استارز ──
  var sc = $('#starCalc');
  if (sc) {
    var unit = Number(sc.getAttribute('data-unit')) || 0, min = Number(sc.getAttribute('data-min')) || 1, max = Number(sc.getAttribute('data-max')) || 1e6;
    var qi = $('#starQty'), rg = $('#starRange'), buy = $('#starBuy');
    function setQ(q, from){
      q = Math.max(min, Math.min(max, Math.round(q) || min));
      if (from !== 'input') qi.value = q;
      rg.value = Math.min(Number(rg.max), q);
      $('#starTotal').textContent = fa(q * unit);
      $('#starBuyQ').textContent = fa(q);
      buy.setAttribute('data-name', fa(q) + ' استارز');
      buy.setAttribute('data-price', fa(q * unit) + ' تومان');
      $$('.quick button', sc).forEach(function(b){ b.classList.toggle('on', Number(b.getAttribute('data-q')) === q); });
    }
    qi.addEventListener('input', function(){ setQ(num(qi.value), 'input'); });
    qi.addEventListener('blur', function(){ setQ(num(qi.value)); });
    rg.addEventListener('input', function(){ setQ(Number(rg.value)); });
    $$('[data-step]', sc).forEach(function(b){ b.addEventListener('click', function(){ setQ(num(qi.value) + Number(b.getAttribute('data-step'))); }); });
    $$('.quick button', sc).forEach(function(b){ b.addEventListener('click', function(){ setQ(Number(b.getAttribute('data-q'))); }); });
    setQ(num(qi.value));
  }

  // ── ماشین‌حساب اینستاگرام ──
  var ig = $('#igCalc');
  if (ig) {
    var sel = $('#igSvc'), iq = $('#igQty'), ib = $('#igBuy');
    function igUpd(){
      var o = sel.options[sel.selectedIndex]; if (!o) return;
      var u = Number(o.getAttribute('data-unit')) || 0, mn = Number(o.getAttribute('data-min')) || 1, mx = Number(o.getAttribute('data-max')) || 1e9;
      var q = Math.max(mn, Math.min(mx, num(iq.value) || mn));
      iq.min = mn;
      $('#igTotal').textContent = fa(q * u);
      ib.setAttribute('data-name', o.textContent + ' — ' + fa(q) + ' عدد');
      ib.setAttribute('data-price', fa(q * u) + ' تومان');
    }
    sel.addEventListener('change', igUpd); iq.addEventListener('input', igUpd); igUpd();
  }

  // ── تب‌ها ──
  $$('[data-tabs]').forEach(function(bar){
    var grid = document.getElementById(bar.getAttribute('data-tabs'));
    bar.addEventListener('click', function(e){
      var t = e.target.closest('.tab'); if (!t) return;
      $$('.tab', bar).forEach(function(x){ x.classList.toggle('on', x === t); });
      var k = t.getAttribute('data-k');
      $$('.offer', grid).forEach(function(o){ o.classList.toggle('hide', k !== 'all' && o.getAttribute('data-kind') !== k); });
    });
  });

  // ── جستجوی کشور ──
  var cs = $('#ctrySearch');
  if (cs) {
    var items = $$('#ctryGrid .ctry'), empty = $('#ctryEmpty');
    cs.addEventListener('input', function(){
      var q = cs.value.trim().toLowerCase(), n = 0;
      items.forEach(function(it){ var ok = !q || it.getAttribute('data-name').indexOf(q) !== -1; it.style.display = ok ? '' : 'none'; if (ok) n++; });
      empty.style.display = n ? 'none' : 'block';
    });
  }
})();
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
