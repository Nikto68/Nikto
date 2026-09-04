<?php
/**
 * 🧩 مینی‌اپِ یکپارچه — «فروشگاه»: هر سه اپِ قدیمی (خدمات تلگرام،
 * شماره مجازی، ری‌اکشن/استوری) پشتِ یک آدرس و یک ناوبری.
 *
 * هیچ‌کدام از منطقِ سفارش/پرداخت/تحویل عوض نشده — این فایل فقط یک
 * پوسته‌ی تازه است که کاتالوگِ سه‌تایی (از maBootUnified) را نشان
 * می‌دهد و برایِ هر سفارش، app درستِ همان آیتم را به maApi() می‌فرستد.
 *
 * پنج تبِ اصلی: خانه · فروشگاه · ایردراپ · حساب من · بیشتر.
 * «ایردراپ» خودش یک زیربرنامه‌ی پنج‌تبی است (معدن/ماموریت/پاداش/
 * رفرال/لیدربورد) با ناوبریِ پایینیِ مالِ خودش — دقیقا همان رفتاری که
 * در تصاویرِ مرجع دیده شد: با زدنِ آیکونِ ایردراپ، یک صفحه‌ی تازه با
 * منویِ تازه باز می‌شود.
 *
 * قاعده‌های سرعت که نباید شکسته شوند (همان قاعده‌های سه مینی‌اپِ قبلی):
 *   • backdrop-filter فقط روی چند سطحِ ثابت — هرگز روی کارت‌های تکرارشونده
 *   • کارت‌ها یک‌بار ساخته می‌شوند؛ صفحه‌ها با display جابه‌جا می‌شوند
 *   • هر انیمیشن فقط transform/opacity — با prefers-reduced-motion خاموش
 */

function maViewUnified($boot) {
    return strtr(maTplUnified(), [
        '__TITLE__' => htmlspecialchars((string)$boot['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function maTplUnified() {
    return str_replace('__SKIN__', maSkinNova(), maTplUnifiedBody());
}

function maTplUnifiedBody() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
__SKIN__
</head>
<body>
<div class="splash" id="splash">
  <div class="splash-grid"></div>
  <div class="splash-dot d1"></div><div class="splash-dot d2"></div><div class="splash-dot d3"></div><div class="splash-dot d4"></div>
  <div class="splash-mid">
    <div class="splash-icon"><i>🛍</i></div>
    <h1 class="splash-name">فروشگاه</h1>
    <p class="splash-sub">استارز · شماره مجازی · ری‌اکشن و استوری — همه در یک‌جا</p>
  </div>
  <div class="splash-foot">
    <div class="splash-pager"><i></i><i></i><i></i></div>
    <div class="splash-pwr">در حال بارگذاری</div>
  </div>
</div>
<div class="sky"></div>
<div class="veil"></div>

<div class="wrap">
  <div class="top">
    <div class="ava" id="ava">🛍</div>
    <div class="who">
      <h1 id="ttl">—</h1>
      <div class="chipbal" id="balChip"><b>+</b><span id="balTop">…</span><em id="curTop"></em></div>
    </div>
    <button class="bell" id="bell" aria-label="اعلان‌ها">🔔<span class="bdot"></span></button>
    <button class="cta" id="topCta">＋ شارژ</button>
  </div>

  <!-- ══ خانه ══ -->
  <section class="pg on" id="pgHome">
    <div class="verify" id="verifyBox" style="display:none">
      <i>⚠️</i>
      <div><b>یوزرنیم تلگرام‌تان را ثبت کنید</b><span>برای پیگیریِ راحت‌تر سفارش و پشتیبانی سریع‌تر.</span></div>
    </div>

    <div class="promoRow" id="promoRow">
      <div class="promo p1"><b>🎁 خوش‌آمدگویی</b><span>اولین خرید، اولین تجربه‌ی آنی</span></div>
      <div class="promo p2"><b>💎 ایردراپ فعال شد</b><span>کریستال جمع کن، جایزه بگیر</span></div>
    </div>

    <div class="sect"><h2><s></s><span>دسترسی سریع</span></h2></div>
    <div class="qgrid" id="qgrid"></div>

    <div class="sect"><h2><s></s><span id="catsTtl">دسته‌بندی‌ها</span></h2>
      <a id="goShop">همه</a></div>
    <div class="rail" id="rail"></div>

    <div class="sect" id="bestSect" style="display:none"><h2><s></s><span>پرفروش‌های این ماه</span></h2></div>
    <div id="bestList"></div>

    <div class="trustwrap" id="trustwrap"></div>
  </section>

  <!-- ══ فروشگاه ══ -->
  <section class="pg" id="pgShop">
    <div class="find"><input id="q" placeholder="جستجو در همه‌ی خدمات…"><span>🔎</span></div>
    <div class="tabs" id="tabs"></div>
    <div class="grid" id="grid">
      <div class="skel"></div><div class="skel"></div>
    </div>
  </section>

  <!-- ══ ایردراپ ══ -->
  <section class="pg" id="pgAir">
    <div class="airhead">
      <button class="airback" id="airBack">‹ فروشگاه</button>
      <b id="airTtl">ایردراپ</b>
      <span></span>
    </div>

    <!-- معدن -->
    <div class="airpg on" id="airMine">
      <div class="ringwrap">
        <svg viewBox="0 0 140 140" class="ring">
          <defs>
            <linearGradient id="ringGrad" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#F2B705"/><stop offset="1" stop-color="#6C5CE7"/>
            </linearGradient>
          </defs>
          <circle cx="70" cy="70" r="60" class="ringbg"/>
          <circle cx="70" cy="70" r="60" class="ringfg" id="ringFg"/>
        </svg>
        <div class="ringmid">
          <i>💎</i>
          <b id="crystalNow">۰</b>
          <span id="crystalOf">از … کریستال</span>
        </div>
      </div>
      <div class="ratebox" id="rateBox">۱۲ کریستال در ساعت</div>
      <div class="endbox" id="endBox">پایان فصل: —</div>
      <div class="statrow">
        <div class="stat"><i>⭐️</i><b id="stLevel">۱</b><span>سطح</span></div>
        <div class="stat"><i>⚡️</i><b id="stRate">×۱</b><span>سرعت استخراج</span></div>
        <div class="stat"><i>💎</i><b id="stTotal">۰</b><span>کل کریستال</span></div>
      </div>
    </div>

    <!-- ماموریت‌ها -->
    <div class="airpg" id="airMissions">
      <div class="sect"><h2><s></s><span>ماموریت‌ها</span></h2></div>
      <div id="missionList"><div class="void"><div>🎯</div>در حال خواندن…</div></div>
    </div>

    <!-- پاداش‌ها -->
    <div class="airpg" id="airRewards">
      <div class="sect"><h2><s></s><span>تبدیل کریستال</span></h2></div>
      <div class="pane">
        <h3>💱 کریستال به کیف‌پول</h3>
        <p class="rhint" id="redeemHint">—</p>
        <div class="amt"><input id="redeemAmt" type="text" inputmode="numeric" placeholder="مقدار کریستال"></div>
        <button class="go" id="redeemGo" style="margin-top:13px">تبدیل کن</button>
        <div class="walbox" id="redeemNote"></div>
      </div>
    </div>

    <!-- رفرال -->
    <div class="airpg" id="airRef">
      <div class="statrow">
        <div class="stat"><i>👥</i><b id="refCount">۰</b><span>دعوت معتبر</span></div>
        <div class="stat"><i>💰</i><b id="refEarned">۰</b><span>درآمد کل</span></div>
        <div class="stat"><i>👛</i><b id="refPending">۰</b><span>قابل برداشت</span></div>
      </div>
      <button class="go" id="refShare" style="margin-top:14px">🔗 اشتراک‌گذاری لینک دعوت</button>
      <div class="sect"><h2><s></s><span>برترین معرف‌ها</span></h2></div>
      <div id="refTop"><div class="void"><div>🏆</div>در حال خواندن…</div></div>
    </div>

    <!-- لیدربورد -->
    <div class="airpg" id="airLeader">
      <div class="sect"><h2><s></s><span>لیدربورد فصل</span></h2></div>
      <div id="leaderList"><div class="void"><div>🏆</div>در حال خواندن…</div></div>
    </div>

    <nav class="adock" id="adock"></nav>
  </section>

  <!-- ══ حساب من ══ -->
  <section class="pg" id="pgMe">
    <div class="prof">
      <div class="big" id="meAva">🛍</div>
      <div class="d">
        <b id="meName">—</b>
        <span id="meUser"></span>
        <code id="meId"></code>
      </div>
    </div>

    <div class="pane" id="topPane">
      <h3>💳 <span>شارژ کیف پول</span></h3>
      <div id="cardBox"></div>
      <div class="amt"><input id="amt" type="text" inputmode="numeric" placeholder="مبلغ به تومان"></div>
      <div class="quick" id="amtQuick"></div>
      <button class="go" id="topGo" style="margin-top:13px">ثبت درخواست شارژ</button>
      <div class="walbox" id="topNote"></div>
    </div>

    <div class="sect"><h2><s></s><span>سفارش‌های اخیر</span></h2></div>
    <div id="ordList"><div class="void"><div>🧾</div>در حال خواندن…</div></div>
  </section>

  <!-- ══ بیشتر ══ -->
  <section class="pg" id="pgMore">
    <div class="pane">
      <h3>ℹ️ راهنما</h3>
      <div class="link" id="lnkAir"><s>💎</s><em>ایردراپ</em><s>‹</s></div>
      <div class="link" id="lnkReports"><s></s><em>چنل گزارشات</em><s>‹</s></div>
      <div class="link" id="lnkBot"><s>🤖</s><em>بازگشت به ربات</em><s>‹</s></div>
    </div>
  </section>

  <!-- ══ 🔔 اعلان‌ها ══ -->
  <section class="pg" id="pgNote">
    <div class="sect"><h2><s></s><span>اعلان‌ها</span></h2></div>
    <div id="noteList"><div class="void"><div>🔔</div>هنوز خبری نیست.</div></div>
  </section>
</div>

<nav class="dock" id="dock"></nav>

<div class="scrim" id="scrim"></div>
<div class="sheet" id="sheet">
  <div class="grip"></div>
  <div class="head">
    <div class="orb" id="sOrb">💠</div>
    <div style="flex:1;min-width:0"><h2 id="sName">—</h2><p id="sDesc"></p></div>
  </div>
  <div id="sField"></div>
  <div class="total"><span>مبلغ قابل پرداخت</span><b id="sTotal">۰</b></div>
  <button class="go" id="sWal">پرداخت از کیف وپول</button>
  <button class="go alt" id="sGo">شارژ حساب</button>
  <div class="walbox" id="sWalNote"></div>
  <button class="ghost" id="sNo">بستن</button>
</div>

<div class="win" id="win">
  <div>
    <div class="ring2">✓</div>
    <h2 id="wTtl">سفارش ثبت شد</h2>
    <p id="wSub"></p>
    <div class="code" id="wCode"></div>
    <button class="go" id="wNote" style="max-width:280px">🔔 مشاهده فرآیند خرید</button>
    <button class="ghost" id="wBack" style="max-width:280px;margin:9px auto 0">ادامه خرید</button>
    <button class="ghost" id="wGo" style="max-width:280px;margin:9px auto 0">بازگشت به ربات</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B  = __BOOT__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };

var splashStart = Date.now();
var splashGone = false;
var SPLASH_MIN = 5000;
var SPLASH_MAX = 8000;
function hideSplash(){
  if (splashGone) return; splashGone = true;
  var wait = Math.max(0, SPLASH_MIN - (Date.now() - splashStart));
  setTimeout(function(){
    var s = $('splash'); if (!s) return;
    s.classList.add('hide');
    setTimeout(function(){ s.remove(); }, 500);
  }, wait);
}
setTimeout(hideSplash, SPLASH_MAX);

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}
function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}
function digits(s){
  s = String(s == null ? '' : s);
  var out = '', fa0 = 1776, ar0 = 1632, dot = false;
  for (var i=0;i<s.length;i++){
    var ch = s[i], c = s.charCodeAt(i);
    if (c >= fa0 && c <= fa0+9) out += (c - fa0);
    else if (c >= ar0 && c <= ar0+9) out += (c - ar0);
    else if (ch >= '0' && ch <= '9') out += ch;
    else if ((ch === '.' || c === 0x066B) && !dot) { out += '.'; dot = true; }
  }
  return out;
}
function intIn(s){ return Math.floor(Number(digits(s)) || 0); }

$('ttl').textContent = B.title;
$('curTop').textContent = B.currency;
document.title = B.title;

var S = { page:'home', cat:'', q:'', item:null, qty:1, busy:false, bal:0, nodes:[], me:null, gridBuilt:false };

function api(appKey, action, extra, ok, bad){
  if (!B.api){ bad({ message:'آدرس سرور مینی‌اپ تنظیم نشده است. با پشتیبانی تماس بگیرید.' }); return; }
  var body = Object.assign({ action:action, app:appKey,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  var ctl = null, timer = null;
  try { ctl = new AbortController(); timer = setTimeout(function(){ ctl.abort(); }, 20000); } catch(e){}
  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined,
    cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'پاسخ سرور نامعتبر بود.'}; }); })
    .then(function(j){ if (timer) clearTimeout(timer); if (j && j.ok) ok(j); else bad(j || {}); })
    .catch(function(){ if (timer) clearTimeout(timer); bad({ message:'ارتباط با سرور برقرار نشد.' }); });
}

