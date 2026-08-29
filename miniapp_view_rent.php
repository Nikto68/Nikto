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
 * ⚠️ پیش‌نیازی که باید روی سرور جدا اضافه شود: یک فایلِ
 *    tonconnect-manifest.json (اسم/آیکون/آدرسِ ربات) که TonConnect
 *    موقعِ اتصال آن را می‌خواند. آدرسش پایین با MANIFEST_URL مشخص شده —
 *    باید واقعاً روی همین دامنه ساخته و آپلود شود.
 */

function grViewRent() {
    // ⚠️ base_url خودش کاملِ مسیرِ bot_master_membership.php است
    // (مثلا https://DOMAIN/bot_master_membership.php) — پس برای API فقط
    // کوئری‌استرینگ اضافه می‌شود، ولی مانیفست باید روی ریشه‌ی دامنه باشد،
    // نه کنارِ خودِ فایلِ php.
    $base = trim((string)(maCfg()['base_url'] ?? ''));
    $root = '';
    $p = @parse_url($base);
    if (!empty($p['scheme']) && !empty($p['host']))
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');

    $manifest = $root !== '' ? $root . '/tonconnect-manifest.json' : '';
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
  /* سفید · سبز · مشکی — همه کم‌رنگ، هیچ‌جا اشباع بالا نیست */
  --bg:#07090A; --bg2:#0C1210;
  --glass:rgba(255,255,255,.045); --glass2:rgba(255,255,255,.075);
  --hair:rgba(255,255,255,.09);
  --ink:#F3F6F4; --dim:#8FA098; --dim2:#5C6B64;
  --acc:#7FD9A6;            /* سبزِ کم‌جان — نه نئون */
  --acc-dim:rgba(127,217,166,.16);
  --ok:#7FD9A6; --bad:#E28B93;
  --ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Vazirmatn,Tahoma,sans-serif;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;color:var(--ink);font-family:var(--ui);padding:16px;padding-bottom:96px;
  background:
    radial-gradient(120% 60% at 15% -10%, rgba(127,217,166,.10), transparent 60%),
    radial-gradient(90% 50% at 100% 0%, rgba(255,255,255,.05), transparent 55%),
    var(--bg);
  background-attachment:fixed;
}
h1{font-size:17px;margin:4px 0 16px;display:flex;align-items:center;gap:8px;font-weight:700}
h1 .ic{display:inline-block;animation:float 3.2s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-3px) rotate(-6deg)}}
@keyframes glow{0%,100%{opacity:.55}50%{opacity:1}}
@keyframes pop{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}

.tabs{display:flex;gap:8px;margin-bottom:16px}
.tab{
  flex:1;text-align:center;padding:10px;border-radius:14px;color:var(--dim);font-size:13px;
  background:var(--glass);border:1px solid var(--hair);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  transition:.2s;
}
.tab.on{background:var(--acc-dim);color:var(--acc);border-color:rgba(127,217,166,.35)}

