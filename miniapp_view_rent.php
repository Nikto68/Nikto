<?php
/**
 * 🎁 نمای مینی‌اپِ «اجاره‌ی گیفت» — کاملاً جدا از دو مینی‌اپِ دیگر.
 *
 * ⚠️ تنها مینی‌اپی که به یک کتابخانه‌ی بیرونی (TonConnect SDK) نیاز
 *    دارد — چون اتصالِ کیف‌پولِ واقعیِ مشتری یک پروتکلِ رمزنگاری‌شده است
 *    که دست‌ساز پیاده‌سازی‌کردنش خطرِ امنیتی دارد. به همین دلیل
 *    giftrent.php برای همین یک صفحه، CSP جداگانه (شل‌تر) دارد؛ دو
 *    مینی‌اپِ دیگر دست‌نخورده می‌مانند.
 *
 * ⚠️ tonconnect-manifest.json دیگر فایلِ استاتیک نیست — قبلاً باید دستی
 *    روی ریشه‌ی دامنه آپلود می‌شد که همیشه سرِ راه بود («خطای بارگذاریِ
 *    dApp manifest» چون فایل اصلاً وجود نداشت). حالا خودِ
 *    bot_master_membership.php آن را می‌سازد (grManifestOut در
 *    giftrent.php) — آدرسش پایین با MANIFEST مشخص شده.
 *
 * 🖼 عکسِ خودِ گیفت: marketapp.org آدرسِ عکس را در لیست نمی‌دهد، ولی
 *    هر گیفتِ آپگریدشده‌ی تلگرام دقیقاً همین الگوی عمومی و مستندِ
 *    fragment.com را دارد: nft.fragment.com/gift/{اسم-بدونِ-فاصله}-{شماره}.medium.jpg
 *    این الگو سمتِ کلاینت (جاوااسکریپت) از روی «اسمِ گیفت #شماره‌اش»
 *    ساخته می‌شود؛ اگر عکس نبود (onerror)، آیکونِ جایگزین نشان داده می‌شود.
 */

function grViewRent() {
    // ⚠️ base_url خودش کاملِ مسیرِ bot_master_membership.php است
    // (مثلا https://DOMAIN/bot_master_membership.php) — هم API هم
    // manifest از همینجا (با کوئری‌استرینگِ متفاوت) سرو می‌شوند.
    $base = trim((string)(maCfg()['base_url'] ?? ''));
    $manifest = $base !== '' ? $base . (str_contains($base, '?') ? '&' : '?') . 'tonconnect_manifest=1' : '';
    $api = $base !== '' ? $base . (str_contains($base, '?') ? '&' : '?') . 'rent_api=1' : '';

    return strtr(grTplRent(), [
        '__MANIFEST__' => json_encode($manifest, JSON_UNESCAPED_SLASHES),
        '__API__'      => json_encode($api, JSON_UNESCAPED_SLASHES),
    ]);
}

function grTplRent() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1,user-scalable=no">
<meta name="color-scheme" content="dark">
<title>اجاره‌ی گیفت</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tonconnect/sdk@3/dist/tonconnect-sdk.min.js"></script>
<style>
:root{
  --bg:#07090A;
  --glass:rgba(255,255,255,.045); --glass2:rgba(255,255,255,.08);
  --hair:rgba(255,255,255,.09);
  --ink:#F3F6F4; --dim:#8FA098; --dim2:#5C6B64;
  /* آبیِ قیمت — دقیقاً هم‌رنگِ پیلِ قیمتِ مارکت‌های گیفت */
  --acc:#4FA3FF; --acc-dim:rgba(79,163,255,.18);
  --ok:#7FD9A6; --bad:#E28B93;
  --ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Vazirmatn,Tahoma,sans-serif;
  --navh:64px;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{
  margin:0;color:var(--ink);font-family:var(--ui);padding:14px;
  padding-bottom:calc(var(--navh) + env(safe-area-inset-bottom, 0px) + 20px);
  background:
    radial-gradient(120% 60% at 15% -10%, rgba(127,217,166,.10), transparent 60%),
    radial-gradient(90% 50% at 100% 0%, rgba(255,255,255,.05), transparent 55%),
    var(--bg);
  background-attachment:fixed;
}
@keyframes float{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-3px) rotate(-6deg)}}
@keyframes glow{0%,100%{opacity:.55}50%{opacity:1}}
@keyframes pop{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}

.topbar{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:2px 0 12px}
.topbar .brand{display:flex;align-items:center;gap:8px;font-weight:700;font-size:16px}
.topbar .ic{display:inline-block;animation:float 3.2s ease-in-out infinite}
.balance{
  display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:20px;font-size:12px;font-weight:700;
  background:var(--glass2);border:1px solid var(--hair);color:var(--ink);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
}