/* ── آیکون‌های شیشه‌ای — همان زبانِ بصریِ سه مینی‌اپِ دیگر، نه اموجیِ خام:
   خطِ نازک + پرشدنِ ظریف، با یک سایه‌ی نوری که فقط رویِ آیتمِ فعال روشن
   می‌شود. تاکیدِ صریحِ کارفرما همین بود: «بیشتر روی آیکون‌ها کار کن». */
var ICONS = {
  home:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M3.4 10.6L12 3.4l8.6 7.2v9.4H3.4z"/><path d="M9.4 20v-6h5.2v6"/></svg>',
  bag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M4.4 8h15.2l-1.2 12.4H5.6z"/><path class="i-lid" d="M8.6 8V6.2a3.4 3.4 0 016.8 0V8"/></svg>',
  user:  '<svg viewBox="0 0 24 24"><circle class="i-float" cx="12" cy="8" r="4.2"/><path d="M3.8 21c.6-4.6 4-7 8.2-7s7.6 2.4 8.2 7"/></svg>',
  people:'<svg viewBox="0 0 24 24"><circle class="i-float" cx="9" cy="8.2" r="3.4"/><circle cx="16.6" cy="9.4" r="2.7"/><path d="M2.6 20.6c.5-3.9 3.2-6 6.4-6s5.9 2.1 6.4 6"/><path d="M15.6 15c2.4.3 4.1 2 4.6 5.1"/></svg>',
  crown: '<svg viewBox="0 0 24 24"><path class="fl i-float" d="M3 8.4l4.2 3.2L12 4.6l4.8 7 4.2-3.2-1.7 9.6H4.7z"/><rect class="fl" x="4.4" y="19" width="15.2" height="2.1" rx="1"/></svg>',
  gift:  '<svg viewBox="0 0 24 24"><rect x="3.4" y="9.8" width="17.2" height="10.8" rx="2"/><path class="i-lid" d="M2.4 6.4h19.2v3.6H2.4z"/><path d="M12 6.4v14.2"/><path class="i-lid" d="M12 6.4C10.6 3 6.6 3.4 7.2 6.4M12 6.4c1.4-3.4 5.4-3 4.8 0"/></svg>',
  target:'<svg viewBox="0 0 24 24"><circle class="i-float" cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5.1"/><circle class="fl i-pulse" cx="12" cy="12" r="1.7"/></svg>',
  gem:   '<svg viewBox="0 0 24 24"><g class="i-spin"><path d="M4 9.2L12 21l8-11.8L16.6 3H7.4z"/><path d="M4 9.2h16M9.2 9.2L12 21l2.8-11.8M7.4 3l1.8 6.2M16.6 3l-1.8 6.2"/></g></svg>',
  star:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5 6.1 20.6l1.2-6.5L2.5 9.5l6.6-.9z"/></svg>',
  heart: '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 20.4c-4-2.6-9-6.4-9-11A5 5 0 0112 6.2 5 5 0 0121 9.4c0 4.6-5 8.4-9 11z"/></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><ellipse class="i-pulse" cx="12" cy="12" rx="4" ry="9"/></svg>',
  grid:  '<svg viewBox="0 0 24 24"><rect class="i-float" x="3.2" y="3.2" width="7.4" height="7.4" rx="2"/><rect x="13.4" y="3.2" width="7.4" height="7.4" rx="2"/><rect x="3.2" y="13.4" width="7.4" height="7.4" rx="2"/><rect class="i-pulse" x="13.4" y="13.4" width="7.4" height="7.4" rx="2"/></svg>',
  box:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M12 2.8l8.4 4.4v9.6L12 21.2 3.6 16.8V7.2z"/><path class="i-float" d="M3.6 7.2L12 11.6l8.4-4.4M12 11.6v9.6"/></svg>',
  clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.8"/><path class="i-tick" d="M12 12V6.6"/><path d="M12 12l3.6 2.2"/></svg>',
  inf:   '<svg viewBox="0 0 24 24"><path class="i-draw" d="M8.4 8.2a3.8 3.8 0 100 7.6c3 0 4.2-7.6 7.2-7.6a3.8 3.8 0 110 7.6c-3 0-4.2-7.6-7.2-7.6z"/></svg>',
  wallet:'<svg viewBox="0 0 24 24"><rect x="3" y="6.2" width="18" height="13" rx="2.6"/><path d="M3 10.4h18"/><circle class="fl i-pulse" cx="17" cy="14.6" r="1.5"/></svg>',
  bolt:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.4 2.2L4.6 13.6h5.4l-.8 8.2 9-11.6h-5.4z"/></svg>',
  shield:'<svg viewBox="0 0 24 24"><path class="i-draw" d="M12 2.6l8 3v6.2c0 5-3.4 8.7-8 9.8-4.6-1.1-8-4.8-8-9.8V5.6z"/><path class="i-draw" d="M8.6 12.2l2.4 2.4 4.6-4.8"/></svg>',
  tag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M11.4 2.8h9.8v9.8L11.6 22.2 1.8 12.4z"/><circle class="fl i-pulse" cx="17" cy="7" r="1.7"/></svg>',
  menu:  '<svg viewBox="0 0 24 24"><path d="M3.5 6.6h17M3.5 12h17M3.5 17.4h17"/></svg>',
  headset:'<svg viewBox="0 0 24 24"><path class="i-draw" d="M4.4 13.2v-1.4a7.6 7.6 0 0115.2 0v1.4"/><rect x="3" y="13.2" width="4.6" height="6.6" rx="2.1"/><rect x="16.4" y="13.2" width="4.6" height="6.6" rx="2.1"/><path d="M19 19.8v.5a2.5 2.5 0 01-2.5 2.5h-2.7"/></svg>'
};
var ICO_MAP = [
  [/star|استار|ستار/i,                'star'],
  [/gift|گیفت|هدیه|teddy|تدی/i,       'gift'],
  [/\bton\b|تون|tonco|الماس|کریستال/i,'gem'],
  [/react|ری‌اکشن|لایک|قلب|heart/i,   'heart'],
  [/vol|حجم|گیگ|گیگا|مگ|giga|\bgb\b/i,'box'],
  [/time|زمان|روز|ماه|month|day/i,    'clock'],
  [/unlim|نامحدود|بی.?نهایت/i,        'inf'],
  [/loc|کشور|لوکیشن|country|سرور|شماره|مجازی|num|phone/i, 'globe'],
  [/امن|secure|shield/i,              'shield'],
  [/fast|سریع|توربو|turbo|speed/i,    'bolt'],
  [/off|تخفیف|حراج|discount/i,        'tag'],
  [/acc|اکانت|account|عضو|member/i,   'user'],
  [/pack|بسته|فروش|shop|buy/i,        'bag']
];
function icoFor(c){
  var key = String(c.id || '') + ' ' + String(c.name || '');
  for (var i = 0; i < ICO_MAP.length; i++) if (ICO_MAP[i][0].test(key)) return ICONS[ICO_MAP[i][1]];
  return ICONS.bag;
}

var toastT;
function toast(m, good){
  var t = $('toast');
  t.textContent = m;
  t.classList.toggle('ok', !!good);
  t.classList.add('on');
  clearTimeout(toastT);
  toastT = setTimeout(function(){ t.classList.remove('on'); }, 3600);
  buzz(good ? 'success' : 'error');
}

function setBal(v){
  S.bal = Number(v) || 0;
  $('balTop').textContent = fa(S.bal);
  if (S.item) walletState();
}

/* ── ناوبریِ اصلی ── */
var DOCK_PAGES = [
  { id:'home', ico:'home', name:'خانه' },
  { id:'shop', ico:'bag',  name:'فروشگاه' },
  { id:'air',  ico:'gem',  name:'ایردراپ', cls:'airbtn' },
  { id:'me',   ico:'user', name:'حساب من' },
  { id:'more', ico:'menu', name:'بیشتر' }
];
(function drawDock(){
  var h = '';
  DOCK_PAGES.forEach(function(p){
    h += '<b data-p="' + p.id + '"' + (p.cls ? ' class="' + p.cls + (p.id==='home' ? ' on' : '') + '"' : (p.id==='home' ? ' class="on"' : '')) + '>' +
         '<i class="navi">' + ICONS[p.ico] + '</i><span>' + esc(p.name) + '</span></b>';
  });
  $('dock').innerHTML = h;
})();

