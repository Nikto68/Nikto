<?php
/**
 * 🚀 مینی‌اپِ «ثبت سفارش» — تنها راهِ خریدِ محصول، جایگزینِ گفتگویِ
 * متنیِ قدیمی (لینک → تعداد → سرعت → ادمین → فاکتور، پیام‌به‌پیام).
 * همان اطلاعات را با یک ویزاردِ سه‌مرحله‌ای (اطلاعات → پرداخت → تایید)
 * می‌گیرد و به maOrderSubmit() می‌سپارد — هیچ منطقِ قیمت/پرداخت اینجا
 * تکرار نشده.
 *
 * ظاهر: تمام‌صفحه، شیشه‌ای، فقط سیاه‌وسفید (به‌جایِ رنگ، وضعیت با
 * آیکون/وزنِ فونت/شفافیت نشان داده می‌شود)، بدون هیچ ایموجی — طبق
 * خواستِ صریحِ کارفرما، جدا از پوسته‌ی رنگیِ «فروشگاهِ یکپارچه».
 *
 * سرعت: صفحه یک‌بار ساخته می‌شود (بدونِ فریم‌ورک/کتابخانه)، مراحل با
 * display جابه‌جا می‌شوند (نه ساختِ دوباره‌ی DOM)، و ورودی‌ها فقط یک
 * span را آپدیت می‌کنند — نه رندرِ دوباره‌ی کل فرم روی هر کلید.
 */