/* ── نوارِ جستجو/فیلتر ── */
.filterbar{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.searchbox{
  flex:1;display:flex;align-items:center;gap:6px;padding:9px 12px;border-radius:14px;
  background:var(--glass);border:1px solid var(--hair);color:var(--dim);font-size:12.5px;
}
.searchbox input{flex:1;background:transparent;border:0;outline:0;color:var(--ink);font:inherit;min-width:0}
.searchbox input::placeholder{color:var(--dim2)}
.iconbtn{
  width:34px;height:34px;flex:none;border-radius:12px;display:flex;align-items:center;justify-content:center;
  background:var(--glass);border:1px solid var(--hair);color:var(--dim);font-size:14px;
}
.iconbtn.on{background:var(--acc-dim);color:var(--acc);border-color:rgba(79,163,255,.35)}
.countline{color:var(--dim2);font-size:11px;margin:2px 2px 10px}

/* ── پنلِ فیلتر — تاشو، زیرِ نوارِ جستجو ── */
.filter-panel{
  background:#14181680;border:1px solid var(--hair);border-radius:16px;padding:10px;margin-bottom:10px;
  animation:pop .2s ease both;
}
.acc-row{border-radius:12px;overflow:hidden;margin-bottom:6px}
.acc-head{
  display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 12px;cursor:pointer;
  background:var(--glass)
}
.acc-head .t{font-size:12.5px;font-weight:600}
.acc-head .r{display:flex;align-items:center;gap:8px}
.acc-clear{font-size:11px;color:var(--acc)}
.acc-chev{font-size:10px;color:var(--dim2);transition:transform .2s}
.acc-row.open .acc-chev{transform:rotate(180deg)}
.acc-body{display:none;padding:10px 12px 4px}
.acc-row.open .acc-body{display:block}
.acc-chips{display:flex;gap:7px;flex-wrap:wrap}
.filter-foot{display:flex;gap:8px;margin-top:4px}
.filter-foot button{flex:1;padding:10px}
.btn-ghost{background:var(--glass);border:1px solid var(--hair);color:var(--ink)}

/* ── گریدِ کارت‌ها ── */
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.gcard{
  position:relative;border-radius:14px;overflow:hidden;cursor:pointer;
  background:#1A1D1B;border:1px solid var(--hair);
  box-shadow:0 4px 16px rgba(0,0,0,.3);
  animation:pop .3s ease both;transition:transform .12s;
}
.gcard:active{transform:scale(.96)}
/* ⚡ شیمرِ لودینگ فقط رو .loading اجراست، نه همیشه — با صدتا کارتِ هم‌زمان،
   انیمیشنِ همیشگی روی هرکدوم دقیقاً همون چیزی بود که اسکرول رو سنگین می‌کرد */
.gimg-wrap{position:relative;aspect-ratio:1/1;background:#22261F}
.gimg-wrap.loading{
  background:linear-gradient(120deg, rgba(255,255,255,.05) 25%, rgba(255,255,255,.10) 37%, rgba(255,255,255,.05) 63%);
  background-size:200% 100%;animation:shimmer 1.6s linear infinite;
}
.gimg-wrap img{width:100%;height:100%;object-fit:cover;display:block;position:relative;z-index:1}
.gfallback{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  font-size:28px;background:radial-gradient(circle at 50% 35%, var(--acc-dim), transparent 70%);
}
.gbadge{
  position:absolute;top:6px;right:6px;z-index:2;width:22px;height:22px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;
  background:rgba(0,0,0,.5);
}
.gdays{
  position:absolute;left:0;right:0;bottom:0;z-index:2;padding:5px 7px 6px;
  font-size:9.5px;font-weight:600;color:#fff;
  background:linear-gradient(0deg, rgba(0,0,0,.65), transparent);
}
.ginfo{padding:8px 8px 9px}
.gname-row{display:flex;align-items:baseline;gap:4px;margin-bottom:6px;overflow:hidden}
.gname{font-size:11.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gnum{font-size:10px;color:var(--dim2);flex:none;direction:ltr}
.gprices{display:flex;gap:6px}
.gprices>div{flex:1;min-width:0}
.gprices .lbl{font-size:9px;color:var(--dim2);margin-bottom:2px;white-space:nowrap}
.gprices .val{font-size:11px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:3px}
.gprices .val .ic{color:var(--acc);font-size:10px}

.empty{text-align:center;color:var(--dim);padding:52px 10px;font-size:13px;grid-column:1/-1}

/* ── لیستِ «اجاره‌های من» ── */
.rcard{
  display:flex;align-items:center;gap:11px;
  background:linear-gradient(160deg, var(--glass2), var(--glass));
  border:1px solid var(--hair);border-radius:16px;padding:10px;margin-bottom:10px;
  animation:pop .3s ease both;
}
.rcard .thumb{width:52px;height:52px;border-radius:12px;flex:none;overflow:hidden;position:relative;
  background:var(--acc-dim)}
.rcard .thumb img{width:100%;height:100%;object-fit:cover}
.rcard .thumb .gfallback{font-size:22px}
.rcard .body{flex:1;min-width:0}
.rcard h4{margin:0 0 3px;font-size:13.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10.5px;
  background:var(--glass);border:1px solid var(--hair)}
.pill.active{background:var(--acc-dim);color:var(--ok);border-color:rgba(127,217,166,.35)}
.pill.wait{background:rgba(255,255,255,.09);color:var(--ink)}
.pill.failed{background:rgba(226,139,147,.16);color:var(--bad)}
.rcard .btn-mini{margin-top:6px;padding:7px 12px;font-size:12px}

button{border:0;border-radius:14px;padding:12px 14px;font-family:inherit;font-size:14px;cursor:pointer;
  transition:transform .12s, opacity .12s}
button:active{transform:scale(.97)}
.btn-main{
  width:100%;margin-top:12px;color:#08130E;font-weight:700;
  background:linear-gradient(135deg, var(--acc), #A9D6FF);
  box-shadow:0 6px 20px rgba(79,163,255,.3);
}
.btn-main:disabled{opacity:.45;box-shadow:none}
.btn-tonkeeper{
  display:block;width:100%;margin-top:10px;text-align:center;text-decoration:none;
  box-sizing:border-box;padding:24px 20px;font-size:18px;font-weight:800;color:#08130E;
  border-radius:32px;background:linear-gradient(135deg, var(--acc), #A9D6FF);
  box-shadow:0 12px 28px rgba(79,163,255,.4), inset 0 1px 0 rgba(255,255,255,.4);
}
.btn-tonkeeper:active{transform:scale(.97)}
input[type=range]{width:100%;accent-color:var(--acc)}
.dim{color:var(--dim);font-size:12px}

/* ── نوارِ پایینِ شیشه‌ای ── */
.navbar{
  position:fixed;left:14px;right:14px;bottom:calc(env(safe-area-inset-bottom, 0px) + 12px);
  height:var(--navh);display:flex;gap:6px;padding:6px;border-radius:22px;
  /* بلورِ سنگین اینجا با شیت‌شیتِ پایین همزمان روی موبایل لگ می‌داد —
     پس‌زمینه‌ی نیمه‌کدر جای بلور رو گرفته، ظاهر شیشه‌ای می‌مونه بدون هزینه‌ی رندر */
  background:rgba(13,17,15,.86);border:1px solid var(--hair);
  box-shadow:0 10px 34px rgba(0,0,0,.45);z-index:6;
}
.navbtn{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;
  background:transparent;color:var(--dim);font-size:11px;border-radius:16px;
}
.navbtn span{font-size:18px;transition:transform .2s}
.navbtn.on{background:var(--acc-dim);color:var(--acc)}
.navbtn.on span{transform:translateY(-1px)}

/* ── بات‌شیت ── */
.sheet-bg{position:fixed;inset:0;background:rgba(2,4,3,.6);
  opacity:0;pointer-events:none;transition:opacity .2s;z-index:8}
.sheet-bg.show{opacity:1;pointer-events:auto}
.sheet{
  /* ⚡ بدونِ backdrop-filter عمداً: بلورِ یه پنلِ تمام‌عرضِ متحرک، هم‌زمان
     با کشیدنِ اسلایدرِ روز، رویِ موبایل کاملاً محسوس لگ می‌داد. پس‌زمینه‌ی
     تقریباً کدر همون حسِ شیشه‌ای رو می‌ده بدونِ بارِ رندرِ مداوم. */
  position:fixed;left:0;right:0;bottom:0;z-index:9;
  background:linear-gradient(180deg, #101613F5, #0A0F0DFB);
  border:1px solid var(--hair);border-bottom:none;border-radius:24px 24px 0 0;
  padding:10px 16px calc(env(safe-area-inset-bottom, 0px) + 20px);
  transform:translateY(105%);transition:transform .25s cubic-bezier(.2,.8,.2,1);
  box-shadow:0 -10px 40px rgba(0,0,0,.5);
  will-change:transform;
}
.sheet.open{transform:translateY(0)}
.sheet-handle{width:36px;height:4px;border-radius:3px;background:var(--hair);margin:4px auto 14px}
.sheet-img{width:100%;aspect-ratio:1.6/1;border-radius:16px;overflow:hidden;position:relative;margin-bottom:12px;
  background:var(--acc-dim)}
.sheet-img img{width:100%;height:100%;object-fit:cover}
.sheet-img .gfallback{font-size:46px}
.sheet h3{margin:0 0 2px;font-size:16px}
.toast{position:fixed;bottom:calc(var(--navh) + env(safe-area-inset-bottom, 0px) + 24px);left:16px;right:16px;
  background:rgba(20,24,22,.92);border:1px solid var(--hair);border-radius:14px;padding:13px;font-size:13px;
  text-align:center;display:none;z-index:10;animation:pop .25s ease both}
</style>
</head>
<body>
<div class="topbar">
  <div class="brand"><span class="ic">🎁</span> اجاره‌ی گیفت</div>
  <div class="balance" id="balancePill" onclick="openAccountSheet()">👛 —</div>
</div>

<div class="filterbar">
  <div class="searchbox">🔎<input id="search" placeholder="جستجوی گیفت…" oninput="renderGrid()"></div>
  <div class="iconbtn" id="filterBtn" onclick="toggleFilterPanel()">🎛</div>
  <div class="iconbtn" id="sortBtn" onclick="toggleSort()">↕️</div>
</div>
<div class="filter-panel" id="filterPanel" style="display:none"></div>
<div class="countline" id="countLine"></div>

<div id="viewList" class="grid"></div>
<div id="viewMine" style="display:none"></div>
<div class="toast" id="toast"></div>

<div class="sheet-bg" id="sheetBg" onclick="closeSheet()"></div>
<div class="sheet" id="sheet">
  <div class="sheet-handle"></div>
  <div id="sheetBody"></div>
</div>

<nav class="navbar">
  <button class="navbtn on" id="navList" onclick="showTab('list')"><span>🎁</span>گیفت‌ها</button>
  <button class="navbtn" id="navMine" onclick="showTab('mine')"><span>👤</span>اجاره‌های من</button>
</nav>

<script>
const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
if (tg) { tg.ready(); tg.expand(); }
const API = __API__;
const MANIFEST = __MANIFEST__;
const initData = tg ? tg.initData : '';
let currentItems = [];

function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3500);
}

async function call(action, extra) {
  const body = Object.assign({ action, initData }, extra || {});
  const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return r.json();
}

function showTab(which) {
  document.getElementById('navList').classList.toggle('on', which === 'list');
  document.getElementById('navMine').classList.toggle('on', which === 'mine');
  document.getElementById('viewList').style.display = which === 'list' ? '' : 'none';
  document.getElementById('viewMine').style.display = which === 'mine' ? '' : 'none';
  if (which === 'mine') loadMine();
}

function fmt(n) { return Math.round(n).toLocaleString('fa-IR'); }

/**
 * 🖼 آدرسِ عکسِ گیفت — از روی الگویِ عمومیِ خودِ فرگمنت ساخته می‌شود:
 * nft.fragment.com/gift/{اسم-بدونِ-فاصله-کوچک}-{شماره}.medium.jpg
 * اگر اسم شکلِ «... #عدد» نداشت یا عکس نبود، آیکونِ جایگزین می‌ماند.
 */
function fragmentImgUrl(name) {
  const m = /^(.*?)\s*#(\d+)\s*$/.exec(String(name || '').trim());
  if (!m) return null;
  const slug = m[1].toLowerCase().replace(/[^a-z0-9]/g, '');
  if (!slug) return null;
  return 'https://nft.fragment.com/gift/' + slug + '-' + m[2] + '.medium.jpg';
}

function makeImgBox(name, className) {
  const wrap = document.createElement('div'); wrap.className = className;
  const url = fragmentImgUrl(name);
  const fb = document.createElement('div'); fb.className = 'gfallback'; fb.textContent = '🎁';
  if (url) {
    wrap.classList.add('loading');   // فقط تا وقتی عکس نیومده شیمر داره
    const img = document.createElement('img');
    img.loading = 'lazy'; img.alt = name;
    img.onload = function () { wrap.classList.remove('loading'); };
    img.onerror = function () { wrap.classList.remove('loading'); img.remove(); wrap.appendChild(fb); };
    img.src = url;
    wrap.appendChild(img);
  } else {
    wrap.appendChild(fb);
  }
  return wrap;
}

function splitName(name) {
  const m = /^(.*?)\s*#(\d+)\s*$/.exec(String(name || '').trim());
  return m ? { base: m[1], num: '#' + m[2] } : { base: name, num: '' };
}

let sortDir = 'asc';
function toggleSort() {
  sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  document.getElementById('sortBtn').classList.toggle('on', sortDir === 'desc');
  renderGrid();
}

// ── فیلتر ──
let activeFilters = { backdrop: null, maxPrice: null };
function attrVal(it, trait) {
  const a = (it.attributes || []).find(function (x) { return (x.trait_type || x.traitType || '') === trait; });
  return a ? String(a.value || '') : '';
}
function uniqueAttrValues(trait) {
  const set = new Set();
  currentItems.forEach(function (it) { const v = attrVal(it, trait); if (v) set.add(v); });
  return Array.from(set).sort();
}
function filtersActiveCount() {
  return ['backdrop', 'maxPrice'].filter(function (k) { return activeFilters[k]; }).length;
}

async function loadList() {
  const el = document.getElementById('viewList');
  el.innerHTML = '<div class="empty">در حال بارگذاری…</div>';
  const res = await call('list');
  if (!res.ok || !res.items || !res.items.length) {
    currentItems = [];
    el.innerHTML = '<div class="empty">فعلاً گیفتی برای اجاره موجود نیست.</div>';
    document.getElementById('countLine').textContent = '';
    return;
  }
  currentItems = res.items;
  renderGrid();
}

function renderGrid() {
  const el = document.getElementById('viewList');
  const q = (document.getElementById('search').value || '').trim().toLowerCase();
  document.getElementById('filterBtn').classList.toggle('on', filtersActiveCount() > 0);
  let items = currentItems
    .map(function (it, idx) { return Object.assign({ _idx: idx }, it); })
    .filter(function (it) { return !q || it.nft_name.toLowerCase().includes(q); })
    .filter(function (it) { return !activeFilters.backdrop || attrVal(it, 'Backdrop') === activeFilters.backdrop; })
    .filter(function (it) { return !activeFilters.maxPrice || it.price_day <= activeFilters.maxPrice; });
  items.sort(function (a, b) { return sortDir === 'asc' ? a.price_day - b.price_day : b.price_day - a.price_day; });

  const totalCount = items.length;
  const CAP = 100;
  const capped = items.length > CAP;
  if (capped) items = items.slice(0, CAP);

  document.getElementById('countLine').textContent = capped
    ? ('نمایشِ ' + CAP + ' از ' + totalCount + ' گیفت — بقیه رو با 🎛 فیلتر پیدا کن')
    : (totalCount + ' گیفت');
  if (!items.length) { el.innerHTML = '<div class="empty">چیزی پیدا نشد.</div>'; return; }

  el.innerHTML = '';
  items.forEach(function (it) {
    const { base, num } = splitName(it.nft_name);
    const card = document.createElement('div');
    card.className = 'gcard';
    card.onclick = function () { openSheet(it._idx); };

    const imgBox = makeImgBox(it.nft_name, 'gimg-wrap');
    const badge = document.createElement('div'); badge.className = 'gbadge'; badge.textContent = '🧺';
    const days = document.createElement('div'); days.className = 'gdays';
    days.textContent = 'روز: ' + it.min_days + ' – ' + it.max_days;
    imgBox.appendChild(badge); imgBox.appendChild(days);

    const info = document.createElement('div'); info.className = 'ginfo';
    const nameRow = document.createElement('div'); nameRow.className = 'gname-row';
    const nm = document.createElement('div'); nm.className = 'gname'; nm.textContent = base;
    const nu = document.createElement('div'); nu.className = 'gnum'; nu.textContent = num;
    nameRow.appendChild(nm); nameRow.appendChild(nu);

    const prices = document.createElement('div'); prices.className = 'gprices';
    const perDay = document.createElement('div');
    perDay.innerHTML = '<div class="lbl">هر روز</div><div class="val"><span class="ic">👛</span>' + fmt(it.price_day) + '</div>';
    const minP = document.createElement('div');
    minP.innerHTML = '<div class="lbl">حداقل</div><div class="val"><span class="ic">👛</span>' + fmt(it.price_day * it.min_days) + '</div>';
    prices.appendChild(perDay); prices.appendChild(minP);

    info.appendChild(nameRow); info.appendChild(prices);

    card.appendChild(imgBox); card.appendChild(info);
    el.appendChild(card);
  });
}

let lastMe = null;
async function loadBalance() {
  const res = await call('me');
  if (res.ok) lastMe = res;
  const el = document.getElementById('balancePill');
  el.textContent = res.ok ? '👛 ' + fmt(res.balance) : '👛 —';
}

// ── بات‌شیتِ حساب من ──
async function openAccountSheet() {
  const res = await call('me');
  if (res.ok) lastMe = res;
  const u = (tg && tg.initDataUnsafe && tg.initDataUnsafe.user) || {};
  const body = document.getElementById('sheetBody');
  body.innerHTML = '';

  const head = document.createElement('div');
  head.style.cssText = 'display:flex;align-items:center;gap:12px;margin-bottom:14px';
  const avaWrap = document.createElement('div');
  avaWrap.style.cssText = 'width:52px;height:52px;border-radius:50%;overflow:hidden;flex:none;' +
    'background:var(--acc-dim);display:flex;align-items:center;justify-content:center;font-size:22px';
  if (u.photo_url) {
    const img = document.createElement('img');
    img.src = u.photo_url; img.style.cssText = 'width:100%;height:100%;object-fit:cover';
    avaWrap.textContent = ''; avaWrap.appendChild(img);
  } else { avaWrap.textContent = '👤'; }
  const nameBox = document.createElement('div');
  const nm = document.createElement('div'); nm.style.cssText = 'font-weight:700;font-size:15px';
  nm.textContent = [u.first_name, u.last_name].filter(Boolean).join(' ') || 'حساب من';
  const un = document.createElement('div'); un.className = 'dim';
  un.textContent = u.username ? '@' + u.username : '';
  nameBox.appendChild(nm); nameBox.appendChild(un);
  head.appendChild(avaWrap); head.appendChild(nameBox);

  const balBox = document.createElement('div');
  balBox.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:14px 16px;' +
    'border-radius:16px;background:var(--acc-dim);border:1px solid rgba(79,163,255,.3);margin-bottom:10px';
  const balLbl = document.createElement('div'); balLbl.className = 'dim'; balLbl.textContent = 'موجودیِ کیف‌پول';
  const balVal = document.createElement('div'); balVal.style.cssText = 'font-weight:800;font-size:17px;color:var(--acc)';
  balVal.textContent = '👛 ' + fmt(res.ok ? res.balance : 0) + ' تومان';
  balBox.appendChild(balLbl); balBox.appendChild(balVal);

  const rentBox = document.createElement('div'); rentBox.className = 'dim';
  const activeCount = res.ok ? (res.rentals || []).filter(function (r) { return r.status === 'active'; }).length : 0;
  rentBox.textContent = '🎁 ' + activeCount + ' گیفتِ فعال';
  rentBox.style.margin = '2px 2px 10px';

  const note = document.createElement('div'); note.className = 'dim';
  note.style.cssText = 'text-align:center;margin-top:8px;font-size:11.5px';
  note.textContent = 'برای شارژِ کیف‌پول از منوی اصلیِ ربات استفاده کنید.';

  body.appendChild(head); body.appendChild(balBox); body.appendChild(rentBox); body.appendChild(note);
  document.getElementById('sheetBg').classList.add('show');
  document.getElementById('sheet').classList.add('open');
}

// ── پنلِ فیلتر — تاشو، درونِ صفحه (نه بات‌شیت) ──
let openAccKey = null;
function toggleFilterPanel() {
  const p = document.getElementById('filterPanel');
  const willOpen = p.style.display === 'none';
  p.style.display = willOpen ? '' : 'none';
  if (willOpen) renderFilterPanel();
}

function accRow(label, key, bodyBuilder) {
  const row = document.createElement('div'); row.className = 'acc-row';
  if (openAccKey === key) row.classList.add('open');
  const head = document.createElement('div'); head.className = 'acc-head';
  const t = document.createElement('div'); t.className = 't'; t.textContent = label;
  const r = document.createElement('div'); r.className = 'r';
  const hasVal = key === 'price' ? !!activeFilters.maxPrice : !!activeFilters[key];
  if (hasVal) {
    const clr = document.createElement('span'); clr.className = 'acc-clear'; clr.textContent = 'پاک‌کردن';
    clr.onclick = function (e) {
      e.stopPropagation();
      if (key === 'price') activeFilters.maxPrice = null; else activeFilters[key] = null;
      renderGrid(); renderFilterPanel();
    };
    r.appendChild(clr);
  }
  const chev = document.createElement('span'); chev.className = 'acc-chev'; chev.textContent = '▾';
  r.appendChild(chev);
  head.appendChild(t); head.appendChild(r);
  head.onclick = function () { openAccKey = (openAccKey === key ? null : key); renderFilterPanel(); };

  const bodyEl = document.createElement('div'); bodyEl.className = 'acc-body';
  bodyBuilder(bodyEl);

  row.appendChild(head); row.appendChild(bodyEl);
  return row;
}

/** حدسِ رنگِ یه اسمِ پس‌زمینه (مثلِ «Aquamarine»، «Burnt Sienna») از روی کلمه‌کلیدی داخلِ اسمش */
function guessColor(name) {
  const n = String(name || '').toLowerCase();
  const table = [
    [/black/, '#1a1a1a'], [/white|ivory|pearl/, '#eee'], [/grey|gray|silver|steel|battleship/, '#9aa0a6'],
    [/gold|amber|honey|mustard|caramel|butter|sand|straw/, '#e8b74a'],
    [/orange|sienna|rust|copper|bronze|tangerine|pumpkin/, '#e07a3f'],
    [/red|carmine|ruby|crimson|scarlet|cherry/, '#d64545'],
    [/burgundy|maroon|wine/, '#7a2b3a'],
    [/pink|rose|blush|magenta|fuchsia/, '#e57bb0'],
    [/purple|violet|lavender|orchid|plum/, '#8a5cd6'],
    [/blue|azure|navy|sapphire|cobalt|cyan|sky/, '#4a8fe0'],
    [/teal|aquamarine|turquoise|mint|jade|emerald/, '#3fc7ab'],
    [/green|olive|camo|forest|lime|pine/, '#5fb85a'],
    [/brown|coffee|cappuccino|choco|walnut|umber|tan|beige/, '#8a6b4d'],
    [/yellow|lemon|banana|canary/, '#e8d84a'],
  ];
  for (const [re, color] of table) if (re.test(n)) return color;
  return '#5C6B64';
}

function chipGroup(container, trait, key) {
  const vals = uniqueAttrValues(trait);
  if (!vals.length) { container.innerHTML = '<div class="dim">موردی نیست.</div>'; return; }

  // پس‌زمینه‌ها لیستِ عمودیِ ردیفی‌اند (سطرِ رنگ + اسم)، بقیه چیپِ کنارِ‌هم
  if (trait === 'Backdrop') {
    const list = document.createElement('div'); list.style.cssText = 'display:flex;flex-direction:column;gap:4px';
    vals.forEach(function (val) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:9px;padding:8px 6px;border-radius:10px;cursor:pointer';
      if (activeFilters[key] === val) row.style.background = 'var(--acc-dim)';
      const sw = document.createElement('span');
      sw.style.cssText = 'width:16px;height:16px;border-radius:5px;flex:none;background:' + guessColor(val);
      const lbl = document.createElement('span'); lbl.style.fontSize = '12px'; lbl.textContent = val;
      if (activeFilters[key] === val) lbl.style.color = 'var(--acc)';
      row.appendChild(sw); row.appendChild(lbl);
      row.onclick = function () {
        activeFilters[key] = activeFilters[key] === val ? null : val;
        renderGrid(); renderFilterPanel();
      };
      list.appendChild(row);
    });
    container.appendChild(list);
    return;
  }

  const wrap = document.createElement('div'); wrap.className = 'acc-chips';
  vals.forEach(function (val) {
    const chip = document.createElement('span'); chip.className = 'pill'; chip.style.cursor = 'pointer';
    if (activeFilters[key] === val) chip.classList.add('active');
    chip.textContent = val;
    chip.onclick = function () {
      activeFilters[key] = activeFilters[key] === val ? null : val;
      renderGrid(); renderFilterPanel();
    };
    wrap.appendChild(chip);
  });
  container.appendChild(wrap);
}

function renderFilterPanel() {
  const panel = document.getElementById('filterPanel');
  if (panel.style.display === 'none') return;
  panel.innerHTML = '';

  panel.appendChild(accRow('قیمت', 'price', function (bodyEl) {
    const priceMax = Math.ceil(Math.max.apply(null, currentItems.map(function (it) { return it.price_day; }).concat([0])));
    if (priceMax <= 0) { bodyEl.innerHTML = '<div class="dim">موردی نیست.</div>'; return; }
    const lbl = document.createElement('div'); lbl.className = 'dim'; lbl.style.marginBottom = '6px';
    const slider = document.createElement('input'); slider.type = 'range';
    slider.min = 0; slider.max = priceMax; slider.value = activeFilters.maxPrice || priceMax;
    lbl.textContent = 'حداکثر: ' + fmt(slider.value) + ' تومان';
    slider.oninput = function () { lbl.textContent = 'حداکثر: ' + fmt(slider.value) + ' تومان'; };
    slider.onchange = function () {
      activeFilters.maxPrice = parseInt(slider.value, 10) < priceMax ? parseInt(slider.value, 10) : null;
      renderGrid();
    };
    bodyEl.appendChild(lbl); bodyEl.appendChild(slider);
  }));
  panel.appendChild(accRow('پس‌زمینه', 'backdrop', function (b) { chipGroup(b, 'Backdrop', 'backdrop'); }));

  const foot = document.createElement('div'); foot.className = 'filter-foot';
  const clearBtn = document.createElement('button'); clearBtn.className = 'btn-ghost'; clearBtn.textContent = 'پاک‌کردنِ همه';
  clearBtn.onclick = function () { activeFilters = { backdrop: null, maxPrice: null }; renderGrid(); renderFilterPanel(); };
  const closeBtn = document.createElement('button'); closeBtn.className = 'btn-main'; closeBtn.style.marginTop = '0';
  closeBtn.textContent = 'بستن';
  closeBtn.onclick = function () { toggleFilterPanel(); };
  foot.appendChild(clearBtn); foot.appendChild(closeBtn);
  panel.appendChild(foot);
}

// ── بات‌شیتِ جزئیات + انتخابِ مدت ──
function openSheet(idx) {
  const it = currentItems[idx];
  const { base, num } = splitName(it.nft_name);
  const body = document.getElementById('sheetBody');
  body.innerHTML = '';

  const imgBox = makeImgBox(it.nft_name, 'sheet-img');
  const title = document.createElement('h3'); title.textContent = base;
  const sub = document.createElement('div'); sub.className = 'dim'; sub.textContent = num + '  ·  ' + fmt(it.price_day) + ' تومان / روز';

  body.appendChild(imgBox); body.appendChild(title); body.appendChild(sub);

  const days = document.createElement('input');
  days.type = 'range'; days.min = it.min_days; days.max = it.max_days; days.value = it.min_days;
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-top:8px';
  const dayLabel = document.createElement('span'); dayLabel.className = 'dim';
  const totalLabel = document.createElement('span'); totalLabel.style.cssText = 'font-weight:700;color:var(--acc)';
  const updateTotal = () => { totalLabel.textContent = fmt(it.price_day * days.value) + ' تومان'; dayLabel.textContent = days.value + ' روز'; };
  days.oninput = updateTotal;
  row.appendChild(dayLabel); row.appendChild(totalLabel);

  const btn = document.createElement('button');
  btn.className = 'btn-main'; btn.textContent = 'اجاره کن';
  btn.onclick = function () { orderGift(it.nft_address, parseInt(days.value, 10), btn); };

  if (it.max_days > it.min_days) { body.appendChild(days); body.appendChild(row); }
  else { totalLabel.textContent = fmt(it.price_day * it.min_days) + ' تومان'; row.appendChild(totalLabel); body.appendChild(row); }
  body.appendChild(btn);
  updateTotal();

  document.getElementById('sheetBg').classList.add('show');
  document.getElementById('sheet').classList.add('open');
}
function closeSheet() {
  document.getElementById('sheetBg').classList.remove('show');
  document.getElementById('sheet').classList.remove('open');
}

async function orderGift(nftAddress, days, btn) {
  btn.disabled = true; btn.textContent = 'در حال پرداخت…';
  const res = await call('order', { nft_address: nftAddress, days: days });
  btn.disabled = false; btn.textContent = 'اجاره کن';
  if (!res.ok) { toast(res.message || 'پرداخت انجام نشد'); return; }
  closeSheet();
  toast('پرداخت شد — حالا کیف‌پولت رو وصل کن');
  connectWallet(res.rental_id);
}

// ── TonConnect: اتصالِ کیف‌پولِ خودِ مشتری ──
// ⚠️ این بخش روی یک دستگاهِ واقعی با یک TonKeeperِ واقعی تست نشده؛ بعد از
//    هر تغییر باید واقعاً امتحان بشه — من از اینجا نمی‌تونم تستش کنم.
//
// عمداً فقط TonKeeper هدف‌گیری می‌شود، نه اولین کیف‌پولِ فهرست (که ممکن
// بود «کیف‌پولِ تلگرام» باشد) — چون کیف‌پولِ تلگرام روی حساب‌های ایرانی
// معمولاً کار نمی‌کند.
let connector = null;
let statusUnsub = null;
function getConnector() {
  if (connector) return connector;
  connector = new TonConnectSDK.TonConnect({ manifestUrl: MANIFEST });
  return connector;
}

/**
 * ⚠️ خودِ صفحه داخلِ WebViewِ تلگرامه — اگه لینکِ TonConnect رو با
 * tg.openLink باز کنیم، خودِ تلگرام قاپش می‌زنه و می‌بره تو صفحه‌ی
 * Fragmentِ داخلی‌اش (که manifest ما رو نمی‌شناسه و خطا می‌ده). راه‌حل:
 * یه تگِ <a> معمولی که مستقیم می‌ره سراغِ TonKeeper (showConnectLink پایین).
 */
async function connectWallet(rentalId) {
  try {
    const tc = getConnector();
    // ⚠️ اگه از تلاشِ قبلی یه اتصالِ نیمه‌کاره مونده باشه، connect()ِ دوباره
    // خطا می‌ده («اتصالِ کیف‌پول شکست خورد» بدونِ هیچ جزئیاتی) — قبل از هر
    // تلاشِ تازه، وضعیتِ قبلی رو کاملاً پاک می‌کنیم.
    if (statusUnsub) { statusUnsub(); statusUnsub = null; }
    if (tc.connected) { try { tc.disconnect(); } catch (e2) {} }
    const wallets = await tc.getWallets();
    const tonkeeper = wallets.find(function (w) { return (w.appName || '').toLowerCase() === 'tonkeeper'; });
    if (!tonkeeper) { toast('TonKeeper پیدا نشد — SDK آپدیت است؟'); return; }

    const link = await tc.connect({ universalLink: tonkeeper.universalLink, bridgeUrl: tonkeeper.bridgeUrl });
    if (typeof link === 'string') showConnectLink(link);

    if (statusUnsub) { statusUnsub(); statusUnsub = null; }
    statusUnsub = tc.onStatusChange(async function (walletInfo) {
      if (!walletInfo) return;
      if (statusUnsub) { statusUnsub(); statusUnsub = null; }
      // ⚠️ فیلدِ marketapp دقیقاً tonconnect_url نام‌گذاری شده — یعنی خودِ
      // لینکِ TonConnect را می‌خواهد، نه آدرسِ کیف‌پول را؛ فرستادنِ آدرس
      // («EQ...») همیشه «Invalid tonconnect URL» می‌داد چون اصلاً URL نیست.
      const r = await call('connect', { rental_id: rentalId, tonconnect_url: link });
      if (r.ok) { toast('گیفت وصل شد ✅'); closeSheet(); showTab('mine'); }
      else toast(r.message || 'اتصال ناموفق بود');
    });
  } catch (e) {
    // ⚠️ عمداً متنِ خودِ خطا رو نشون می‌دیم (نه فقط یه پیامِ کلی) — چون
    // بدونِ این، جایی که واقعاً خراب می‌شه (getWallets؟ connect؟ کدوم
    // کیف‌پول؟) هیچ‌وقت از رو گوشیِ کاربر معلوم نمی‌شه.
    const msg = (e && e.message) ? String(e.message) : String(e);
    toast('اتصالِ کیف‌پول شکست خورد: ' + msg);
    connector = null;
  }
}

/**
 * روی همین گوشی، بازکردنِ لینک با یه تگِ <a> معمولی — نه tg.openLink —
 * چون خودِ کلیکِ کاربر رو یه لینکِ ساده، برخلافِ tg.openLink، توسطِ
 * تلگرام قاپیده نمی‌شه و مستقیم می‌ره سراغِ TonKeeper. برای گوشیِ دومی هم
 * که کیف‌پول روش نصبه، همون متن رو کپی/بفرست.
 */
function showConnectLink(link) {
  const body = document.getElementById('sheetBody');
  body.innerHTML = '';
  const title = document.createElement('h3'); title.textContent = '👛 اتصالِ TonKeeper';
  const sub = document.createElement('div'); sub.className = 'dim'; sub.style.marginBottom = '18px';
  sub.textContent = 'اگه TonKeeper رو همین گوشی نصبه، دکمه‌ی زیر رو بزن.';

  const a = document.createElement('a');
  a.href = link; a.target = '_blank'; a.rel = 'noopener';
  a.className = 'btn-tonkeeper';
  a.textContent = '🔗 بازکردنِ TonKeeper';

  body.appendChild(title); body.appendChild(sub); body.appendChild(a);

  document.getElementById('sheetBg').classList.add('show');
  document.getElementById('sheet').classList.add('open');
}

async function loadMine() {
  const el = document.getElementById('viewMine');
  el.innerHTML = '<div class="empty">در حال بارگذاری…</div>';
  const res = await call('me');
  if (!res.ok || !res.rentals || !res.rentals.length) {
    el.innerHTML = '<div class="empty">هنوز گیفتی اجاره نکردی.</div>';
    return;
  }
  el.innerHTML = '';
  const labels = { paying: ['در حالِ پرداخت', 'wait'], connect_wait: ['منتظرِ اتصالِ کیف‌پول', 'wait'],
                   active: ['فعال', 'active'], failed: ['ناموفق', 'failed'], expired: ['تمام‌شده', ''] };
  res.rentals.forEach(function (r) {
    const { base, num } = splitName(r.nft_name);
    const card = document.createElement('div'); card.className = 'rcard';
    const thumb = makeImgBox(r.nft_name, 'thumb');
    const bodyEl = document.createElement('div'); bodyEl.className = 'body';
    const title = document.createElement('h4'); title.textContent = base;
    const lbl = labels[r.status] || [r.status, ''];
    const pill = document.createElement('span'); pill.className = 'pill ' + lbl[1]; pill.textContent = lbl[0];
    const sub = document.createElement('div'); sub.className = 'dim';
    sub.textContent = num + (num ? '  ·  ' : '') + fmt(r.toman_total) + ' تومان';
    bodyEl.appendChild(title); bodyEl.appendChild(pill); bodyEl.appendChild(sub);
    if (r.status === 'connect_wait') {
      const btn = document.createElement('button'); btn.className = 'btn-main btn-mini'; btn.textContent = 'اتصالِ کیف‌پول';
      btn.onclick = function () { connectWallet(r.id); };
      bodyEl.appendChild(btn);
    }
    card.appendChild(thumb); card.appendChild(bodyEl);
    el.appendChild(card);
  });
}

loadList();
loadBalance();
</script>
</body>
</html>
HTML;
}