var PAGE_IDS = ['home','shop','air','me','more','note'];
function go(page, silent){
  if (page === 'shop') ensureGridBuilt();
  if (page === 'air' && !AD.loaded) airLoad();
  if (S.page === page && silent) return;
  S.page = page;
  PAGE_IDS.forEach(function(p){
    var sec = $('pg' + p.charAt(0).toUpperCase() + p.slice(1));
    if (sec) sec.classList.toggle('on', p === page);
  });
  var dockBtns = $('dock').children;
  for (var i=0;i<dockBtns.length;i++)
    dockBtns[i].classList.toggle('on', dockBtns[i].getAttribute('data-p') === page);
  // ایردراپ ناوبریِ پایینیِ خودش (adock) را دارد؛ جزیره‌ی اصلی هم position:fixed
  // است و دقیقا همان‌جا می‌نشیند، پس تا وقتی ایردراپ باز است باید کنار برود —
  // وگرنه رویِ هم می‌افتند و کلیک‌های adock را جزیره‌ی اصلی می‌قاپد.
  $('dock').classList.toggle('hidden', page === 'air');
  window.scrollTo({ top:0, behavior: silent ? 'auto' : 'smooth' });
  if (page === 'me') { drawOrders(); }
  if (page === 'note') loadNotes();
  backBtn();
}
$('dock').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  tap(); go(el.getAttribute('data-p'));
});
$('bell').addEventListener('click', function(){ tap(); go('note'); });
$('goShop').onclick = function(){ tap(); S.cat=''; drawTabs(); applyFilter(); go('shop'); };
$('topCta').onclick = function(){ tap(); go('me'); };
$('lnkAir').onclick = function(){ tap(); go('air'); };
$('lnkReports').onclick = function(){
  tap();
  try { TG && TG.openTelegramLink('https://t.me/ReportsNik'); }
  catch (e) { window.open('https://t.me/ReportsNik', '_blank'); }
};
$('lnkBot').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } };

function backBtn(){
  if (!TG || !TG.BackButton) return;
  try {
    if (S.item || S.page !== 'home') TG.BackButton.show();
    else TG.BackButton.hide();
  } catch(e){}
}
if (TG && TG.BackButton){
  try { TG.BackButton.onClick(function(){
    if (S.item) { shut(); return; }
    if (S.page !== 'home') go('home');
  }); } catch(e){}
}

/* ── من ── */
api('unified', 'me_all', {}, function(j){
  S.me = j;
  setBal(j.balance);
  var nm = ''; // پر می‌شود از initData سمتِ کلاینت اگر تلگرام بدهد
  $('meName').textContent = B.title;
  drawOrders();
  hideSplash();
}, function(j){
  setBal(0);
  if (j && j.message) toast(j.message);
  hideSplash();
});
if (TG && TG.initDataUnsafe && TG.initDataUnsafe.user){
  var tu = TG.initDataUnsafe.user;
  var nm2 = (tu.first_name || '').trim();
  if (nm2) { $('ttl').textContent = 'سلام ' + nm2 + ' 👋'; $('meName').textContent = nm2; }
  $('meUser').textContent = tu.username ? '@' + tu.username : '';
  $('meId').textContent = 'ID: ' + (tu.id || '');
  var ch = (nm2 || 'ش').charAt(0);
  $('ava').textContent = ch; $('meAva').textContent = ch;
  if (!tu.username){
    var vb = $('verifyBox'); if (vb) vb.style.display = '';
  }
}

/* ── میان‌بر دسته‌ها (خانه) ── */
function catAppLabel(c){
  var a = B.apps && B.apps[c.app];
  return a ? a.title : '';
}
(function drawRail(){
  var h = '';
  (B.cats || []).forEach(function(c){
    h += '<div class="rc" data-c="' + esc(c.id) + '" data-app="' + esc(c.app) + '">' +
         '<i class="ico">' + icoFor(c) + '</i>' +
         '<span>' + esc(c.name) + '</span></div>';
  });
  $('rail').innerHTML = h;
})();
$('rail').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.rc') : null;
  if (!el) return;
  tap(); S.cat = el.getAttribute('data-c'); S.q = ''; $('q').value = '';
  drawTabs(); applyFilter(); go('shop');
});

/* ── دسترسیِ سریع (خانه) ── */
(function drawQGrid(){
  var apps = B.apps || {};
  var order = [['tg','star'], ['num','globe'], ['react','heart']];
  var h = '';
  order.forEach(function(pair){
    var k = pair[0]; if (!apps[k]) return;
    h += '<div class="qtile" data-app="' + k + '"><i class="ico">' + ICONS[pair[1]] + '</i><span>' + esc(apps[k].title) + '</span></div>';
  });
  h += '<div class="qtile air" data-air="1"><i class="ico">' + ICONS.gem + '</i><span>ایردراپ</span></div>';
  $('qgrid').innerHTML = h;
})();

/* ── ردیف‌های اعتمادسازی — بج‌های دایره‌ایِ بزرگ، طبقِ مرجعِ کارفرما ── */
(function drawTrust(){
  var rows = [
    { c:'--c1', ico:'bolt',    ttl:'تحویل آنی',        sub:'سفارش‌ها معمولا در چند دقیقه انجام می‌شود' },
    { c:'--c2', ico:'shield',  ttl:'پرداخت امن',        sub:'کیف‌پول داخلی یا ارز، هرکدام راحت‌ترید' },
    { c:'--c3', ico:'headset', ttl:'پشتیبانی ۲۴ ساعته', sub:'هر مشکلی بود، همیشه یک پیام فاصله دارید' }
  ];
  var h = '';
  rows.forEach(function(r){
    h += '<div class="trust" style="--tc:var(' + r.c + ')">' +
           '<i class="trustico">' + ICONS[r.ico] + '</i>' +
           '<div><b>' + esc(r.ttl) + '</b><span>' + esc(r.sub) + '</span></div>' +
         '</div>';
  });
  $('trustwrap').innerHTML = h;
})();
$('qgrid').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.qtile') : null;
  if (!el) return;
  tap();
  if (el.getAttribute('data-air')) { go('air'); return; }
  var app = el.getAttribute('data-app');
  S.cat = ''; S.q = '';
  $('tabs').setAttribute('data-app-filter', app || '');
  applyFilter(); go('shop');
});

/* ── چیپ دسته‌ها (فروشگاه) ── */
function drawTabs(){
  var h = '<b class="' + (S.cat===''?'on':'') + '" data-c="">همه</b>';
  (B.cats || []).forEach(function(c){
    h += '<b class="' + (S.cat===c.id?'on':'') + '" data-c="' + esc(c.id) + '">' +
         esc(c.emoji ? c.emoji + ' ' : '') + esc(c.name) + '</b>';
  });
  $('tabs').innerHTML = h;
}
$('tabs').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  S.cat = el.getAttribute('data-c');
  tap(); drawTabs(); applyFilter();
});

/* ── کارت محصول ── */
function tileHtml(i, n){
  return '<div class="tile' + (i.badge ? ' hot hasbadge' : '') + (i.stale ? ' off' : '') +
           '" data-i="' + esc(i.id) + '" data-app="' + esc(i.app) + '" style="animation-delay:' + Math.min(n*35, 300) + 'ms">' +
           (i.badge ? '<span class="tag">' + esc(i.badge) + '</span>' : '') +
           (i.live  ? '<span class="livedot">زنده</span>' : '') +
           '<div class="orb">' + esc(i.emoji || '💠') + '</div>' +
           '<h3>' + esc(i.name) + '</h3>' +
           (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
           '<div class="foot"><div class="cost"><b>' +
             (i.stale ? '—' : fa(i.price)) + '</b><i>' + esc(B.currency) +
             (i.unit && ['qty','qty_wallet','qty_username','qty_link'].indexOf(i.ask) >= 0 ? ' / ' + esc(i.unit) : '') + '</i></div>' +
             '<div class="plus">+</div></div>' +
         '</div>';
}
var tileObserver = (typeof IntersectionObserver !== 'undefined') ? new IntersectionObserver(function(entries){
  for (var i = 0; i < entries.length; i++) entries[i].target.classList.toggle('instage', entries[i].isIntersecting);
}, { rootMargin: '120px 0px', threshold: 0.01 }) : null;
function observeTiles(box, oldEls){
  if (!tileObserver) { for (var i=0;i<box.children.length;i++) box.children[i].classList.add('instage'); return; }
  if (oldEls) for (var k=0;k<oldEls.length;k++) tileObserver.unobserve(oldEls[k]);
  var els = box.querySelectorAll('.tile');
  for (var j = 0; j < els.length; j++) tileObserver.observe(els[j]);
}
function ensureGridBuilt(){
  if (S.gridBuilt) return;
  S.gridBuilt = true;
  buildGrid();
}
function buildGrid(){
  var box = $('grid');
  var oldEls = [].slice.call(box.children);
  if (!(B.items || []).length){
    box.style.display = 'block';
    box.innerHTML = '<div class="void"><div>🌙</div>چیزی برای نمایش نیست.</div>';
    observeTiles(box, oldEls);
    return;
  }
  var h = '';
  for (var n = 0; n < B.items.length; n++) h += tileHtml(B.items[n], n);
  box.classList.add('first');
  box.innerHTML = h;
  S.nodes = [].slice.call(box.children);
  observeTiles(box, oldEls);
  applyFilter();
  setTimeout(function(){ box.classList.remove('first'); }, 640);
}
function openFrom(ev){
  var el = ev.target.closest ? ev.target.closest('[data-i]') : null;
  if (el) open(el.getAttribute('data-i'), el.getAttribute('data-app'));
}
$('grid').addEventListener('click', openFrom);

function applyFilter(){
  var q = S.q.trim().toLowerCase(), shown = 0;
  var appFilter = $('tabs').getAttribute('data-app-filter') || '';
  for (var n = 0; n < S.nodes.length; n++){
    var el = S.nodes[n], it = B.items[n];
    var inCat = q ? true : (!S.cat || it.cat === S.cat);
    var inApp = !appFilter || it.app === appFilter;
    var ok = inCat && inApp && (!q || (it.name + ' ' + it.desc + ' ' + it.badge).toLowerCase().indexOf(q) >= 0);
    el.classList.toggle('hide', !ok);
    if (ok) shown++;
  }
  var none = $('voidBox');
  if (!shown && !none){
    none = document.createElement('div');
    none.id = 'voidBox'; none.className = 'void';
    none.style.gridColumn = '1 / -1';
    none.innerHTML = '<div>🌙</div>چیزی پیدا نشد.';
    $('grid').appendChild(none);
  } else if (shown && none){
    none.remove();
  }
}
var qT;
$('q').addEventListener('input', function(){
  var v = this.value;
  $('tabs').setAttribute('data-app-filter', '');
  clearTimeout(qT);
  qT = setTimeout(function(){ S.q = v; applyFilter(); }, 120);
});

/* ── پرفروش‌ها (خانه) ── */
(function drawBest(){
  var list = B.best || [];
  if (!list.length) return;
  var h = '';
  list.forEach(function(i, idx){
    h += '<div class="bestrow" data-i="' + esc(i.id) + '" data-app="' + esc(i.app) + '">' +
           '<b class="rk">' + (idx+1) + '</b>' +
           '<span class="e">' + esc(i.emoji || '💠') + '</span>' +
           '<span class="m"><b>' + esc(i.name) + '</b><span>' + fa(i.sold) + ' فروش این ماه</span></span>' +
           '<span class="p">' + fa(i.price) + ' ' + esc(B.currency) + '</span>' +
         '</div>';
  });
  $('bestList').innerHTML = h;
  $('bestSect').style.display = '';
})();
$('bestList').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('[data-i]') : null;
  if (!el) return;
  tap(); ensureGridBuilt(); go('shop', true);
  setTimeout(function(){ open(el.getAttribute('data-i'), el.getAttribute('data-app')); }, 60);
});