/* ── کارتِ شیشه‌ای ── */
.card{
  position:relative;overflow:hidden;
  background:linear-gradient(160deg, var(--glass2), var(--glass));
  border:1px solid var(--hair);border-radius:20px;padding:16px;margin-bottom:12px;
  backdrop-filter:blur(18px) saturate(140%);-webkit-backdrop-filter:blur(18px) saturate(140%);
  box-shadow:0 8px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.06);
  animation:pop .35s ease both;
}
.card::before{
  content:'';position:absolute;inset:-40% -40% auto auto;width:60%;height:60%;
  background:radial-gradient(circle, rgba(127,217,166,.10), transparent 70%);
  pointer-events:none;
}
.card-head{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.gicon{
  width:38px;height:38px;border-radius:12px;flex:none;display:flex;align-items:center;justify-content:center;
  font-size:19px;background:var(--acc-dim);border:1px solid rgba(127,217,166,.25);
  animation:glow 2.6s ease-in-out infinite;
}
.card h3{margin:0;font-size:15px;font-weight:700}
.row{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:8px}
.price{font-weight:700;color:var(--acc)}
.dim{color:var(--dim);font-size:12px}
input[type=range]{width:100%;accent-color:var(--acc)}
button{border:0;border-radius:14px;padding:12px 14px;font-family:inherit;font-size:14px;cursor:pointer;
  transition:transform .12s, opacity .12s}
button:active{transform:scale(.97)}
.btn-main{
  width:100%;margin-top:10px;color:#08130E;font-weight:700;
  background:linear-gradient(135deg, var(--acc), #C9F5DC);
  box-shadow:0 6px 20px rgba(127,217,166,.25);
}
.btn-main:disabled{opacity:.45;box-shadow:none}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;
  background:var(--glass);border:1px solid var(--hair)}
.pill.active{background:var(--acc-dim);color:var(--ok);border-color:rgba(127,217,166,.35)}
.pill.wait{background:rgba(255,255,255,.09);color:var(--ink)}
.pill.failed{background:rgba(226,139,147,.16);color:var(--bad)}
.empty{text-align:center;color:var(--dim);padding:48px 10px;font-size:13px}
.toast{position:fixed;bottom:16px;left:16px;right:16px;background:var(--glass2);border:1px solid var(--hair);
  border-radius:14px;padding:13px;font-size:13px;text-align:center;display:none;z-index:9;
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);animation:pop .25s ease both}
</style>
</head>
<body>
<h1><span class="ic">🎁</span> اجاره‌ی گیفت</h1>
<div class="tabs">
  <div class="tab on" id="tabList" onclick="showTab('list')">لیست گیفت‌ها</div>
  <div class="tab" id="tabMine" onclick="showTab('mine')">اجاره‌های من</div>
</div>
<div id="viewList"></div>
<div id="viewMine" style="display:none"></div>
<div class="toast" id="toast"></div>

<script>
const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
if (tg) { tg.ready(); tg.expand(); }
const API = __API__;
const MANIFEST = __MANIFEST__;
const initData = tg ? tg.initData : '';

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
  document.getElementById('tabList').classList.toggle('on', which === 'list');
  document.getElementById('tabMine').classList.toggle('on', which === 'mine');
  document.getElementById('viewList').style.display = which === 'list' ? '' : 'none';
  document.getElementById('viewMine').style.display = which === 'mine' ? '' : 'none';
  if (which === 'mine') loadMine();
}

function fmt(n) { return Math.round(n).toLocaleString('fa-IR'); }

async function loadList() {
  const el = document.getElementById('viewList');
  el.innerHTML = '<div class="empty">در حال بارگذاری…</div>';
  const res = await call('list');
  if (!res.ok || !res.items || !res.items.length) {
    el.innerHTML = '<div class="empty">فعلاً گیفتی برای اجاره موجود نیست.</div>';
    return;
  }
  el.innerHTML = '';
  res.items.forEach(function (it) {
    const card = document.createElement('div');
    card.className = 'card';
    const days = document.createElement('input');
    days.type = 'range'; days.min = it.min_days; days.max = it.max_days; days.value = it.min_days;
    const dayLabel = document.createElement('span');
    dayLabel.textContent = it.min_days + ' روز';
    const totalLabel = document.createElement('span');
    totalLabel.className = 'price';
    const updateTotal = () => { totalLabel.textContent = fmt(it.price_day * days.value) + ' تومان'; dayLabel.textContent = days.value + ' روز'; };
    days.oninput = updateTotal;

    const icon = document.createElement('div'); icon.className = 'gicon'; icon.textContent = '🎁';
    icon.style.animationDelay = (Math.random() * 1.2).toFixed(2) + 's';
    const title = document.createElement('h3'); title.textContent = it.nft_name;
    const head = document.createElement('div'); head.className = 'card-head';
    head.appendChild(icon); head.appendChild(title);

    const sub = document.createElement('div'); sub.className = 'dim';
    sub.textContent = fmt(it.price_day) + ' تومان / روز';

    const rangeRow = document.createElement('div'); rangeRow.appendChild(days);
    const infoRow = document.createElement('div'); infoRow.className = 'row';
    infoRow.appendChild(dayLabel); infoRow.appendChild(totalLabel);

    const btn = document.createElement('button');
    btn.className = 'btn-main'; btn.textContent = 'اجاره کن';
    btn.onclick = function () { orderGift(it.nft_address, parseInt(days.value, 10), btn); };

    card.appendChild(head); card.appendChild(sub);
    if (it.max_days > it.min_days) { card.appendChild(rangeRow); card.appendChild(infoRow); }
    else { totalLabel.textContent = fmt(it.price_day * it.min_days) + ' تومان'; card.appendChild(totalLabel); }
    card.appendChild(btn);
    el.appendChild(card);
    updateTotal();
  });
}