function maViewOrder($boot) {
    return strtr(maTplOrder(), [
        '__TITLE__' => 'ثبت سفارش',
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function maTplOrder() {
    return str_replace('__SKIN__', maSkinMono(), maTplOrderBody());
}

/** 🖤 پوسته‌ی مونوکروم — شیشه‌ای، تمام‌صفحه، فقط سیاه/سفید */
function maSkinMono() {
    return <<<'CSS'
<style>
:root{
  --bg:#000; --ink:#fff; --dim:rgba(255,255,255,.55); --dim2:rgba(255,255,255,.32);
  --line:rgba(255,255,255,.16); --line2:rgba(255,255,255,.09);
  --pane:rgba(255,255,255,.07); --pane2:rgba(255,255,255,.04);
  --blur:20px; --r:20px; --safe:env(safe-area-inset-bottom,0px);
  color-scheme:dark;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}
.ico{display:inline-block;width:1em;height:1em;vertical-align:-.15em;flex:0 0 auto}
.ico svg{width:100%;height:100%;display:block}
.dots{display:flex;gap:3px;justify-content:center;margin-bottom:6px}
.dots i{width:6px;height:6px;border-radius:50%;background:var(--line);display:block}
.dots i.on{background:#fff}
img{max-width:100%}
.wrap{position:relative;z-index:1;max-width:560px;margin:0 auto;padding:14px 15px calc(120px + var(--safe))}

/* ═══ سربرگ — کاملا جدا از محتوا: نواری تمام‌عرض با خطِ زیرین، نه یک کارتِ شناور ═══ */
.top{position:sticky;top:0;z-index:9;display:flex;align-items:center;gap:12px;
  margin:0 -15px 14px;padding:14px 15px;
  border-bottom:1px solid var(--line);background:rgba(8,8,8,.86);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  box-shadow:0 8px 24px -18px rgba(0,0,0,.8)}
.top h1{flex:1;margin:0;font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}
.xbtn{width:34px;height:34px;border-radius:11px;border:1px solid var(--line);background:var(--pane);
  color:var(--ink);font-size:15px;display:grid;place-items:center;cursor:pointer;flex:0 0 auto}
.xbtn:active{transform:scale(.94)}

/* ═══ نشانگرِ سه‌مرحله‌ای ═══ */
.stepper{display:flex;align-items:flex-start;justify-content:space-between;margin:0 0 16px;padding:0 4px}
.stepitem{display:flex;flex-direction:column;align-items:center;gap:7px;flex:1;position:relative}
.stepitem:not(:last-child):after{content:"";position:absolute;top:15px;right:calc(50% + 22px);left:calc(-50% + 22px);
  height:1px;background:var(--line)}
.stepitem.done:not(:last-child):after{background:rgba(255,255,255,.55)}
.stepnum{width:30px;height:30px;border-radius:50%;border:1.5px solid var(--line);background:var(--pane2);
  display:grid;place-items:center;font-size:12.5px;font-weight:900;color:var(--dim);position:relative;z-index:1}
.stepitem.on .stepnum{border-color:#fff;background:#fff;color:#000}
.stepitem.done .stepnum{border-color:rgba(255,255,255,.55);background:transparent;color:#fff}
.stepitem.done .stepnum .ico{width:14px;height:14px}
.steplbl{font-size:10.5px;color:var(--dim);font-weight:700}
.stepitem.on .steplbl{color:#fff}

.step{display:none}
.step.on{display:block}

.card{border:1px solid var(--line);background:var(--pane);border-radius:var(--r);padding:16px;margin-bottom:12px;
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
.card h2{margin:0 0 10px;font-size:13.5px;font-weight:800;display:flex;align-items:center;gap:8px}
.card h2 i{font-style:normal;opacity:.7;font-size:12px;font-weight:600}

.prod{display:flex;align-items:center;gap:12px}
.prod .ic{width:52px;height:52px;border-radius:16px;background:var(--pane2);border:1px solid var(--line);
  display:grid;place-items:center;flex:0 0 auto}
.prod .ic .ico{width:26px;height:26px}
.prod .info{flex:1;min-width:0}
.prod .info b{display:block;font-size:15px;font-weight:800;margin-bottom:4px}
.prod .info span{font-size:12.5px;color:var(--dim)}
.prod .price{font-size:15px;font-weight:900;white-space:nowrap}

label{display:block;font-size:12.5px;color:var(--dim);margin:0 0 7px;font-weight:700}
.hint{font-size:11px;color:var(--dim2);margin-top:6px}
input[type=text],input[type=tel],input[type=number],textarea{
  width:100%;border:1px solid var(--line);background:rgba(255,255,255,.04);color:var(--ink);
  border-radius:14px;padding:13px 14px;font-size:14px;font-family:inherit;outline:none;direction:ltr;text-align:left}
input::placeholder,textarea::placeholder{color:var(--dim2)}
input:focus,textarea:focus{border-color:rgba(255,255,255,.4)}
.field{margin-bottom:14px}
.field:last-child{margin-bottom:0}

.qtyrow{display:flex;align-items:center;gap:10px}
.qtybtn{width:44px;height:44px;border-radius:13px;border:1px solid var(--line);background:var(--pane2);
  color:var(--ink);font-size:19px;font-weight:800;display:grid;place-items:center;cursor:pointer;flex:0 0 auto}
.qtybtn:active{transform:scale(.92)}
.qtyrow input{text-align:center;flex:1}

.speedgrid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.speedgrid.n1{grid-template-columns:1fr}
.spcard{border:1px solid var(--line);background:var(--pane2);border-radius:15px;padding:12px;cursor:pointer;text-align:center}
.spcard.on{border-color:#fff;background:rgba(255,255,255,.14)}
.spcard .t{font-size:12.5px;font-weight:800}
.spcard .d{font-size:10.5px;color:var(--dim);margin-top:3px}

.admbox{border:1px dashed var(--line);border-radius:15px;padding:13px;text-align:center}
.admbox p{margin:0 0 11px;font-size:12.5px;color:var(--dim);line-height:1.9}
.admst{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:800;margin-top:10px}

.btnrow{display:flex;gap:9px;margin-top:11px}
.btn{flex:1;border:1px solid var(--line);background:var(--pane2);color:var(--ink);
  border-radius:13px;padding:12px;font-size:12.5px;font-weight:800;font-family:inherit;cursor:pointer;text-align:center;text-decoration:none;display:block}
.btn:active{transform:scale(.97)}
.btn.solid{background:#fff;color:#000;border-color:#fff}

.paypick{display:flex;flex-direction:column;gap:9px}
.paycard{border:1px solid var(--line);background:var(--pane2);border-radius:15px;padding:13px 14px;
  display:flex;align-items:center;gap:11px;cursor:pointer}
.paycard.on{border-color:#fff;background:rgba(255,255,255,.14)}
.paycard .r{width:19px;height:19px;border-radius:50%;border:2px solid var(--line);flex:0 0 auto}
.paycard.on .r{border-color:#fff;background:radial-gradient(circle,#fff 40%,transparent 42%)}
.paycard .m{flex:1}
.paycard .m b{display:block;font-size:13px;font-weight:800}
.paycard .m span{font-size:11px;color:var(--dim)}
.paycard.dis{opacity:.4;pointer-events:none}

/* ═══ خلاصه‌ی تاییدِ نهایی ═══ */
.sumrow{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;
  border-bottom:1px solid var(--line2);font-size:12.5px}
.sumrow:last-child{border-bottom:0}
.sumrow em{font-style:normal;color:var(--dim)}
.sumrow b{font-weight:800;max-width:60%;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sumtotal{display:flex;align-items:center;justify-content:space-between;margin-top:6px;padding-top:12px;
  border-top:1px solid var(--line)}
.sumtotal em{font-style:normal;font-size:13px;font-weight:800}
.sumtotal b{font-size:18px;font-weight:900}

.navrow{display:flex;gap:10px;margin-top:4px}

.foot{position:fixed;left:0;right:0;bottom:0;z-index:9;padding:12px 15px calc(14px + var(--safe));
  background:linear-gradient(to top,rgba(0,0,0,.94) 60%,transparent);}
.footin{max-width:560px;margin:0 auto;border:1px solid var(--line);background:rgba(20,20,20,.82);
  border-radius:18px;padding:12px 14px;backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  display:flex;align-items:center;gap:12px}
.foot .tot{flex:1;min-width:0}
.foot .tot em{display:block;font-size:10.5px;color:var(--dim);font-style:normal;margin-bottom:2px}
.foot .tot b{font-size:16px;font-weight:900}
.backbtn{border:1px solid var(--line);background:transparent;color:var(--ink);border-radius:14px;
  padding:13px 16px;font-size:12.5px;font-weight:800;font-family:inherit;cursor:pointer;flex:0 0 auto}
.backbtn:active{transform:scale(.96)}
.submit{border:0;background:#fff;color:#000;border-radius:14px;padding:13px 22px;font-size:13.5px;font-weight:900;
  font-family:inherit;cursor:pointer;flex:0 0 auto;white-space:nowrap}
.submit:active{transform:scale(.96)}
.submit[disabled]{opacity:.35;pointer-events:none}
.submit.busy{opacity:.6}

.err{font-size:12px;font-weight:700;color:var(--ink);background:rgba(255,255,255,.1);
  border:1px solid var(--line);border-radius:12px;padding:10px 12px;margin-top:10px;display:none}
.err.show{display:block}

.state{position:fixed;inset:0;z-index:20;display:none;align-items:center;justify-content:center;
  background:rgba(0,0,0,.92);backdrop-filter:blur(6px);padding:20px}
.state.show{display:flex}
.statebox{max-width:340px;text-align:center}
.statebox .ic{margin-bottom:14px;display:flex;justify-content:center}
.statebox .ic .ico{width:56px;height:56px}
.statebox h3{margin:0 0 8px;font-size:16px;font-weight:900}
.statebox p{margin:0 0 20px;font-size:13px;color:var(--dim);line-height:1.9}

.spin{width:26px;height:26px;border-radius:50%;border:3px solid rgba(255,255,255,.2);border-top-color:#fff;
  animation:sp .8s linear infinite;margin:0 auto}
@keyframes sp{to{transform:rotate(360deg)}}
.load{position:fixed;inset:0;z-index:30;display:flex;align-items:center;justify-content:center;background:var(--bg)}
.load.hide{display:none}

.step.slidein{animation:stepIn .22s ease}
@keyframes stepIn{0%{opacity:0;transform:translateY(6px)}100%{opacity:1;transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){ .step.slidein{animation:none} }
</style>
CSS;
}

function maTplOrderBody() {
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
<div class="load" id="load"><div class="spin"></div></div>

<div class="wrap" id="wrap" style="display:none">
  <div class="top">
    <button class="xbtn" id="closeBtn"></button>
    <h1></h1>
  </div>

  <div class="stepper" id="stepper">
    <div class="stepitem on" data-s="1"><div class="stepnum">۱</div><div class="steplbl">اطلاعات</div></div>
    <div class="stepitem" data-s="2"><div class="stepnum">۲</div><div class="steplbl">پرداخت</div></div>
    <div class="stepitem" data-s="3"><div class="stepnum">۳</div><div class="steplbl">تایید</div></div>
  </div>

  <div class="card">
    <div class="prod">
      <div class="ic" id="pIcon"></div>
      <div class="info">
        <b id="pName">—</b>
        <span id="pDesc"></span>
      </div>
      <div class="price"><span id="pPrice">—</span></div>
    </div>
  </div>

  <div id="closedArea"></div>

  <div id="step1" class="step on"></div>
  <div id="step2" class="step"></div>
  <div id="step3" class="step"></div>

  <div class="err" id="mainErr"></div>
</div>

<div class="foot" id="foot" style="display:none">
  <div class="footin">
    <button class="backbtn" id="backBtn" style="display:none"></button>
    <div class="tot"><em>مبلغ قابل پرداخت</em><b id="totLine">— تومان</b></div>
    <button class="submit" id="nextBtn">ادامه</button>
  </div>
</div>

<div class="state" id="stateBox">
  <div class="statebox">
    <div class="ic" id="stIcon"></div>
    <h3 id="stTitle">—</h3>
    <p id="stText">—</p>
    <button class="btn solid" id="stClose" style="width:100%">بازگشت به ربات</button>
  </div>
</div>

<script>
(function(){
"use strict";
var B  = __BOOT__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#000000'); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor('#000000'); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}
function fa(n){ n = Math.round((Number(n)||0)*100)/100; try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); } }
function digits(s){
  s = String(s == null ? '' : s);
  var out = '', fa0 = 1776, ar0 = 1632;
  for (var i=0;i<s.length;i++){
    var ch = s[i], c = s.charCodeAt(i);
    if (c >= fa0 && c <= fa0+9) out += (c - fa0);
    else if (c >= ar0 && c <= ar0+9) out += (c - ar0);
    else if (ch >= '0' && ch <= '9') out += ch;
  }
  return out;
}
function intIn(s){ return Math.floor(Number(digits(s)) || 0); }

// 🖼 آیکون‌های خطی — به‌جایِ هر ایموجی، تا ظاهر کاملا تک‌رنگ و یک‌دست بماند
var ICO = {
  close:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  rocket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15c3-1 5.5-4 6-9-5 .5-8 3-9 6l-4 1 2 2 1 3 3 2 1-4Z"/><circle cx="13" cy="10" r="1.4"/><path d="M8 16c-1.5 1-2 3-2 5 2 0 4-.5 5-2M4 13c1-1 2.4-1.4 3.5-1.1"/></svg>',
  box:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8 12 4l8.5 4-8.5 4-8.5-4Z"/><path d="M3.5 8v8L12 20l8.5-4V8"/><path d="M12 12v8"/></svg>',
  link:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 15l6-6"/><path d="M11 6.5 12.3 5a3.6 3.6 0 0 1 5 5L16 11.3"/><path d="M13 17.5 11.7 19a3.6 3.6 0 0 1-5-5L8 12.7"/></svg>',
  users:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.5 14.2c2.6.3 4.5 2.5 4.5 5.3"/></svg>',
  bolt:   '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg>',
  bot:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4"/><circle cx="12" cy="3" r="1"/><circle cx="9" cy="13.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="15" cy="13.5" r="1.3" fill="currentColor" stroke="none"/><path d="M2 12h2M20 12h2"/></svg>',
  plus:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  refresh:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 0 1 14-5.2M20 12a8 8 0 0 1-14 5.2"/><path d="M18.5 3.5v4h-4M5.5 20.5v-4h4"/></svg>',
  clock:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>',
  check:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.3 11 15l4.5-6"/></svg>',
  checkbare:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>',
  xmark:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.5l5 5m0-5-5 5"/></svg>',
  card:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2.4"/><path d="M3 10.5h18"/><path d="M6.5 15h3"/></svg>',
  wallet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5v-8Z"/><path d="M4 8.5V17"/><rect x="14.5" y="11" width="5.5" height="4" rx="1.1"/><circle cx="16.8" cy="13" r=".6" fill="currentColor" stroke="none"/></svg>',
  receipt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-2.5-1.6L13 21l-2.5-1.6L8 21l-2-1.6V3Z"/><path d="M9 8h6M9 12h6M9 16h3.5"/></svg>',
  chevleft:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>',
  clipboard:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 11h6M9 15h6"/></svg>',
};
function ic(name){ return '<span class="ico">' + (ICO[name] || '') + '</span>'; }

function closeApp(){ try{ TG && TG.close ? TG.close() : window.close(); }catch(e){ window.close(); } }
$('closeBtn').innerHTML = ic('close');
$('closeBtn').onclick = closeApp;
$('stClose').onclick = closeApp;
document.querySelector('.top h1').innerHTML = ic('rocket') + ' ثبت سفارش';

function showState(iconName, title, text, isFinal){
  $('stIcon').innerHTML = ic(iconName);
  $('stTitle').textContent = title;
  $('stText').textContent = text;
  $('stateBox').classList.add('show');
  if (!isFinal) $('stClose').style.display = 'none'; else $('stClose').style.display = '';
}

if (!B.ok) {
  $('load').classList.add('hide');
  showState('xmark', 'خطا', B.error || 'مشکلی پیش آمد.', true);
  return;
}

function api(action, extra, ok, bad){
  if (!B.api){ bad({ message:'آدرس سرور مینی‌اپ تنظیم نشده است.' }); return; }
  var body = Object.assign({ action:action, app:'order', pid:B.product.id,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'پاسخ سرور نامعتبر بود.'}; }); })
    .then(function(j){ if (j && j.ok) ok(j); else bad(j || {}); })
    .catch(function(){ bad({ message:'اتصال برقرار نشد.' }); });
}

var P = B.product, F = B.flow || {on:false};
var S = { step:1, qty: F.min || 1, speed: (F.speeds && F.speeds[0]) ? F.speeds[0].id : '', link: '',
          pay: B.wallet_ok ? 'wallet' : 'manual', adminOk: false, busy:false };

$('pIcon').innerHTML = ic('box');
$('pName').textContent = P.name;
$('pDesc').textContent = P.desc || '';
$('pPrice').textContent = fa(P.price) + ' ' + P.currency;
document.title = P.name;

function findSpeed(id){ for (var i=0;i<(F.speeds||[]).length;i++) if (F.speeds[i].id === id) return F.speeds[i]; return null; }
function curRate(){
  if (!F.on) return P.price;
  var sp = (F.speeds || []).length > 1 ? findSpeed(S.speed) : (F.speeds || [])[0];
  return sp ? sp.rate : P.price;
}
function curTotal(){
  if (!F.on) return P.price;
  var per = F.per || 1000;
  return Math.round((curRate() * (S.qty / per)) * 100) / 100;
}
function renderTotal(){ $('totLine').textContent = fa(curTotal()) + ' ' + P.currency; }

// ---- محصول بسته است؟ (بدونِ فرم، بدونِ ویزارد) ----
if (P.closed) {
  var msg = P.closed === 'bought' ? 'این محصول را قبلا خریده‌اید.'
          : P.closed === 'full'   ? 'ظرفیتِ این محصول تکمیل شده است.'
          : 'این محصول در حال حاضر در دسترس نیست.';
  $('closedArea').innerHTML = '<div class="card"><p style="margin:0;font-size:13px;color:var(--dim);text-align:center">' + esc(msg) + '</p></div>';
  $('stepper').style.display = 'none';
  $('load').classList.add('hide');
  $('wrap').style.display = '';
  return;
}

// ---- مرحله‌ی ۱: اطلاعات ----
var h1 = '';
if (F.on) {
  if (F.ask_link) {
    h1 += '<div class="card"><h2>' + ic('link') + ' لینک کانال/گروه</h2>' +
      '<div class="field"><input type="text" id="fLink" placeholder="https://t.me/YourChannel یا لینک دعوتِ خصوصی" dir="ltr"></div>' +
      '<div class="hint">لینکِ خصوصی هم می‌پذیرد؛ فقط باید با https://t.me/ شروع شود.</div></div>';
  }
  if (F.ask_qty) {
    h1 += '<div class="card"><h2>' + ic('users') + ' تعداد <i>بینِ ' + fa(F.min) + ' تا ' + fa(F.max) + '</i></h2>' +
      '<div class="qtyrow">' +
        '<button class="qtybtn" id="qMinus" type="button">−</button>' +
        '<input type="text" inputmode="numeric" id="fQty" value="' + fa(S.qty) + '">' +
        '<button class="qtybtn" id="qPlus" type="button">+</button>' +
      '</div></div>';
  }
  if ((F.speeds || []).length > 1) {
    h1 += '<div class="card"><h2>' + ic('bolt') + ' سرعت</h2><div class="speedgrid" id="spGrid">';
    var nSp = F.speeds.length;
    F.speeds.forEach(function(sp, i){
      var dots = '<div class="dots">';
      for (var d = 0; d < nSp; d++) dots += '<i class="' + (d <= i ? 'on' : '') + '"></i>';
      dots += '</div>';
      h1 += '<div class="spcard' + (i===0?' on':'') + '" data-id="' + esc(sp.id) + '">' +
        dots + '<div class="t">' + esc(sp.text) + '</div>' +
        (sp.per_day ? '<div class="d">' + fa(sp.per_day) + '/روز</div>' : '') + '</div>';
    });
    h1 += '</div></div>';
  }
  if (F.ask_admin) {
    h1 += '<div class="card"><h2>' + ic('bot') + ' ادمینِ کانال</h2>' +
      '<div class="admbox" id="admBox"><p>ربات باید ادمینِ کامل کانال/گروه باشد تا سفارش پردازش شود.</p>' +
      '<div class="btnrow">' +
        '<a class="btn" id="addBotBtn" href="#" target="_blank">' + ic('plus') + ' افزودنِ ربات</a>' +
        '<button class="btn solid" id="checkAdmBtn" type="button">' + ic('refresh') + ' بررسیِ دوباره</button>' +
      '</div>' +
      '<div class="admst" id="admStatus">' + ic('clock') + ' هنوز تایید نشده</div>' +
      '</div></div>';
  }
}
if (h1 === '') h1 = '<div class="card"><p style="margin:0;font-size:12.5px;color:var(--dim);text-align:center">این محصول نیازی به اطلاعاتِ اضافه ندارد — «ادامه» را بزنید.</p></div>';
$('step1').innerHTML = h1;

// ---- مرحله‌ی ۲: پرداخت ----
$('step2').innerHTML =
  '<div class="card"><h2>' + ic('card') + ' روشِ پرداخت</h2><div class="paypick" id="payPick">' +
  '<div class="paycard' + (B.wallet_ok ? (S.pay==='wallet'?' on':'') : ' dis') + '" id="walletCard" data-pay="wallet">' +
    '<div class="r"></div><div class="m"><b>کیف پول</b><span>موجودی: <span id="balSpan">…</span> تومان</span></div></div>' +
  '<div class="paycard' + (S.pay==='manual'?' on':'') + '" data-pay="manual">' +
    '<div class="r"></div><div class="m"><b>کارت به کارت / رمزارز</b><span>پرداخت دستی، بعد ارسالِ رسید در چت</span></div></div>' +
  '</div></div>';

// ---- مرحله‌ی ۳: تایید ----
$('step3').innerHTML =
  '<div class="card"><h2>' + ic('clipboard') + ' خلاصه‌ی سفارش</h2><div id="sumBox"></div></div>';

$('load').classList.add('hide');
$('wrap').style.display = '';
$('foot').style.display = '';
renderTotal();

// 🔄 موجودیِ زنده + بازبینیِ نهاییِ در‌دسترس‌بودن (initData همین‌جا امضا/تایید می‌شود)
api('order_boot', {}, function(j){
  var b = $('balSpan'); if (b) b.textContent = fa(j.balance || 0);
  B.balance = j.balance || 0;
  if (j.closed) {
    var msg = j.closed === 'bought' ? 'این محصول را قبلا خریده‌اید.'
            : j.closed === 'full'   ? 'ظرفیتِ این محصول تکمیل شده است.'
            : 'این محصول در حال حاضر در دسترس نیست.';
    goStep(1);
    $('closedArea').innerHTML = '<div class="card"><p style="margin:0;font-size:13px;color:var(--dim);text-align:center">' + esc(msg) + '</p></div>';
    $('step1').style.display = 'none'; $('step2').style.display = 'none'; $('step3').style.display = 'none';
    $('stepper').style.display = 'none';
    $('foot').style.display = 'none';
  }
}, function(j){
  var b = $('balSpan'); if (b) b.textContent = '؟';
  showErr(j.message || 'موجودیِ کیف‌پول دریافت نشد.');
});

if ($('fLink')) $('fLink').oninput = function(){ S.link = this.value; };
if ($('fQty')) {
  $('fQty').oninput = function(){ this.value = fa(intIn(this.value)); S.qty = intIn(this.value) || 0; renderTotal(); };
  $('qMinus').onclick = function(){ tap(); S.qty = Math.max(F.min||1, S.qty - (F.per||1)); $('fQty').value = fa(S.qty); renderTotal(); };
  $('qPlus').onclick  = function(){ tap(); var mx = F.max||999999999; S.qty = Math.min(mx, S.qty + (F.per||1)); $('fQty').value = fa(S.qty); renderTotal(); };
}
if ($('spGrid')) {
  $('spGrid').querySelectorAll('.spcard').forEach(function(el){
    el.onclick = function(){
      tap();
      $('spGrid').querySelectorAll('.spcard').forEach(function(x){ x.classList.remove('on'); });
      el.classList.add('on');
      S.speed = el.getAttribute('data-id');
      renderTotal();
    };
  });
}
if ($('addBotBtn') && B.bot) {
  $('addBotBtn').href = 'https://t.me/' + B.bot + '?startchannel&admin=invite_users+promote_members';
}
if ($('checkAdmBtn')) {
  $('checkAdmBtn').onclick = function(){
    tap();
    $('admStatus').innerHTML = ic('clock') + ' در حال بررسی…';
    api('order_check_admin', { link: S.link }, function(j){
      S.adminOk = !!j.admin_ok;
      if (j.admin_ok) $('admStatus').innerHTML = ic('check') + ' تایید شد — ' + esc(j.title || '');
      else if (j.pending) $('admStatus').innerHTML = ic('clock') + ' منتظرِ افزودنِ ربات — بعد از ادمین‌کردن دوباره بزنید';
      else $('admStatus').innerHTML = ic('xmark') + ' هنوز ادمین نشده';
    }, function(j){
      $('admStatus').innerHTML = ic('xmark') + ' ' + esc(j.message || 'لینک را اول وارد کنید');
    });
  };
}
$('payPick').querySelectorAll('.paycard').forEach(function(el){
  if (el.classList.contains('dis')) return;
  el.onclick = function(){
    tap();
    $('payPick').querySelectorAll('.paycard').forEach(function(x){ x.classList.remove('on'); });
    el.classList.add('on');
    S.pay = el.getAttribute('data-pay');
  };
});

function showErr(msg){
  var e = $('mainErr'); e.textContent = msg; e.classList.add('show');
  setTimeout(function(){ e.classList.remove('show'); }, 5000);
}

// ---- ناوبریِ ویزارد ----
function stepValid(n){
  if (n === 1 && F.on && F.ask_link && !S.link.trim()) { showErr('لینک کانال را وارد کنید.'); return false; }
  if (n === 1 && F.on && F.ask_admin && !S.adminOk) { showErr('اول باید ربات را ادمینِ کانال کنید و «بررسیِ دوباره» بزنید.'); return false; }
  return true;
}
function renderSummary(){
  var rows = [];
  rows.push(['محصول', P.name]);
  if (F.on) {
    if (F.ask_link) rows.push(['لینک', S.link || '—']);
    if (F.ask_qty)  rows.push(['تعداد', fa(S.qty)]);
    var sp = (F.speeds||[]).length ? (findSpeed(S.speed) || F.speeds[0]) : null;
    if (sp) rows.push(['سرعت', sp.text]);
  }
  rows.push(['روشِ پرداخت', S.pay === 'wallet' ? 'کیف پول' : 'کارت به کارت / رمزارز']);
  var html = '';
  rows.forEach(function(r){ html += '<div class="sumrow"><em>' + esc(r[0]) + '</em><b>' + esc(r[1]) + '</b></div>'; });
  html += '<div class="sumtotal"><em>مبلغِ قابلِ پرداخت</em><b>' + fa(curTotal()) + ' ' + esc(P.currency) + '</b></div>';
  $('sumBox').innerHTML = html;
}
function goStep(n){
  S.step = n;
  [1,2,3].forEach(function(i){
    var el = $('step' + i);
    el.classList.remove('on','slidein');
    if (i === n) { el.classList.add('on'); void el.offsetWidth; el.classList.add('slidein'); }
  });
  document.querySelectorAll('.stepitem').forEach(function(el){
    var s = parseInt(el.getAttribute('data-s'), 10);
    el.classList.remove('on','done');
    if (s === n) el.classList.add('on');
    else if (s < n) el.classList.add('done');
  });
  var nums = ['۱','۲','۳'];
  document.querySelectorAll('.stepitem').forEach(function(el){
    var s = parseInt(el.getAttribute('data-s'), 10);
    el.querySelector('.stepnum').innerHTML = el.classList.contains('done') ? ic('checkbare') : nums[s-1];
  });
  $('backBtn').style.display = n > 1 ? '' : 'none';
  $('backBtn').innerHTML = ic('chevleft') + ' قبلی';
  $('nextBtn').textContent = n < 3 ? 'ادامه' : 'ثبتِ نهایی';
  if (n === 3) renderSummary();
  window.scrollTo(0, 0);
}

$('backBtn').onclick = function(){ tap(); if (S.step > 1) goStep(S.step - 1); };
$('nextBtn').onclick = function(){
  if (S.busy) return;
  if (S.step < 3) {
    if (!stepValid(S.step)) return;
    tap();
    goStep(S.step + 1);
    return;
  }
  // مرحله‌ی سوم: ثبتِ نهایی
  S.busy = true;
  $('nextBtn').classList.add('busy');
  $('nextBtn').textContent = '…';
  $('backBtn').style.display = 'none';
  api('order_submit', { link: S.link, qty: S.qty, speed_id: S.speed, pay: S.pay }, function(j){
    S.busy = false;
    if (j.manual) {
      showState('receipt', 'در انتظارِ پرداخت', 'سفارش ثبت شد. برای دیدنِ اطلاعاتِ پرداخت و ارسالِ رسید، به چتِ خصوصیِ ربات برگردید.', true);
    } else if (j.needs_topup) {
      showState('wallet', 'نیاز به شارژ', 'موجودیِ کیف‌پول کافی نبود؛ درخواستِ شارژ در چتِ خصوصیِ ربات برایتان ارسال شد.', true);
    } else {
      showState('check', 'سفارش ثبت شد', 'خریدتان با موفقیت ثبت شد. جزئیات در چتِ خصوصیِ ربات برایتان ارسال شده.', true);
    }
  }, function(j){
    S.busy = false;
    $('nextBtn').classList.remove('busy');
    $('nextBtn').textContent = 'ثبتِ نهایی';
    $('backBtn').style.display = '';
    if (j.need_admin) { showErr(j.message || 'ابتدا ربات را ادمینِ کانال کنید.'); goStep(1); return; }
    showErr(j.message || j.error || 'مشکلی پیش آمد، دوباره امتحان کنید.');
  });
};
})();
</script>
</body>
</html>
HTML;
}