/* ── سفارش‌ها (حساب من) ── */
function drawOrders(){
  var box = $('ordList');
  if (!S.me){ box.innerHTML = '<div class="void"><div>🧾</div>در حال خواندن…</div>'; return; }
  var os = S.me.orders || [];
  if (!os.length){ box.innerHTML = '<div class="void"><div>🧾</div>هنوز سفارشی ثبت نشده.</div>'; return; }
  var h = '';
  os.forEach(function(o){
    h += '<div class="ord">' +
           '<span class="e">' + esc(o.emoji || '💠') + '</span>' +
           '<span class="m"><b>' + esc(o.name) + '</b><span>' + esc(o.date || '') + '</span></span>' +
           '<span class="s"><u>' + fa(o.total) + '</u><i>' + esc(o.status) + '</i></span>' +
         '</div>';
  });
  box.innerHTML = h;
}

function prettyCard(v){
  var d = String(v || '').replace(/\D/g, '');
  if (d.length !== 16) return String(v || '');
  return d.replace(/(\d{4})(?=\d)/g, '$1 ');
}
(function topup(){
  var t = B.topup || {};
  if (!t.on && !t.gw){ $('topPane').style.display = 'none'; return; }
  if (t.card){
    $('cardBox').innerHTML =
      '<div class="card-no"><b id="cardNo">' + esc(prettyCard(t.card)) + '</b>' +
        '<button id="cardCp">کپی</button></div>' +
      (t.name ? '<div class="card-holder">به نام: <b>' + esc(t.name) + '</b></div>' : '');
    $('cardCp').onclick = function(){
      var v = String(t.card);
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v);
      } catch(e){}
      toast('کپی شد', true); tap('medium');
    };
  }
  var min = Number(t.min) || 10000;
  var picks = [min, min*5, min*10, min*20];
  $('amtQuick').innerHTML = picks.map(function(p){ return '<i data-a="' + p + '">' + fa(p) + '</i>'; }).join('');
  $('amtQuick').addEventListener('click', function(ev){
    var el = ev.target.closest ? ev.target.closest('i[data-a]') : null;
    if (!el) return;
    $('amt').value = fa(Number(el.getAttribute('data-a')));
    tap();
  });
  $('topNote').innerHTML = 'مبلغ را به کارت بالا واریز کنید، بعد دکمه را بزنید — فاکتور داخل ربات برایتان می‌آید.<br>حداقل: <b>' + fa(min) + '</b> ' + esc(B.currency);
  var busy = false;
  $('topGo').onclick = function(){
    if (busy) return;
    var amt = intIn($('amt').value);
    if (!amt || amt < min){ toast('حداقل مبلغ ' + fa(min) + ' ' + B.currency + ' است.'); return; }
    busy = true; var btn = this, old = btn.textContent;
    btn.disabled = true; btn.textContent = 'در حال ثبت…'; tap('medium');
    api((B.apps && B.apps.tg) ? 'tg' : Object.keys(B.apps||{})[0], 'topup', { amount: amt }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      $('amt').value = '';
      $('wTtl').textContent  = 'درخواست شارژ ثبت شد';
      $('wSub').textContent  = j.message || '';
      $('wCode').textContent = j.order || '';
      $('win').classList.add('on');
      buzz('success');
    }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      toast((j && j.message) ? j.message : 'ثبت درخواست شارژ انجام نشد.');
    });
  };
})();