async function orderGift(nftAddress, days, btn) {
  btn.disabled = true; btn.textContent = 'در حال پرداخت…';
  const res = await call('order', { nft_address: nftAddress, days: days });
  btn.disabled = false; btn.textContent = 'اجاره کن';
  if (!res.ok) { toast(res.message || 'پرداخت انجام نشد'); return; }
  toast('پرداخت شد — حالا کیف‌پولت رو وصل کن');
  connectWallet(res.rental_id);
}

// ── TonConnect: اتصالِ کیف‌پولِ خودِ مشتری ──
// ⚠️ این بخش روی یک دستگاهِ واقعی با یک کیف‌پولِ TON واقعی تست نشده؛
//    قبل از رفتنِ به تولید حتماً با Tonkeeper/MyTonWallet واقعی امتحان شود.
let connector = null;
function getConnector() {
  if (connector) return connector;
  connector = new TonConnectSDK.TonConnect({ manifestUrl: MANIFEST });
  return connector;
}

async function connectWallet(rentalId) {
  try {
    const tc = getConnector();
    const wallets = await tc.getWallets();
    const wallet = wallets[0];
    const url = await tc.connect({ jsBridgeKey: wallet.jsBridgeKey } || wallets[0].universalLink ? wallets[0] : wallets[0]);
    if (typeof url === 'string' && tg && tg.openLink) tg.openLink(url);

    tc.onStatusChange(async function (walletInfo) {
      if (!walletInfo) return;
      const r = await call('connect', { rental_id: rentalId, tonconnect_url: JSON.stringify(walletInfo) });
      if (r.ok) { toast('گیفت وصل شد ✅'); showTab('mine'); }
      else toast(r.message || 'اتصال ناموفق بود');
    });
  } catch (e) {
    toast('اتصالِ کیف‌پول شکست خورد — دوباره امتحان کنید');
  }
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
    const card = document.createElement('div'); card.className = 'card';
    const icon = document.createElement('div'); icon.className = 'gicon'; icon.textContent = '🎁';
    const title = document.createElement('h3'); title.textContent = r.nft_name;
    const head = document.createElement('div'); head.className = 'card-head';
    head.appendChild(icon); head.appendChild(title);
    const lbl = labels[r.status] || [r.status, ''];
    const pill = document.createElement('span'); pill.className = 'pill ' + lbl[1]; pill.textContent = lbl[0];
    const sub = document.createElement('div'); sub.className = 'dim';
    sub.textContent = fmt(r.toman_total) + ' تومان';
    card.appendChild(head); card.appendChild(pill); card.appendChild(sub);
    if (r.status === 'connect_wait') {
      const btn = document.createElement('button'); btn.className = 'btn-main'; btn.textContent = 'اتصالِ کیف‌پول';
      btn.onclick = function () { connectWallet(r.id); };
      card.appendChild(btn);
    }
    el.appendChild(card);
  });
}

loadList();
</script>
</body>
</html>
HTML;
}