/* ── شیت خرید — هر آیتم app خودش را همراه می‌آورد ── */
function selfId(){
  if (TG && TG.initDataUnsafe && TG.initDataUnsafe.user){
    var u = TG.initDataUnsafe.user;
    if (u.username) return '@' + u.username;
    if (u.id) return String(u.id);
  }
  return '';
}
function selfChip(){
  var v = selfId();
  if (!v) return '';
  return '<div class="selfrow"><button type="button" class="self">👤 برای خودم</button><em>' + esc(v) + '</em></div>';
}
function open(id, appKey){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id && B.items[i].app === appKey) it = B.items[i];
  if (!it) return;
  tap('medium');
  S.item = it;
  S.qty = (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username' || it.ask === 'qty_link')
             ? (Number(it.min) > 0 ? Number(it.min) : 1) : 1;

  $('sOrb').textContent  = it.emoji || '💠';
  $('sName').textContent = it.name;
  $('sDesc').textContent = it.desc || '';
  $('sGo').disabled  = false; $('sGo').textContent  = 'شارژ حساب';
  $('sWal').disabled = false; $('sWal').textContent = 'پرداخت از کیف پول';

  var f = $('sField'), html = '';
  var hasQty = it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username' || it.ask === 'qty_link';
  if (hasQty){
    html += '<div class="lbl">بسته‌های پیشنهادی</div><div class="plans" id="fPlans">' +
              planRows(it).map(function(q){
                return '<i data-q="' + q + '"' + (q === S.qty ? ' class="on"' : '') + '>' +
                       '<s class="pg">' + esc(it.emoji || '💠') + '</s>' +
                       '<b>' + fa(q) + (it.unit ? ' ' + esc(it.unit) : '') + '</b>' +
                       '<u>' + fa(Math.round(it.price * q)) + ' ' + esc(B.currency) + '</u>' +
                       '<em class="chk">✓</em></i>';
              }).join('') +
            '</div>' +
            '<div class="lbl">مقدار دلخواه (حداقل ' + fa(it.min || 1) + ')</div>' +
            '<div class="field"><input id="fQty" type="text" inputmode="numeric" value="' + S.qty + '">' +
              '<div class="hint">حداقل ' + fa(it.min || 1) + (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') + '</div></div>';
  }
  if (it.ask === 'qty_username' || it.ask === 'username'){
    html += '<div class="field"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="hint">آیدی عمومی حساب — بدون آن سفارش قابل انجام نیست.</div>' + selfChip() + '</div>';
  }
  if (it.ask === 'qty_wallet' || it.ask === 'wallet'){
    html += '<div class="field"><label>💼 آدرس ولت مقصد</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="hint">آدرس را کامل و بدون فاصله وارد کنید.</div></div>';
  }
  if (it.ask === 'qty_link'){
    html += '<div class="field"><label>🔗 لینک پست یا استوری</label>' +
            '<input id="fTxt" type="text" placeholder="https://t.me/channel/123" dir="ltr" style="text-align:left" autocomplete="off" spellcheck="false" maxlength="300">' +
            '<div class="hint">لینک را کامل بفرستید.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="field"><label>✍️ توضیح سفارش</label><textarea id="fTxt" maxlength="300" placeholder="هرچه لازم است بنویسید…"></textarea></div>';
  }
  f.innerHTML = html;

  var selfBtn = f.querySelector('.self');
  if (selfBtn) selfBtn.onclick = function(){
    var v = selfId(); if (!v) return;
    var box = $('fTxt'); if (box){ box.value = v; box.dispatchEvent(new Event('input')); }
    this.classList.add('done'); this.textContent = '✓ برای خودم';
    var em = this.parentNode.querySelector('em'); if (em) em.remove();
    tap();
  };
  if (hasQty){
    f.addEventListener('click', function(ev){
      var el = ev.target.closest ? ev.target.closest('i[data-q]') : null;
      if (!el) return;
      setQty(Number(el.getAttribute('data-q'))); tap();
    });
    $('fQty').addEventListener('input', function(){
      var raw = digits(this.value) || this.value.replace(/[^\d.]/g,'');
      setQty(parseFloat(raw) || 0, true);
    });
  }
  setQty(S.qty);
  if (it.stale){ $('sGo').disabled = true; $('sWal').disabled = true; }
  $('scrim').classList.add('on');
  $('sheet').classList.add('on');
  backBtn();
}
function setQty(v, typing){
  var it = S.item; if (!it) return;
  v = Number(v) || 0;
  v = it.ask === 'qty_wallet' ? Math.round(v * 10000) / 10000 : Math.floor(v);
  if (!isFinite(v) || v < 0) v = 0;
  if (!typing){
    if (v < (it.min || 1)) v = it.min || 1;
    if (it.max > 0 && v > it.max) v = it.max;
  }
  S.qty = v;
  var qi = $('fQty'); if (qi && !typing) qi.value = v;
  markPlan(); total();
}
function planRows(it){
  var min = Number(it.min) > 0 ? Number(it.min) : 1;
  var out = [], mults = [1, 2, 5, 10];
  for (var i = 0; i < mults.length; i++){
    var q = min * mults[i];
    if (it.max > 0 && q > it.max) break;
    out.push(q);
  }
  return out;
}
function markPlan(){
  var box = $('fPlans'); if (!box) return;
  var all = box.querySelectorAll('i[data-q]');
  for (var i = 0; i < all.length; i++)
    all[i].classList.toggle('on', Number(all[i].getAttribute('data-q')) === S.qty);
}
function sum(){
  var it = S.item; if (!it) return 0;
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username' || it.ask === 'qty_link')
    return Math.round(it.price * Math.max(0, S.qty));
  return it.price;
}
function total(){ $('sTotal').textContent = fa(sum()) + ' ' + B.currency; walletState(); }
function walletState(){
  var it = S.item; if (!it) return;
  var t = sum(), enough = S.bal >= t;
  $('sWal').disabled = !enough || !!it.stale;
  $('sGo').style.display = enough ? 'none' : '';
  $('sWalNote').innerHTML = enough
    ? '👛 موجودی شما: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) + ' · بعد از پرداخت: <b>' + fa(S.bal - t) + '</b>'
    : '⚠️ موجودی کافی نیست — موجودی: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) + ' · کسری: <b>' + fa(t - S.bal) + '</b>';
}
function shut(){
  $('scrim').classList.remove('on'); $('sheet').classList.remove('on');
  S.item = null; backBtn();
}
$('scrim').onclick = shut;
$('sNo').onclick = function(){ tap(); shut(); };
document.addEventListener('focusin', function(ev){ if (ev.target.closest && ev.target.closest('#sheet')) document.body.classList.add('kb-open'); });
document.addEventListener('focusout', function(ev){ if (ev.target.closest && ev.target.closest('#sheet')) document.body.classList.remove('kb-open'); });

function validate(){
  var it = S.item, fv = '';
  var fx = $('fTxt'); if (fx) fv = fx.value.trim();
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username' || it.ask === 'qty_link'){
    if (!S.qty || S.qty < (it.min || 1)) { toast('حداقل مقدار ' + fa(it.min || 1) + ' است.'); return null; }
    if (it.max > 0 && S.qty > it.max)    { toast('حداکثر مقدار ' + fa(it.max) + ' است.'); return null; }
  }
  if (['username','wallet','qty_wallet','qty_username','qty_link','text'].indexOf(it.ask) >= 0 && !fv){
    toast('لطفا فیلد بالا را پر کنید.'); return null;
  }
  return fv;
}
function send(payMode, btn){
  if (S.busy || !S.item) return;
  var fv = validate(); if (fv === null) return;
  var it = S.item;
  S.busy = true; btn.disabled = true;
  var old = btn.textContent; btn.textContent = 'در حال ثبت…'; tap('medium');
  api(it.app, 'order', { item: it.id, qty: S.qty, field: fv, seen_price: it.price, pay: payMode },
    function(j){
      S.busy = false; btn.disabled = false; btn.textContent = old;
      if (typeof j.balance === 'number') setBal(j.balance);
      shut();
      $('wCode').textContent = j.order || '';
      $('wSub').textContent  = j.message || '';
      $('wTtl').textContent  = j.paid ? 'پرداخت انجام شد ✓' : 'سفارش ثبت شد';
      $('win').classList.add('on'); buzz('success');
      api('unified', 'me_all', {}, function(m){ S.me = m; setBal(m.balance); }, function(){});
    },
    function(j){
      S.busy = false; btn.disabled = false; btn.textContent = old;
      if (j && j.error === 'price_changed' && j.price){ it.price = j.price; total(); }
      if (j && j.error === 'no_balance'){
        if (typeof j.balance === 'number') { S.bal = j.balance; setBal(j.balance); }
        walletState(); toast((j && j.message) ? j.message : 'موجودی کافی نیست.');
        shut(); go('me'); return;
      }
      toast((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
    });
}
$('sWal').onclick = function(){ send('wallet', this); };
$('sGo').onclick  = function(){ shut(); tap(); go('me'); };
$('wGo').onclick   = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };
$('wBack').onclick = function(){ $('win').classList.remove('on'); tap(); go('shop'); };
$('wNote').onclick = function(){ $('win').classList.remove('on'); tap(); go('note'); };

/* ── 🔔 اعلان‌ها — از هر سه اپ ── */
function agoTxt(t){
  var d = Math.max(0, Math.floor(Date.now()/1000) - (t|0));
  if (d < 60) return 'همین الان';
  if (d < 3600) return Math.floor(d/60) + ' دقیقه پیش';
  if (d < 86400) return Math.floor(d/3600) + ' ساعت پیش';
  return Math.floor(d/86400) + ' روز پیش';
}
function loadNotes(){
  var apps = Object.keys(B.apps || {});
  if (!apps.length){ $('noteList').innerHTML = '<div class="void"><div>🔔</div>هنوز خبری نیست.</div>'; return; }
  var all = [], pending = apps.length, freshTotal = 0;
  apps.forEach(function(ak){
    api(ak, 'notes', {}, function(j){
      (j.list || []).forEach(function(n){ n._fresh = j.n || 0; all.push(n); });
      freshTotal += j.n || 0;
      done();
    }, function(){ done(); });
  });
  function done(){
    pending--;
    if (pending > 0) return;
    var box = $('noteList');
    if (!all.length){ box.innerHTML = '<div class="void"><div>🔔</div>هنوز خبری نیست.</div>'; return; }
    all.sort(function(a,b){ return (b.t||0) - (a.t||0); });
    var h = '';
    all.forEach(function(n){
      h += '<div class="note"><div class="nh"><i>' + esc(n.e || '🔔') + '</i><b>' + esc(n.h || '') + '</b>' +
           '<time>' + esc(agoTxt(n.t)) + '</time></div><p>' + esc(n.b || '') + '</p></div>';
    });
    box.innerHTML = h;
  }
}

/* ══════════════════ 💎 ایردراپ ══════════════════ */
var AD = { loaded:false, page:'mine', state:null };
// «معدن» وسطِ ردیف — تاکیدِ صریحِ کارفرما — نه لبه‌ی چپ/راست
var ADOCK_PAGES = [
  { id:'leader', ico:'crown',  name:'لیدربورد' },
  { id:'ref',    ico:'people', name:'رفرال' },
  { id:'mine',   ico:'gem',    name:'معدن' },
  { id:'missions', ico:'target', name:'ماموریت‌ها' },
  { id:'rewards',  ico:'gift',   name:'پاداش‌ها' }
];
(function drawAdock(){
  var h = '';
  ADOCK_PAGES.forEach(function(p){
    h += '<b data-p="' + p.id + '"' + (p.id==='mine' ? ' class="on"' : '') + '>' +
         '<i class="navi">' + ICONS[p.ico] + '</i><span>' + esc(p.name) + '</span></b>';
  });
  $('adock').innerHTML = h;
})();
$('airBack').onclick = function(){ tap(); go('home'); };
$('adock').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  tap(); airGo(el.getAttribute('data-p'));
});
function airGo(p){
  AD.page = p;
  ['mine','missions','rewards','ref','leader'].forEach(function(k){
    var el = $('air' + k.charAt(0).toUpperCase() + k.slice(1));
    if (el) el.classList.toggle('on', k === p);
  });
  var btns = $('adock').children;
  for (var i=0;i<btns.length;i++) btns[i].classList.toggle('on', btns[i].getAttribute('data-p') === p);
  if (p === 'missions') airDrawMissions();
  if (p === 'rewards')  airDrawRewards();
  if (p === 'ref')      airLoadRef();
  if (p === 'leader')   airLoadLeader();
}
function airLoad(){
  AD.loaded = true;
  airRefreshState();
  AD.timer = setInterval(airRefreshState, 15000);
}
function airRefreshState(){
  api('unified', 'airdrop_state', {}, function(j){
    AD.state = j;
    airDrawMine();
    if (AD.page === 'missions') airDrawMissions();
    if (AD.page === 'rewards')  airDrawRewards();
  }, function(){});
}
function fmtHMS(sec){
  sec = Math.max(0, Math.floor(sec));
  var d = Math.floor(sec/86400); sec -= d*86400;
  var h = Math.floor(sec/3600); sec -= h*3600;
  var m = Math.floor(sec/60);
  if (d > 0) return d + ' روز ' + h + ' ساعت';
  return (h<10?'0':'')+h + ':' + (m<10?'0':'')+m;
}
function airDrawMine(){
  var s = AD.state; if (!s) return;
  var pct = s.xp_need > 0 ? Math.min(1, s.xp / s.xp_need) : 0;
  var C = 2 * Math.PI * 60;
  var fg = $('ringFg');
  fg.style.strokeDasharray = C.toFixed(1);
  fg.style.strokeDashoffset = (C * (1 - pct)).toFixed(1);
  $('crystalNow').textContent = fa(Math.floor(s.xp));
  $('crystalOf').textContent  = 'از ' + fa(s.xp_need) + ' کریستال';
  $('rateBox').textContent = fa(s.rate) + ' کریستال در ساعت';
  var left = (s.season_end || 0) - Math.floor(Date.now()/1000);
  $('endBox').textContent = 'پایان فصل ' + s.season + ': ' + fmtHMS(left);
  $('stLevel').textContent = fa(s.level);
  $('stRate').textContent  = '×' + fa(s.level);
  $('stTotal').textContent = fa(Math.floor(s.crystals));
}
function airDrawMissions(){
  var s = AD.state;
  var box = $('missionList');
  if (!s){ box.innerHTML = '<div class="void"><div>🎯</div>در حال خواندن…</div>'; return; }
  var list = s.missions || [];
  if (!list.length){ box.innerHTML = '<div class="void"><div>🎯</div>ماموریتی نیست.</div>'; return; }
  var h = '';
  list.forEach(function(m){
    var pct = m.need > 0 ? Math.min(100, Math.round(m.progress / m.need * 100)) : 100;
    h += '<div class="mission' + (m.claimed ? ' done' : '') + '">' +
           '<span class="e">' + esc(m.emoji) + '</span>' +
           '<span class="m"><b>' + esc(m.name) + '</b>' +
             '<span class="mp"><i style="width:' + pct + '%"></i></span>' +
             '<span class="mt">' + fa(m.progress) + ' / ' + fa(m.need) + ' · جایزه ' + fa(m.reward) + ' 💎</span></span>' +
           (m.claimed ? '<button disabled>گرفته شد</button>'
             : (m.ready ? '<button data-claim="' + esc(m.id) + '">دریافت</button>' : '<button disabled>—</button>')) +
         '</div>';
  });
  box.innerHTML = h;
}
$('missionList').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('[data-claim]') : null;
  if (!el || el.disabled) return;
  tap('medium'); el.disabled = true;
  api('unified', 'airdrop_mission_claim', { id: el.getAttribute('data-claim') }, function(j){
    toast('+' + fa(j.reward) + ' کریستال گرفتی 🎉', true);
    AD.state = j.state; airDrawMine(); airDrawMissions();
  }, function(j){
    el.disabled = false;
    toast((j && j.message) ? j.message : 'دریافت انجام نشد.');
  });
});
function airDrawRewards(){
  var s = AD.state;
  if (!s) return;
  $('redeemHint').textContent = 'هر ' + fa(1) + ' کریستال = ' + fa(s.redeem_rate) + ' تومان · حداقل ' + fa(s.redeem_min) + ' کریستال · موجودی: ' + fa(Math.floor(s.crystals)) + ' 💎';
}
$('redeemGo').onclick = function(){
  var amt = intIn($('redeemAmt').value);
  if (!amt) { toast('مقدار کریستال را وارد کنید.'); return; }
  var btn = this; btn.disabled = true; tap('medium');
  api('unified', 'airdrop_redeem', { amount: amt }, function(j){
    btn.disabled = false;
    $('redeemAmt').value = '';
    setBal(j.balance);
    AD.state = j.state; airDrawMine(); airDrawRewards();
    toast('+' + fa(j.toman) + ' تومان به کیف‌پول اضافه شد ✓', true);
  }, function(j){
    btn.disabled = false;
    toast((j && j.message) ? j.message : 'تبدیل انجام نشد.');
  });
};
function airLoadRef(){
  api('unified', 'airdrop_referral', {}, function(j){
    $('refCount').textContent = fa(j.ref_count);
    $('refEarned').textContent = fa(j.ref_earned);
    $('refPending').textContent = fa(j.ref_pending);
    AD._link = j.link;
    var box = $('refTop');
    var top = j.top || [];
    if (!top.length){ box.innerHTML = '<div class="void"><div>🏆</div>هنوز کسی معرفی نکرده.</div>'; return; }
    var h = '';
    top.forEach(function(r){
      h += '<div class="lbrow"><b class="rk">' + fa(r.rank) + '</b>' +
           '<span class="m">' + esc(r.name || (r.username ? '@'+r.username : ('#'+r.uid))) + '</span>' +
           '<span class="p">' + fa(r.count) + ' دعوت</span></div>';
    });
    box.innerHTML = h;
  }, function(){
    $('refTop').innerHTML = '<div class="void"><div>🏆</div>خوانده نشد.</div>';
  });
}
$('refShare').onclick = function(){
  tap();
  var link = AD._link || '';
  if (!link) { toast('لینک هنوز آماده نیست.'); return; }
  try {
    if (TG && TG.openTelegramLink) TG.openTelegramLink('https://t.me/share/url?url=' + encodeURIComponent(link));
    else window.open('https://t.me/share/url?url=' + encodeURIComponent(link), '_blank');
  } catch(e){}
};
function airLoadLeader(){
  api('unified', 'airdrop_leaderboard', {}, function(j){
    var box = $('leaderList');
    var list = j.list || [];
    if (!list.length){ box.innerHTML = '<div class="void"><div>🏆</div>هنوز کسی کریستال جمع نکرده.</div>'; return; }
    var h = '';
    list.forEach(function(r){
      h += '<div class="lbrow' + (r.uid === j.me ? ' me' : '') + '"><b class="rk">' + fa(r.rank) + '</b>' +
           '<span class="m">' + esc(r.name || (r.username ? '@'+r.username : ('#'+r.uid))) + '</span>' +
           '<span class="p">' + fa(Math.floor(r.crystals)) + ' 💎</span></div>';
    });
    box.innerHTML = h;
  }, function(){
    $('leaderList').innerHTML = '<div class="void"><div>🏆</div>خوانده نشد.</div>';
  });
}

drawTabs();
go('home', true);
(window.requestIdleCallback || function(fn){ setTimeout(fn, 400); })(ensureGridBuilt);
})();
</script>
</body>
</html>
HTML;
}

/**
 * 🌇 پوسته‌ی «نوا» — هویتِ رنگیِ چهارمِ کاملا جدا: کهربایی/زمردی روی
 * شبِ سرمه‌ای، تا هم از شفق‌قطبیِ tg (بنفش/آبی) هم اقیانوسِ num
 * (سرمه‌ای/فیروزه‌ای) هم منشورِ react (بنفش/مجنتا) فاصله داشته باشد.
 * ساختارِ CSS و قاعده‌های سرعت عینِ سه پوسته‌ی قبلی است (فقط چند
 * کلاسِ تازه برایِ خانه/ایردراپ اضافه شده).
 */
function maSkinNova() {
    return <<<'CSS'
<style>
:root{
  --c1:#F2B705; --c2:#22C55E; --c3:#6C5CE7; --c4:#F23557;
  --bg:#0A0E1A; --ink:#F4F6FF; --dim:#9AA3C7; --line:rgba(255,255,255,.12);
  --pane:rgba(255,255,255,.055); --pane2:rgba(255,255,255,.035);
  --blur:18px; --r:24px; --safe:env(safe-area-inset-bottom,0px);
  color-scheme:dark;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{background:var(--bg);color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden;-webkit-font-smoothing:antialiased}
img{max-width:100%}

.sky{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(62vw 62vw at 88% -10%,color-mix(in srgb,var(--c1) 30%,transparent),transparent 68%),
    radial-gradient(56vw 56vw at 2% 22%,color-mix(in srgb,var(--c3) 32%,transparent),transparent 66%),
    radial-gradient(50vw 50vw at 84% 104%,color-mix(in srgb,var(--c2) 24%,transparent),transparent 64%),
    var(--bg)}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:radial-gradient(122% 78% at 50% 0%,transparent 42%,var(--bg) 96%)}

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 15px calc(112px + var(--safe))}

/* ═══ سربرگ ═══ */
.top{display:flex;align-items:center;gap:11px;margin:14px 0 4px;padding:11px 12px;border-radius:22px;
  border:1px solid var(--line);background:var(--pane);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  box-shadow:0 10px 28px -20px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06)}
.ava{width:46px;height:46px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  font-weight:900;font-size:18px;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  box-shadow:0 0 0 2px var(--bg),0 0 0 4px color-mix(in srgb,var(--c1) 45%,transparent)}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:14.5px;font-weight:800;letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chipbal{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:5px 6px 5px 10px;border-radius:12px;
  border:1px solid var(--line);background:rgba(255,255,255,.06);font-size:12.5px;font-weight:900;cursor:pointer}
.chipbal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.chipbal b{width:20px;height:20px;border-radius:7px;display:grid;place-items:center;font-size:14px;line-height:1;
  color:#fff;background:linear-gradient(135deg,var(--c2),var(--c1))}
.cta{flex:0 0 auto;padding:11px 14px;border:0;border-radius:15px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#111;
  background:linear-gradient(135deg,var(--c1),#FFD866)}
.cta:active{transform:scale(.96)}
.bell{position:relative;flex:0 0 auto;width:38px;height:38px;border-radius:13px;cursor:pointer;
  border:1px solid var(--line);background:rgba(255,255,255,.06);color:inherit;display:grid;place-items:center;font-size:16px;font-family:inherit}
.bell:active{transform:scale(.94)}
.bell .bdot{position:absolute;top:6px;inset-inline-end:6px;width:8px;height:8px;border-radius:99px;
  background:var(--c4);opacity:0;transform:scale(.4);transition:opacity .2s,transform .2s}
.bell.has .bdot{opacity:1;transform:scale(1)}
.note{border:1px solid var(--line);background:var(--pane2);border-radius:16px;padding:13px 14px;margin-bottom:10px}
.note .nh{display:flex;align-items:center;gap:8px;margin-bottom:5px}
.note .nh i{font-style:normal;font-size:16px;line-height:1}
.note .nh b{font-size:13px;font-weight:800;flex:1;min-width:0}
.note .nh time{font-size:10px;color:var(--dim);white-space:nowrap}
.note p{font-size:12px;color:var(--dim);line-height:1.8;white-space:pre-line;margin:0}

.pg{display:none;animation:pgIn .3s cubic-bezier(.2,.9,.3,1)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .pg{animation:none} }

.sect{display:flex;align-items:baseline;justify-content:space-between;margin:20px 2px 11px}
.sect h2{margin:0;font-size:11.5px;font-weight:800;letter-spacing:1.2px;color:var(--dim);display:flex;align-items:center;gap:8px}
.sect h2 s{text-decoration:none;width:5px;height:16px;border-radius:3px;background:linear-gradient(180deg,var(--c2),var(--c1))}
.sect a{font-size:11.5px;color:var(--c1);font-weight:700;cursor:pointer}

/* ═══ بنرِ احرازِ هویت ═══ */
.verify{display:flex;align-items:center;gap:11px;margin:14px 2px 0;padding:13px 14px;border-radius:18px;
  border:1px solid color-mix(in srgb,var(--c4) 35%,transparent);background:color-mix(in srgb,var(--c4) 10%,transparent)}
.verify i{font-style:normal;font-size:19px}
.verify b{display:block;font-size:12.5px;font-weight:800}
.verify span{display:block;font-size:11px;color:var(--dim);margin-top:2px;line-height:1.6}

/* ═══ کارت‌های تبلیغاتی ═══ */
.promoRow{display:flex;gap:10px;overflow-x:auto;margin-top:14px;padding-bottom:2px;scrollbar-width:none}
.promoRow::-webkit-scrollbar{display:none}
.promo{flex:0 0 84%;padding:18px;border-radius:22px;color:#fff;position:relative;overflow:hidden}
.promo b{position:relative;display:block;font-size:14px;font-weight:900}
.promo span{position:relative;display:block;font-size:11.5px;margin-top:6px;opacity:.9}
.promo.p1{background:linear-gradient(135deg,var(--c1),#B8860B)}
.promo.p2{background:linear-gradient(135deg,var(--c3),#3B2E8A)}

/* ═══ دسترسیِ سریع ═══ */
.qgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.qtile{padding:14px 6px;border-radius:18px;text-align:center;cursor:pointer;
  border:1px solid var(--line);background:var(--pane)}
.qtile:active{transform:scale(.95)}
.qtile span{display:block;font-size:10px;font-weight:800;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.qtile.air{background:linear-gradient(140deg,color-mix(in srgb,var(--c3) 30%,var(--pane)),var(--pane))}

/* ═══ میان‌بر دسته‌ها ═══ */
.rail{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail .rc{flex:0 0 auto;width:82px;padding:13px 6px;border-radius:20px;text-align:center;cursor:pointer;
  border:1px solid var(--line);background:var(--pane)}
.rail .rc:active{transform:scale(.95)}
.rail .rc span{display:block;font-size:10.5px;font-weight:800;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rail .rc.on{border-color:color-mix(in srgb,var(--c1) 55%,transparent)}
.rail .rc.on span{color:var(--ink)}

/* ═══ آیکونِ شیشه‌ایِ جعبه‌ای — دسته‌ها + دسترسیِ سریع ═══ */
.ico{position:relative;width:38px;height:38px;margin:0 auto 7px;border-radius:14px;display:grid;place-items:center;
  color:#fff;background-image:linear-gradient(158deg,var(--c1),var(--c3));
  border:1px solid rgba(255,255,255,.24);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 6px 16px -8px color-mix(in srgb,var(--c1) 55%,transparent);
  overflow:hidden}
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(115deg,transparent 41%,rgba(255,255,255,.6) 50%,transparent 59%);
  transform:translateX(-130%);animation:icoSheen 3.2s cubic-bezier(.4,0,.2,1) infinite}
.ico:after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:radial-gradient(120% 70% at 50% -10%,rgba(255,255,255,.35),transparent 60%)}
.ico svg{position:relative;width:20px;height:20px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
@keyframes icoSheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}
.i-spin{transform-box:fill-box;transform-origin:50% 50%;animation:icoSpin 6s linear infinite}
.i-float{animation:icoFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.i-pulse{animation:icoPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.i-lid{animation:icoLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes icoSpin{to{transform:rotate(360deg)}}
@keyframes icoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes icoPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.75}}
@keyframes icoLid{0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@media (prefers-reduced-motion:reduce){
  .ico:before,.i-spin,.i-float,.i-pulse,.i-lid{animation:none!important}
}

/* ═══ آیکونِ ناوبری — بدونِ جعبه، فقط رنگش با فعال‌شدن عوض می‌شود ═══ */
.navi{display:block;margin:0 auto;width:19px;height:19px}
.navi svg{width:100%;height:100%;display:block;fill:none;stroke:currentColor;
  stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.navi svg .fl{fill:currentColor;stroke:none}

/* ═══ پرفروش‌ها ═══ */
.bestrow{display:flex;align-items:center;gap:11px;padding:12px 13px;border-radius:16px;margin-bottom:8px;
  border:1px solid var(--line);background:var(--pane)}
.bestrow .rk{width:22px;height:22px;flex:0 0 auto;border-radius:8px;display:grid;place-items:center;font-size:11px;font-weight:900;
  color:#111;background:linear-gradient(135deg,var(--c1),#FFD866)}
.bestrow .e{font-size:19px}
.bestrow .m{flex:1;min-width:0}
.bestrow .m b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bestrow .m span{display:block;font-size:10px;color:var(--dim);margin-top:2px}
.bestrow .p{flex:0 0 auto;font-size:11.5px;font-weight:800;color:var(--c1)}

/* ═══ ردیف‌های اعتمادسازی — بج‌های دایره‌ایِ رنگیِ بزرگ، طبقِ مرجع ═══ */
.trustwrap{display:flex;flex-direction:column;gap:16px;margin:22px 2px 4px}
.trust{display:flex;align-items:center;gap:14px}
.trustico{width:60px;height:60px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;
  background:color-mix(in srgb,var(--tc,var(--c1)) 20%,transparent)}
.trustico svg{width:26px;height:26px;fill:none;stroke:var(--tc,var(--c1));
  stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.trustico svg .fl{fill:var(--tc,var(--c1));stroke:none}
.trust b{display:block;font-size:13.5px;font-weight:900;color:var(--ink)}
.trust span{display:block;font-size:11.5px;color:var(--dim);line-height:1.8;margin-top:4px}

/* ═══ جستجو و چیپ دسته ═══ */
.find{position:relative;margin:4px 0 12px}
.find input{width:100%;padding:13px 42px 13px 14px;border-radius:16px;border:1px solid var(--line);
  background:var(--pane);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none;transition:.2s}
.find input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.find span{position:absolute;top:50%;right:14px;transform:translateY(-50%);opacity:.5;font-size:15px}
.tabs{display:flex;gap:7px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs b{flex:0 0 auto;padding:9px 15px;border-radius:13px;cursor:pointer;font-size:12px;font-weight:800;
  color:var(--dim);border:1px solid var(--line);background:var(--pane);white-space:nowrap}
.tabs b.on{color:#fff;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c3))}

/* ═══ شبکه‌ی محصول ═══ */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.tile{position:relative;overflow:hidden;padding:14px 12px 12px;border-radius:22px;cursor:pointer;contain:content;
  border:1px solid var(--line);background:var(--pane);box-shadow:0 10px 26px -22px rgba(20,25,45,.5);
  display:flex;flex-direction:column;min-height:172px;animation:rise .4s cubic-bezier(.2,.9,.3,1)}
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.grid:not(.first) .tile{animation:none}
@media (prefers-reduced-motion:reduce){ .tile{animation:none} }
.tile:active{transform:scale(.975)}
.tile.hot{border-color:color-mix(in srgb,var(--c1) 38%,transparent);box-shadow:0 14px 30px -20px color-mix(in srgb,var(--c1) 45%,transparent)}
.tile.hide{display:none}
.orb{position:relative;width:52px;height:52px;border-radius:18px;display:grid;place-items:center;font-size:25px;
  margin-bottom:11px;color:#fff;background-image:linear-gradient(140deg,var(--c1),var(--c3));border:1px solid rgba(255,255,255,.22)}
.tile h3{position:relative;margin:0;font-size:13px;font-weight:800;line-height:1.55;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile p{position:relative;margin:4px 0 0;font-size:10.5px;color:var(--dim);line-height:1.65;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile .foot{position:relative;margin-top:auto;padding-top:11px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .cost b{display:block;font-size:15px;font-weight:900;letter-spacing:-.4px;
  background:linear-gradient(90deg,var(--c1),var(--c3));-webkit-background-clip:text;background-clip:text;color:transparent}
.tile .cost i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:1px}
.tile .plus{width:29px;height:29px;flex:0 0 auto;border-radius:11px;display:grid;place-items:center;
  font-size:17px;font-weight:700;color:#fff;line-height:1;background:linear-gradient(135deg,var(--c1),var(--c3))}
.tag{position:absolute;top:10px;left:10px;z-index:2;font-size:8.5px;font-weight:900;padding:3px 7px;border-radius:8px;
  color:#fff;background:linear-gradient(135deg,var(--c4),var(--c3))}
.livedot{position:absolute;top:10px;left:10px;z-index:2;font-size:8px;font-weight:900;padding:3px 7px;border-radius:8px;
  color:#fff;background:var(--c2);letter-spacing:.3px}
.tile.hasbadge .livedot{top:31px}
.tile.off{opacity:.55}

/* ═══ فهرست سفارش ═══ */
.ord{display:flex;align-items:center;gap:11px;padding:13px 14px;border-radius:18px;margin-bottom:9px;
  border:1px solid var(--line);background:var(--pane)}
.ord .e{width:42px;height:42px;flex:0 0 auto;border-radius:14px;display:grid;place-items:center;font-size:21px;
  background:var(--pane2);border:1px solid var(--line)}
.ord .m{flex:1;min-width:0}
.ord .m b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ord .m span{display:block;font-size:10px;color:var(--dim);margin-top:3px;direction:ltr;text-align:right}
.ord .s{flex:0 0 auto;text-align:left}
.ord .s u{display:block;text-decoration:none;font-size:12px;font-weight:900;color:var(--c1)}
.ord .s i{display:block;font-style:normal;font-size:9.5px;color:var(--dim);margin-top:3px}

/* ═══ حساب کاربری ═══ */
.prof{display:flex;align-items:center;gap:14px;padding:19px 17px;border-radius:26px;position:relative;overflow:hidden;
  border:1px solid var(--line);background:var(--pane2);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
.prof .big{position:relative;width:64px;height:64px;border-radius:22px;flex:0 0 auto;overflow:hidden;
  display:grid;place-items:center;font-size:26px;font-weight:900;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.prof .d{position:relative;flex:1;min-width:0}
.prof .d b{display:block;font-size:16px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prof .d span{display:block;font-size:11.5px;color:var(--dim);margin-top:4px;direction:ltr;text-align:right}
.prof .d code{display:inline-block;margin-top:7px;font-size:10.5px;padding:3px 9px;border-radius:8px;
  background:var(--pane);border:1px solid var(--line);direction:ltr;font-family:ui-monospace,monospace}

.pane{margin-top:13px;padding:16px;border-radius:22px;border:1px solid var(--line);background:var(--pane);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
.pane h3{margin:0 0 12px;font-size:13px;font-weight:900;display:flex;align-items:center;gap:7px}
.card-no{padding:14px;border-radius:16px;border:1px dashed color-mix(in srgb,var(--c1) 40%,transparent);background:var(--pane2)}
.card-no b{display:block;font-size:19px;font-weight:900;letter-spacing:1.5px;direction:ltr;text-align:center;
  font-family:ui-monospace,monospace;white-space:nowrap;overflow-x:auto;scrollbar-width:none;color:var(--c1)}
.card-no b::-webkit-scrollbar{display:none}
.card-no button{width:100%;margin-top:11px;padding:11px;border:0;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.card-holder{margin-top:9px;font-size:11.5px;color:var(--dim)}
.card-holder b{color:var(--ink)}
.amt{display:flex;gap:9px;margin-top:13px}
.amt input{flex:1;min-width:0;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:var(--pane2);color:var(--ink);font-family:inherit;font-size:15px;font-weight:800;outline:none;text-align:center}
.quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.quick i{padding:7px 12px;border-radius:11px;font-style:normal;font-size:11.5px;font-weight:800;cursor:pointer;
  border:1px solid var(--line);background:var(--pane2);color:var(--dim)}
.link{display:flex;align-items:center;gap:11px;padding:14px;border-radius:16px;margin-top:9px;cursor:pointer;
  border:1px solid var(--line);background:var(--pane2);font-size:12.5px;font-weight:700}
.link:active{transform:scale(.985)}
.link em{flex:1;font-style:normal}
.link s{text-decoration:none;color:var(--dim);font-size:16px}

.void{text-align:center;padding:46px 20px;color:var(--dim);font-size:12.5px;line-height:1.9}
.void div{font-size:44px;margin-bottom:10px;opacity:.55}
.skel{height:172px;border-radius:22px;border:1px solid var(--line);
  background:linear-gradient(90deg,var(--pane),var(--pane2),var(--pane));background-size:200% 100%;animation:sh 1.3s linear infinite}
@keyframes sh{to{background-position:-200% 0}}

/* ═══ جزیره‌ی پایین ═══ */
.dock{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(11px + var(--safe));z-index:30;
  width:min(94vw,420px);display:flex;gap:3px;padding:7px;border-radius:26px;
  border:1px solid var(--line);background:rgba(255,255,255,.09);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  box-shadow:0 18px 44px -12px rgba(0,0,0,.65),inset 0 1px 0 rgba(255,255,255,.08)}
.dock b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;border-radius:19px;cursor:pointer;color:var(--dim);font-size:9.5px;font-weight:800}
.dock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dock b .navi{width:19px;height:19px}
.dock b.on{color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.dock b.airbtn.on{background:linear-gradient(135deg,var(--c3),#8B5CF6)}
.dock.hidden{display:none}

/* ═══ ایردراپ ═══ */
.airhead{display:flex;align-items:center;justify-content:space-between;margin:14px 0 6px}
.airback{border:1px solid var(--line);background:var(--pane);color:var(--ink);border-radius:13px;
  padding:9px 13px;font-family:inherit;font-size:11.5px;font-weight:800;cursor:pointer}
.airhead b{font-size:14px;font-weight:900}
.airpg{display:none;padding-bottom:70px}
.airpg.on{display:block;animation:pgIn .3s cubic-bezier(.2,.9,.3,1)}
.ringwrap{position:relative;width:220px;height:220px;margin:20px auto 0;display:grid;place-items:center}
.ring{width:100%;height:100%;transform:rotate(-90deg)}
.ringbg{fill:none;stroke:var(--pane2);stroke-width:9}
.ringfg{fill:none;stroke:url(#ringGrad);stroke-width:9;stroke-linecap:round;
  transition:stroke-dashoffset .6s cubic-bezier(.2,.9,.3,1)}
.ringmid{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px}
.ringmid i{font-style:normal;font-size:30px}
.ringmid b{font-size:26px;font-weight:900}
.ringmid span{font-size:10.5px;color:var(--dim)}
.ratebox{text-align:center;margin-top:16px;font-size:12.5px;font-weight:800;color:var(--c1)}
.endbox{text-align:center;margin-top:6px;font-size:11px;color:var(--dim)}
.statrow{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-top:18px}
.stat{padding:14px 6px;border-radius:18px;text-align:center;border:1px solid var(--line);background:var(--pane)}
.stat i{display:block;font-style:normal;font-size:17px;margin-bottom:6px}
.stat b{display:block;font-size:15px;font-weight:900}
.stat span{display:block;font-size:9.5px;color:var(--dim);margin-top:3px}
.adock{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(11px + var(--safe));z-index:30;
  width:min(94vw,420px);display:flex;gap:3px;padding:7px;border-radius:26px;
  border:1px solid var(--line);background:rgba(255,255,255,.09);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  box-shadow:0 18px 44px -12px rgba(0,0,0,.65),inset 0 1px 0 rgba(255,255,255,.08)}
.adock b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;border-radius:19px;cursor:pointer;color:var(--dim);font-size:9.5px;font-weight:800}
.adock b .navi{width:18px;height:18px}
.adock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.adock b.on{color:#fff;background:linear-gradient(135deg,var(--c3),#8B5CF6)}

.mission{display:flex;align-items:center;gap:11px;padding:13px 14px;border-radius:18px;margin-bottom:9px;
  border:1px solid var(--line);background:var(--pane)}
.mission.done{opacity:.6}
.mission .e{font-size:22px;flex:0 0 auto}
.mission .m{flex:1;min-width:0}
.mission .m b{display:block;font-size:12.5px;font-weight:800}
.mission .mp{display:block;height:5px;border-radius:99px;background:var(--pane2);margin-top:7px;overflow:hidden}
.mission .mp i{display:block;height:100%;background:linear-gradient(90deg,var(--c1),var(--c2));border-radius:inherit}
.mission .mt{display:block;font-size:10px;color:var(--dim);margin-top:5px}
.mission button{flex:0 0 auto;border:0;border-radius:12px;padding:9px 13px;font-family:inherit;font-size:11px;
  font-weight:800;color:#fff;cursor:pointer;background:linear-gradient(135deg,var(--c1),var(--c3))}
.mission button[disabled]{background:var(--pane2);color:var(--dim);cursor:default}

.lbrow{display:flex;align-items:center;gap:11px;padding:12px 14px;border-radius:16px;margin-bottom:8px;
  border:1px solid var(--line);background:var(--pane)}
.lbrow.me{border-color:color-mix(in srgb,var(--c1) 55%,transparent)}
.lbrow .rk{width:26px;height:26px;flex:0 0 auto;border-radius:9px;display:grid;place-items:center;font-size:11.5px;
  font-weight:900;color:#111;background:linear-gradient(135deg,var(--c1),#FFD866)}
.lbrow .m{flex:1;min-width:0;font-size:12.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lbrow .p{flex:0 0 auto;font-size:11.5px;font-weight:800;color:var(--c1)}
.rhint{font-size:11px;color:var(--dim);line-height:1.8;margin:0 0 4px}

/* ═══ شیت خرید ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(3,2,10,.55);backdrop-filter:blur(6px);opacity:0;pointer-events:none;transition:.28s}
.scrim.on{opacity:1;pointer-events:auto}
body.kb-open .scrim{backdrop-filter:none;-webkit-backdrop-filter:none;transition:opacity .28s}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .38s cubic-bezier(.2,.9,.25,1);background:color-mix(in srgb,var(--bg) 88%,#1A1332);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-radius:30px 30px 0 0;border-top:1px solid var(--line);
  padding:10px 17px calc(22px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -24px 60px -20px rgba(0,0,0,.7),inset 0 1px 0 rgba(255,255,255,.08)}
.sheet.on{transform:none}
.grip{width:42px;height:4px;border-radius:4px;background:rgba(255,255,255,.22);margin:4px auto 16px}
.sheet .head{display:flex;align-items:center;gap:13px;margin-bottom:16px}
.sheet .head .orb{width:56px;height:56px;font-size:27px;margin:0}
.sheet .head h2{margin:0;font-size:16.5px;font-weight:900}
.sheet .head p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.7}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--dim);margin-bottom:7px}
.field input,.field textarea{width:100%;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:var(--pane2);color:var(--ink);font-family:inherit;font-size:14.5px;outline:none}
.field textarea{min-height:80px;resize:vertical;font-size:13px}
.field .hint{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.7}
.field .selfrow{display:flex;align-items:center;gap:7px;margin-top:8px;flex-wrap:wrap}
.field .self{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:11.5px;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3));border:0}
.field .self.done{filter:saturate(.5)}
.field .selfrow em{font-style:normal;font-size:10.5px;color:var(--dim);direction:ltr}
.lbl{font-size:10.5px;font-weight:800;letter-spacing:1.1px;color:var(--dim);margin:14px 0 9px}
.plans{display:grid;gap:9px}
.plans i{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:18px;cursor:pointer;
  font-style:normal;border:1px solid var(--line);background:var(--pane2)}
.plans i .pg{width:38px;height:38px;flex:0 0 auto;border-radius:13px;display:grid;place-items:center;font-size:20px;
  text-decoration:none;color:#fff;background:linear-gradient(140deg,var(--c1),var(--c3));border:1px solid rgba(255,255,255,.2)}
.plans i b{flex:1;min-width:0;font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plans i u{flex:0 0 auto;text-decoration:none;font-size:12px;font-weight:800;color:var(--c1)}
.plans i .chk{width:22px;height:22px;flex:0 0 auto;border-radius:7px;border:1.5px solid var(--line);
  display:grid;place-items:center;font-size:13px;font-style:normal;color:transparent}
.plans i.on{border-color:color-mix(in srgb,var(--c1) 60%,transparent);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 12%,transparent),transparent)}
.plans i.on .chk{border-color:transparent;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.total{display:flex;justify-content:space-between;align-items:center;margin:16px 0;padding:15px 16px;
  border-radius:18px;border:1px solid var(--line);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 10%,transparent),color-mix(in srgb,var(--c3) 8%,transparent))}
.total span{font-size:12.5px;color:var(--dim)}
.total b{font-size:20px;font-weight:900;background:linear-gradient(90deg,var(--c1),var(--c3));
  -webkit-background-clip:text;background-clip:text;color:transparent}
.go{width:100%;padding:16px;border:0;border-radius:18px;cursor:pointer;font-family:inherit;font-size:15px;
  font-weight:900;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.go:active{transform:scale(.985)}
.go[disabled]{cursor:default;color:var(--dim);background:var(--pane2);border:1px solid var(--line)}
.go.alt{margin-top:9px;color:var(--ink);background:var(--pane2);border:1px solid var(--line);font-weight:700;font-size:13.5px}
.walbox{margin-top:10px;padding:11px 14px;border-radius:13px;font-size:11.5px;line-height:1.8;
  border:1px solid var(--line);background:var(--pane2);color:var(--dim)}
.walbox b{color:var(--c1)}
.ghost{width:100%;margin-top:9px;padding:14px;border-radius:16px;cursor:pointer;border:1px solid var(--line);
  background:transparent;color:var(--dim);font-family:inherit;font-size:13.5px;font-weight:700}

/* ═══ موفقیت ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:30px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 12%,transparent),transparent 72%)}
.win.on{display:grid}
.ring2{position:relative;width:110px;height:110px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;
  font-size:50px;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3));animation:pop .55s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}to{transform:none;opacity:1}}
.win h2{margin:0 0 9px;font-size:20px;font-weight:900;color:var(--ink)}
.win p{margin:0 0 24px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.win .code{font-family:ui-monospace,monospace;font-size:12px;padding:8px 14px;border-radius:11px;
  border:1px solid var(--line);background:var(--pane2);margin-bottom:20px;direction:ltr;color:var(--ink)}

/* ═══ پیام ═══ */
.toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:13px 18px;border-radius:15px;font-size:12.5px;font-weight:700;max-width:88vw;text-align:center;
  background:linear-gradient(135deg,var(--c4),#B1003A);color:#fff;transition:transform .34s cubic-bezier(.2,1.3,.4,1);
  box-shadow:0 14px 30px -14px color-mix(in srgb,var(--c4) 55%,transparent);line-height:1.7}
.toast.ok{background:linear-gradient(135deg,var(--c2),var(--c1));color:#fff}
.toast.on{transform:translate(-50%,0)}

/* ═══ اسپلش — گرادیانِ آبی، شبکه‌ی ریز، آیکونِ اسکوییرکل، پیجرِ نقطه‌ای ═══
   (طبقِ مرجعِ تازه‌ی کارفرما: پس‌زمینه‌ی آبی به‌جایِ بنفشِ تیره، بدونِ
   نوارِ پیشرفت — به‌جایش سه نقطه‌ی پیجینیشن، مثلِ اسلایدرِ خودِ اپ‌های
   تلگرام) */
#splash{position:fixed;inset:0;z-index:999;display:flex;flex-direction:column;align-items:center;
  background:linear-gradient(165deg,#3B7CF0 0%,#2452B8 45%,#152C63 100%);
  transition:opacity .5s ease,visibility .5s ease;overflow:hidden;padding:0 24px}
#splash.hide{opacity:0;visibility:hidden;pointer-events:none}
.splash-grid{position:absolute;inset:0;pointer-events:none;opacity:.5;
  background-image:
    linear-gradient(rgba(255,255,255,.09) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.09) 1px,transparent 1px);
  background-size:34px 34px}
.splash-dot{position:absolute;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.55);pointer-events:none}
.splash-dot.d1{top:16%;left:12%}
.splash-dot.d2{top:29%;right:10%;width:4px;height:4px}
.splash-dot.d3{bottom:32%;right:22%}
.splash-dot.d4{bottom:20%;left:18%;width:4px;height:4px}
.splash-mid{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:16px;text-align:center;width:100%;max-width:320px}
.splash-icon{position:relative;width:104px;height:104px;border-radius:28px;display:grid;place-items:center;
  background:linear-gradient(155deg,#241436,#130B22);
  border:1px solid rgba(255,255,255,.28);
  box-shadow:inset 1px 1px 0 rgba(255,255,255,.22),inset -2px -2px 10px rgba(0,0,0,.5),
             0 24px 50px -16px rgba(0,0,0,.55);
  animation:splashFloat 3s ease-in-out infinite}
.splash-icon i{font-style:normal;font-size:46px;line-height:1;filter:drop-shadow(0 4px 10px rgba(0,0,0,.35))}
.splash-name{position:relative;margin:2px 0 0;font-size:22px;font-weight:900;color:#fff;letter-spacing:-.2px}
.splash-sub{position:relative;margin:0;font-size:12.5px;color:rgba(255,255,255,.72);line-height:1.8}
.splash-foot{position:relative;width:100%;max-width:280px;padding-bottom:calc(30px + var(--safe));
  display:flex;flex-direction:column;align-items:center;gap:12px}
.splash-pager{display:flex;align-items:center;gap:7px}
.splash-pager i{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.35);font-style:normal}
.splash-pager i:nth-child(1){animation:pagerPulse 1.5s ease-in-out infinite}
.splash-pager i:nth-child(2){animation:pagerPulse 1.5s ease-in-out .5s infinite}
.splash-pager i:nth-child(3){animation:pagerPulse 1.5s ease-in-out 1s infinite}
@keyframes pagerPulse{0%,100%{background:rgba(255,255,255,.35);transform:scale(1)}30%{background:#fff;transform:scale(1.25)}}
.splash-pwr{position:relative;font-size:11.5px;font-weight:700;letter-spacing:.3px;color:rgba(255,255,255,.6)}
@keyframes splashFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@media (prefers-reduced-motion:reduce){ .splash-icon,.splash-pager i{animation:none!important} }
</style>
CSS;
}
