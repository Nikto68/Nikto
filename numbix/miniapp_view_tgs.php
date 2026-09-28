<?php

function svTplTg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#050B18">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
__FONT__
<style>
:root{
  --bg:#050B18;--bg2:#081427;--card:#0B1A30;--card2:#0F2340;--line:rgba(94,234,212,.12);--line2:rgba(42,171,238,.28);
  --ink:#EAF6FF;--dim:#8EA6C2;--dim2:#5E7896;
  --tg:#2AABEE;--tg2:#229ED9;--cy:#5EEAD4;--vi:#A78BFA;--gold:#FCD34D;--red:#FB7185;--ok:#34D399;
  --grad:linear-gradient(120deg,#2AABEE 0%,#5EEAD4 100%);
  --r:22px;--safe:env(safe-area-inset-bottom,0px);color-scheme:dark
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg);color:var(--ink);min-height:100%}
body{font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:13px;line-height:1.7;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
input{font-family:inherit;-webkit-user-select:text;user-select:text}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
svg{display:block}
.hid{display:none!important}
.ltr{direction:ltr;unicode-bidi:isolate}

.aur{position:fixed;inset:0;z-index:0;pointer-events:none;background:
  radial-gradient(80vw 38vh at 90% -6%,rgba(42,171,238,.32),transparent 70%),
  radial-gradient(70vw 34vh at -10% 18%,rgba(94,234,212,.16),transparent 70%),
  radial-gradient(90vw 40vh at 50% 108%,rgba(167,139,250,.16),transparent 70%),
  linear-gradient(180deg,#050B18,#061229 60%,#050B18)}
.aur:after{content:"";position:absolute;inset:0;opacity:.35;
  background-image:radial-gradient(rgba(142,166,194,.35) 1px,transparent 1.2px);background-size:22px 22px;
  -webkit-mask-image:linear-gradient(180deg,#000,transparent 55%);mask-image:linear-gradient(180deg,#000,transparent 55%)}

.app{position:relative;z-index:1;max-width:480px;margin:0 auto;padding:0 16px calc(28px + var(--safe))}
.top{position:sticky;top:0;z-index:20;margin:0 -16px;padding:10px 16px 8px;
  background:linear-gradient(180deg,rgba(5,11,24,.97) 70%,rgba(5,11,24,0))}
.bar{display:flex;align-items:center;gap:10px}
.me{display:flex;align-items:center;gap:9px;flex:1;min-width:0}
.av{width:38px;height:38px;border-radius:13px;display:grid;place-items:center;flex:0 0 auto;overflow:hidden;
  background:var(--grad);color:#04121F;font-weight:900;font-size:15px}
.av img{width:100%;height:100%;object-fit:cover}
.me b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.me small{display:block;font-size:10px;color:var(--dim)}
.bal{display:flex;align-items:center;gap:6px;height:36px;padding:0 6px 0 12px;border-radius:12px;
  background:rgba(42,171,238,.12);border:1px solid var(--line2);font-weight:900;font-size:12px}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.bal i{width:24px;height:24px;border-radius:8px;display:grid;place-items:center;background:var(--grad);color:#04121F;font-style:normal}
.bal i svg{width:14px;height:14px}
.ib{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.03)}
.ib svg{width:18px;height:18px;color:var(--cy)}

.tabs{position:relative;display:grid;grid-template-columns:repeat(4,1fr);margin-top:10px;padding:4px;border-radius:16px;
  background:rgba(11,26,48,.9);border:1px solid var(--line)}
.tabs button{position:relative;z-index:1;height:38px;border-radius:12px;font-size:11.5px;font-weight:800;color:var(--dim);
  display:flex;align-items:center;justify-content:center;gap:5px;transition:color .2s}
.tabs button svg{width:16px;height:16px}
.tabs button.on{color:#04121F}
.tabs .ind{position:absolute;z-index:0;top:4px;bottom:4px;width:calc((100% - 8px)/4);right:4px;border-radius:12px;
  background:var(--grad);box-shadow:0 6px 18px -6px rgba(42,171,238,.8);transition:transform .32s cubic-bezier(.3,.9,.3,1)}
.tabs .bd{position:absolute;top:3px;left:8px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--gold);
  color:#1a1300;font-size:9.5px;font-weight:900;display:none;place-items:center}
.tabs .bd.on{display:grid}

.pg{display:none;padding-top:6px}
.pg.on{display:block;animation:pin .3s cubic-bezier(.2,.8,.2,1)}
@keyframes pin{from{opacity:0;transform:translate3d(0,10px,0)}to{opacity:1;transform:none}}

.hero{position:relative;overflow:hidden;border-radius:26px;padding:20px 18px 18px;margin-top:6px;
  background:linear-gradient(145deg,#0E2A4D 0%,#0A1B33 55%,#0B1A30 100%);border:1px solid var(--line2)}
.hero:before{content:"";position:absolute;width:260px;height:260px;left:-80px;top:-120px;border-radius:50%;
  background:radial-gradient(closest-side,rgba(42,171,238,.45),transparent)}
.hero .pl{position:absolute;left:10px;top:14px;width:118px;height:118px}
.hero .orb{position:absolute;left:14px;top:18px;width:110px;height:110px;border-radius:50%;border:1.5px dashed rgba(94,234,212,.35);
  animation:spin 18s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.hero .pl svg{width:100%;height:100%;filter:drop-shadow(0 14px 18px rgba(42,171,238,.55));animation:fly 4s ease-in-out infinite}
@keyframes fly{0%,100%{transform:translate3d(0,0,0) rotate(-4deg)}50%{transform:translate3d(-6px,-8px,0) rotate(3deg)}}
.hero .tx{position:relative;max-width:62%}
.hero .kick{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;
  color:var(--cy);background:rgba(94,234,212,.1);border:1px solid rgba(94,234,212,.25)}
.hero .kick i{width:6px;height:6px;border-radius:50%;background:var(--cy);box-shadow:0 0 8px var(--cy)}
.hero h1{margin-top:10px;font-size:21px;font-weight:900;line-height:1.35}
.hero h1 span{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.hero p{margin-top:6px;font-size:11.5px;color:var(--dim)}
.hero .go{position:relative;margin-top:14px;height:44px;padding:0 18px;border-radius:14px;background:var(--grad);color:#04121F;
  font-weight:900;font-size:13px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 12px 24px -12px rgba(42,171,238,.9)}
.hero .go svg{width:16px;height:16px}
.kpis{position:relative;display:flex;gap:8px;margin-top:14px}
.kpis div{flex:1;padding:8px 10px;border-radius:14px;background:rgba(5,11,24,.55);border:1px solid var(--line)}
.kpis b{display:block;font-size:14px;font-weight:900}
.kpis small{font-size:9.5px;color:var(--dim)}

.hd{display:flex;align-items:center;gap:8px;margin:20px 2px 10px}
.hd h3{flex:1;font-size:14px;font-weight:900;display:flex;align-items:center;gap:8px}
.hd h3:before{content:"";width:4px;height:16px;border-radius:4px;background:var(--grad)}
.hd a,.hd button{font-size:11px;font-weight:800;color:var(--tg)}

.bento{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.bt{position:relative;overflow:hidden;text-align:right;border-radius:20px;padding:14px;min-height:112px;
  background:var(--card);border:1px solid var(--line);display:flex;flex-direction:column;justify-content:space-between}
.bt:first-child{grid-column:1/-1;min-height:96px;flex-direction:row;align-items:center;gap:14px;
  background:linear-gradient(120deg,rgba(42,171,238,.22),rgba(94,234,212,.08)),var(--card);border-color:var(--line2)}
.bt .ic{width:44px;height:44px;border-radius:15px;display:grid;place-items:center;background:rgba(42,171,238,.14);color:var(--tg);flex:0 0 auto}
.bt:first-child .ic{width:54px;height:54px;border-radius:18px;background:var(--grad);color:#04121F}
.bt .ic svg{width:22px;height:22px}
.bt b{display:block;font-size:13.5px;font-weight:900}
.bt small{display:block;font-size:10.5px;color:var(--dim)}
.bt .pr{font-size:11px;font-weight:800;color:var(--cy)}
.bt:active{transform:scale(.98)}
.bt:nth-child(3n+2) .ic{background:rgba(94,234,212,.12);color:var(--cy)}
.bt:nth-child(3n) .ic{background:rgba(167,139,250,.14);color:var(--vi)}

.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.steps div{padding:12px 10px;border-radius:18px;background:var(--card);border:1px solid var(--line);text-align:center}
.steps i{width:30px;height:30px;margin:0 auto 6px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-weight:900;
  background:rgba(42,171,238,.14);color:var(--tg);font-size:13px}
.steps b{display:block;font-size:11.5px;font-weight:800}
.steps small{font-size:9.5px;color:var(--dim)}

.chips{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;margin:0 -16px;padding:2px 16px 4px}
.chips::-webkit-scrollbar{display:none}
.chips button{flex:0 0 auto;height:34px;padding:0 13px;border-radius:11px;font-size:11.5px;font-weight:800;color:var(--dim);
  background:var(--card);border:1px solid var(--line);display:flex;align-items:center;gap:6px}
.chips button svg{width:14px;height:14px}
.chips button.on{color:#04121F;background:var(--grad);border-color:transparent}
.srch{display:flex;align-items:center;gap:8px;height:44px;margin-bottom:10px;padding:0 12px;border-radius:14px;background:var(--card);border:1px solid var(--line)}
.srch svg{width:17px;height:17px;color:var(--dim)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}

.list{display:grid;gap:9px;margin-top:10px}
.tk{position:relative;display:flex;align-items:stretch;border-radius:18px;background:var(--card);border:1px solid var(--line);
  overflow:hidden;text-align:right;width:100%}
.tk .m{flex:1;min-width:0;padding:12px 13px}
.tk .m b{display:block;font-size:12.5px;font-weight:800;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tk .m small{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.tk .m small span{font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:7px;background:rgba(142,166,194,.1);color:var(--dim)}
.tk .m small span.g{background:rgba(52,211,153,.12);color:var(--ok)}
.tk .p{position:relative;flex:0 0 96px;padding:10px 8px;display:flex;flex-direction:column;align-items:center;justify-content:center;
  background:linear-gradient(180deg,rgba(42,171,238,.14),rgba(94,234,212,.06));border-right:1.5px dashed rgba(94,234,212,.25)}
.tk .p:before,.tk .p:after{content:"";position:absolute;right:-8px;width:14px;height:14px;border-radius:50%;background:var(--bg)}
.tk .p:before{top:-7px}.tk .p:after{bottom:-7px}
.tk .p b{font-size:14px;font-weight:900;color:var(--cy)}
.tk .p small{font-size:9px;color:var(--dim)}
.tk:active{transform:scale(.985)}

.emp{text-align:center;padding:34px 18px;border-radius:22px;background:var(--card);border:1px dashed var(--line2);color:var(--dim)}
.emp svg{width:40px;height:40px;margin:0 auto 10px;color:var(--tg)}
.emp b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{height:74px;border-radius:18px;margin-bottom:9px;background:linear-gradient(90deg,var(--card) 30%,var(--card2) 50%,var(--card) 70%);
  background-size:300% 100%;animation:sk 1.3s linear infinite}
@keyframes sk{from{background-position:100% 0}to{background-position:-200% 0}}

.or{border-radius:20px;background:var(--card);border:1px solid var(--line);padding:13px;margin-bottom:10px}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h .ic{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;background:rgba(42,171,238,.14);color:var(--tg);flex:0 0 auto}
.or .h .ic svg{width:20px;height:20px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(42,171,238,.14);color:var(--tg)}
.pill.done{background:rgba(52,211,153,.14);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(252,211,77,.14);color:var(--gold)}
.pill.canceled,.pill.failed{background:rgba(251,113,133,.14);color:var(--red)}
.prog{height:7px;border-radius:7px;background:rgba(142,166,194,.14);margin:11px 0 7px;overflow:hidden}
.prog i{display:block;height:100%;border-radius:7px;background:var(--grad);transition:width .6s ease}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:var(--tg);direction:ltr;overflow:hidden}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}

.wal{position:relative;overflow:hidden;border-radius:24px;padding:18px;background:linear-gradient(135deg,#123A66,#0B1A30 70%);border:1px solid var(--line2)}
.wal small{color:var(--dim);font-size:11px}
.wal b{display:block;font-size:28px;font-weight:900;margin-top:2px}
.wal b em{font-style:normal;font-size:12px;color:var(--dim);font-weight:700;margin-right:4px}
.wal:after{content:"";position:absolute;left:-40px;bottom:-60px;width:180px;height:180px;border-radius:50%;border:24px solid rgba(94,234,212,.08)}
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.qa button{height:40px;border-radius:12px;background:var(--card);border:1px solid var(--line);font-weight:800;font-size:12px}
.qa button.on{background:rgba(42,171,238,.16);border-color:var(--tg);color:var(--tg)}
.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:48px;padding:0 14px;border-radius:14px;background:var(--bg2);border:1px solid var(--line2);color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input:focus{border-color:var(--tg);box-shadow:0 0 0 3px rgba(42,171,238,.18)}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.btn{width:100%;height:52px;margin-top:14px;border-radius:16px;background:var(--grad);color:#04121F;font-weight:900;font-size:14px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 14px 26px -14px rgba(42,171,238,.95)}
.btn svg{width:18px;height:18px}
.btn[disabled]{opacity:.55}
.btn.gh{background:var(--card);color:var(--ink);border:1px solid var(--line2);box-shadow:none}
.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(42,171,238,.08);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}
.links{display:grid;gap:8px;margin-top:10px}
.links button{display:flex;align-items:center;gap:10px;height:52px;padding:0 14px;border-radius:16px;background:var(--card);border:1px solid var(--line);font-weight:800;font-size:12.5px;text-align:right}
.links button svg{width:20px;height:20px;color:var(--tg)}
.links button span{flex:1}

.ov{position:fixed;inset:0;z-index:40;background:rgba(2,6,14,.62);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;
  border-radius:26px 26px 0 0;background:#0A1830;border-top:1px solid var(--line2);padding:8px 16px calc(18px + var(--safe));
  transform:translate3d(0,105%,0);transition:transform .34s cubic-bezier(.2,.85,.25,1)}
.sh.on{transform:none}
.grab{width:42px;height:4px;border-radius:4px;background:rgba(142,166,194,.35);margin:0 auto 10px}
.sh .st{display:flex;align-items:flex-start;gap:12px}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#04121F;flex:0 0 auto}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.05);flex:0 0 auto}
.sh .x svg{width:16px;height:16px}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:48px;height:48px;border-radius:14px;background:var(--card);border:1px solid var(--line2);font-size:20px;font-weight:900;color:var(--tg)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:30px;padding:0 11px;border-radius:10px;background:var(--card);border:1px solid var(--line);font-size:11px;font-weight:800;color:var(--dim)}
.qchips button.on{border-color:var(--cy);color:var(--cy)}
.sum{margin-top:14px;border-radius:18px;padding:12px 14px;background:linear-gradient(120deg,rgba(42,171,238,.14),rgba(94,234,212,.06));border:1px solid var(--line2)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t b{font-size:19px;font-weight:900;color:var(--cy)}
.sum div.lo b{color:var(--red)}

.toast{position:fixed;left:16px;right:16px;bottom:calc(20px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:16px;background:#0F2340;border:1px solid var(--line2);font-size:12px;font-weight:700;
  transform:translate3d(0,140%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px #000}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:var(--ok)}.toast.er svg{color:var(--red)}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate svg{width:64px;height:64px;color:var(--tg);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.5a4 4 0 0 0-3-3.8M15.5 4.2a3.5 3.5 0 0 1 0 6.6"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="m12 2.8 2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3L2.9 9.5l6.3-.9z"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.2" fill="currentColor"/><circle cx="3.5" cy="12" r="1.2" fill="currentColor"/><circle cx="3.5" cy="18" r="1.2" fill="currentColor"/></symbol>
    <symbol id="i-wallet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="14" rx="3"/><path d="M2.5 10h19M16 15h2.5M6 6V5a2 2 0 0 1 2-2h9"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m7.5 12.3 3 3 6-6.3"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.3"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.6-4.5L3 9M3 4v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15M21 20v-5h-5"/></symbol>
    <symbol id="i-headset" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.6"/><rect x="17" y="13" width="4" height="6" rx="1.6"/><path d="M19 19a3 3 0 0 1-3 3h-3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-plane" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 3.5 2.8 10.6c-.9.3-.9 1.5 0 1.8l4.7 1.6 1.8 5.6c.3.8 1.3 1 1.8.4l2.7-2.8 4.6 3.4c.7.5 1.6.1 1.8-.7L23 4.7c.2-.9-.7-1.6-1.5-1.2z"/><path d="m7.5 14 11-7.5-8 9"/></symbol>
    <symbol id="i-sim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 2.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 6 20V4a1.5 1.5 0 0 1 1-1.5z"/><rect x="9" y="11" width="6" height="6" rx="1"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor"/></symbol>
    <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></symbol>
  </defs>
</svg>
<div class="aur" aria-hidden="true"></div>

<div class="app">
  <div class="top">
    <div class="bar">
      <div class="me"><div class="av" id="ava"></div><div style="min-width:0"><b id="uName">—</b><small id="uSub">خوش آمدید</small></div></div>
      <button class="ib" id="supBtn" aria-label="پشتیبانی"><svg><use href="#i-headset"/></svg></button>
      <button class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em><i><svg><use href="#i-plus"/></svg></i></button>
    </div>
    <nav class="tabs" id="tabs">
      <span class="ind" id="ind"></span>
      <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
      <button data-go="list"><svg><use href="#i-grid"/></svg>سرویس‌ها</button>
      <button data-go="orders"><svg><use href="#i-list"/></svg>سفارش‌ها<span class="bd" id="ordN"></span></button>
      <button data-go="wallet"><svg><use href="#i-wallet"/></svg>کیف پول</button>
    </nav>
  </div>

  <section class="pg on" id="pg-home">
    <div class="hero">
      <div class="orb"></div>
      <div class="pl"><svg viewBox="0 0 120 120"><defs><linearGradient id="gp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7DD3FC"/><stop offset="1" stop-color="#2AABEE"/></linearGradient></defs>
        <circle cx="60" cy="60" r="44" fill="url(#gp)"/><path d="M34 58.5 84 39c2.3-.9 4.3.6 3.6 3.8l-8.5 40c-.6 2.8-2.3 3.5-4.7 2.2l-13-9.6-6.3 6c-.7.7-1.3 1.3-2.6 1.3l.9-13.3 24.3-22c1-.9-.2-1.4-1.6-.5l-30 18.9-12.9-4c-2.8-.9-2.9-2.8.6-4.2z" fill="#fff"/></svg></div>
      <div class="tx">
        <span class="kick"><i></i>شروعِ خودکار</span>
        <h1 id="hTitle">__TITLE__</h1>
        <p id="hTag">__TAG__</p>
      </div>
      <button class="go" data-go="list"><svg><use href="#i-plane"/></svg>ثبت سفارش</button>
      <div class="kpis"><div><b id="kN">—</b><small>سرویس فعال</small></div><div><b id="kF">—</b><small>شروع قیمت (۱۰۰۰ تایی)</small></div><div><b>۲۴/۷</b><small>ثبت خودکار</small></div></div>
    </div>
    <div class="hd"><h3>دسته‌بندی‌ها</h3></div>
    <div class="bento" id="bento"></div>
    <div class="hd"><h3>پیشنهادِ امروز</h3><button data-go="list">همه</button></div>
    <div class="list" id="pop"></div>
    <div class="hd"><h3>چطور کار می‌کند؟</h3></div>
    <div class="steps">
      <div><i>۱</i><b>سرویس را بزن</b><small>ممبر، بازدید، …</small></div>
      <div><i>۲</i><b>لینک و تعداد</b><small>بدون نیاز به رمز</small></div>
      <div><i>۳</i><b>پرداخت</b><small>از کیف پول، آنی</small></div>
    </div>
    <div class="links" id="xl"></div>
  </section>

  <section class="pg" id="pg-list">
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجوی سرویس…" autocomplete="off"></div>
    <div class="chips" id="cchips"></div>
    <div class="list" id="slist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="hd" style="margin-top:8px"><h3>سفارش‌های من</h3><button id="oRef"><svg style="width:16px;height:16px;display:inline-block;vertical-align:-3px"><use href="#i-refresh"/></svg> تازه کن</button></div>
    <div id="olist"></div>
  </section>

  <section class="pg" id="pg-wallet">
    <div class="wal"><small>موجودی کیف پول</small><b><span id="wBal">…</span><em>تومان</em></b></div>
    <div class="fld"><label>مبلغِ شارژ (تومان)</label><input id="tAmt" inputmode="numeric" placeholder="مثلا ۱۰۰٬۰۰۰"></div>
    <div class="qa" id="qa"></div>
    <button class="btn" id="tGo"><svg><use href="#i-wallet"/></svg>درخواستِ شارژ</button>
    <div class="note" id="tNote">فاکتور و روشِ پرداخت داخلِ ربات برایتان فرستاده می‌شود؛ بعد از واریز، موجودی خودکار یا با تاییدِ پشتیبانی شارژ می‌شود.</div>
    <div class="links"><button id="supBtn2"><svg><use href="#i-headset"/></svg><span>پشتیبانی</span></button></div>
  </section>
</div>

<div class="ov" id="ov"></div>
<div class="sh" id="sh"><div class="grab"></div><div id="shB"></div></div>
<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var D = document, H = D.documentElement;
var $ = function(id){ return D.getElementById(id); };
var HASH_INIT = (function(){ try { var m = /(?:^|&)tgWebAppData=([^&]*)/.exec(String(location.hash || '').replace(/^#/, '')); return m ? decodeURIComponent(m[1]) : ''; } catch(e){ return ''; } })();
function initData(){ try { if (TG && TG.initData) return TG.initData; } catch(e){} return HASH_INIT; }
function tgUser(){
  try { if (TG && TG.initDataUnsafe && TG.initDataUnsafe.user) return TG.initDataUnsafe.user; } catch(e){}
  try { var m = /(?:^|&)user=([^&]*)/.exec(HASH_INIT); var u = m ? JSON.parse(decodeURIComponent(m[1])) : null; return (u && u.id) ? u : null; } catch(e){ return null; }
}
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function faD(s){ return String(s == null ? '' : s).replace(/\d/g, function(d){ return String.fromCharCode(1776 + +d); }); }
var NF = null; try { NF = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }); } catch(e){}
function fa(n){ n = Number(n) || 0; return NF ? NF.format(n) : faD(Math.round(n)); }
function digits(s){ s = String(s == null ? '' : s); var o = ''; for (var i = 0; i < s.length; i++) { var c = s.charCodeAt(i);
  if (c >= 1776 && c <= 1785) o += (c - 1776); else if (c >= 1632 && c <= 1641) o += (c - 1632); else if (c >= 48 && c <= 57) o += s[i]; } return o; }
function ico(n){ return '<svg><use href="#i-' + n + '"/></svg>'; }
function tap(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k || 'light'); } catch(e){} }
function buzz(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); } catch(e){} }
var TT;
function toast(msg, good){
  var t = $('toast');
  t.className = 'toast ' + (good ? 'ok' : 'er');
  t.innerHTML = ico(good ? 'check' : 'alert') + '<span>' + esc(msg) + '</span>';
  void t.offsetWidth; t.classList.add('on');
  clearTimeout(TT); TT = setTimeout(function(){ t.classList.remove('on'); }, 3600);
  buzz(good ? 'success' : 'error');
}
function openLink(url){
  if (!url) return;
  try { if (TG && /^https:\/\/t\.me\//i.test(url) && TG.openTelegramLink) { TG.openTelegramLink(url); return; }
        if (TG && TG.openLink) { TG.openLink(url); return; } } catch(e){}
  window.open(url, '_blank', 'noopener');
}
function openApp(url){
  if (!url) return;
  var d = initData(), h = '';
  if (d) h = '#tgWebAppData=' + encodeURIComponent(d) + '&tgWebAppVersion=' + encodeURIComponent((TG && TG.version) || '7.0') +
             '&tgWebAppPlatform=' + encodeURIComponent((TG && TG.platform) || 'unknown');
  location.href = url + h;
}

var API = (function(){ try { if (/^https?:$/.test(location.protocol)) return location.origin + location.pathname + '?mapi=1'; } catch(e){} return ''; })();
var READS = { me: 1, sv_orders: 1, sv_order: 1 };
var GATED = false;
function api(action, extra, ok, bad, tried){
  bad = bad || function(j){ toast((j && j.message) || 'خطا — دوباره امتحان کنید.'); };
  if (!API) { bad({ message: 'آدرس سرور تنظیم نشده است.' }); return; }
  var t0 = Date.now(), got = false, body = { action: action, initData: initData() };
  for (var k in (extra || {})) body[k] = extra[k];
  var ctl = null, tm = null;
  try { ctl = new AbortController(); tm = setTimeout(function(){ ctl.abort(); }, 30000); } catch(e){}
  fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
               signal: ctl ? ctl.signal : undefined, cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
    .then(function(r){ got = true; return r.json().catch(function(){ return { ok: false, message: 'پاسخ سرور نامعتبر بود.' }; }); })
    .then(function(j){ if (tm) clearTimeout(tm);
      if (j && j.ok) { ok(j); return; }
      if (j && j.error === 'unauthorized') { gate(j.message); return; }
      bad(j || {}); })
    .catch(function(){ if (tm) clearTimeout(tm);
      if (!got && !tried && READS[action] && Date.now() - t0 < 8000) { setTimeout(function(){ api(action, extra, ok, bad, 1); }, 700); return; }
      bad({ message: 'ارتباط با سرور برقرار نشد.' }); });
}
function gate(msg){
  if (GATED) return; GATED = true;
  var g = D.createElement('div'); g.className = 'gate';
  g.innerHTML = ico('plane') + '<b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p>' +
    (B.bot ? '<button class="btn" style="max-width:260px">رفتن به ربات</button>' : '');
  D.body.appendChild(g);
  var b = g.querySelector('button'); if (b) b.onclick = function(){ openLink('https://t.me/' + B.bot); };
}

var CATS = B.cats || [], ITEMS = B.items || [], CAT = {}, ITEM = {};
CATS.forEach(function(c){ CAT[c.id] = c; });
ITEMS.forEach(function(i){ ITEM[i.i] = i; i.k = String(i.n || '').toLowerCase(); });
var HINT = { members: ['لینک یا آیدیِ کانال/گروه', 't.me/mychannel'], views: ['لینکِ پست', 't.me/mychannel/125'],
  reactions: ['لینکِ پست', 't.me/mychannel/125'], votes: ['لینکِ پستِ نظرسنجی', 't.me/mychannel/125'],
  comments: ['لینکِ پست', 't.me/mychannel/125'], premium: ['لینکِ کانال', 't.me/mychannel'], other: ['لینک', 't.me/…'] };
var S = { page: '', stack: [], bal: 0, cat: '', q: '', orders: null, cur: null, sheet: false, poll: null };
var U = tgUser() || {};

function setBal(v){ if (v == null || isNaN(Number(v))) return; S.bal = Number(v); $('bal').textContent = fa(S.bal); $('wBal').textContent = fa(S.bal); }
function drawSelf(avatar){
  var n = ((U.first_name || '') + ' ' + (U.last_name || '')).trim() || (U.username ? '@' + U.username : 'کاربر');
  $('uName').textContent = n;
  var ini = esc(n.charAt(0).toUpperCase());
  $('ava').innerHTML = avatar ? '<img src="' + esc(avatar) + '" alt="" onerror="this.parentNode.textContent=\'' + ini.replace(/'/g, '') + '\'">' : ini;
}

var PAGES = ['home', 'list', 'orders', 'wallet'];
function go(p, back){
  if (PAGES.indexOf(p) < 0) p = 'home';
  if (p === S.page) { window.scrollTo(0, 0); return; }
  if (!back && S.page) S.stack.push(S.page);
  S.page = p;
  PAGES.forEach(function(x){ $('pg-' + x).classList.toggle('on', x === p); });
  var btns = D.querySelectorAll('#tabs button'), idx = 0;
  [].forEach.call(btns, function(b, i){ var on = b.getAttribute('data-go') === p; b.classList.toggle('on', on); if (on) idx = i; });
  $('ind').style.transform = 'translate3d(' + (-idx * 100) + '%,0,0)';
  window.scrollTo(0, 0);
  clearTimeout(S.poll);
  if (p === 'list') drawList();
  if (p === 'orders') loadOrders();
  if (p === 'wallet') drawWallet();
  backBtn();
}
function goBack(){
  if (S.sheet) { closeSheet(); return; }
  var p = S.stack.pop(); go(p || 'home', true);
}
function backBtn(){
  if (!TG || !TG.BackButton) return;
  try { if (S.sheet || S.page !== 'home') TG.BackButton.show(); else TG.BackButton.hide(); } catch(e){}
}
D.addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('[data-go]') : null;
  if (!el) return;
  ev.preventDefault(); tap();
  if (S.sheet) closeSheet();
  var cat = el.getAttribute('data-cat');
  if (cat != null) S.cat = cat;
  go(el.getAttribute('data-go'));
  if (cat != null) drawList(true);
});

function priceRow(i){
  var c = CAT[i.c] || {};
  return '<button class="tk" data-sv="' + esc(i.i) + '"><span class="m"><b>' + esc(i.n) + '</b><small>' +
    '<span>' + esc(c.n || '') + '</span><span>' + fa(i.mn) + ' تا ' + fa(i.mx) + '</span>' + (i.r ? '<span class="g">ضمانتِ ریزش</span>' : '') +
    '</small></span><span class="p"><b>' + fa(i.p) + '</b><small>تومان / ۱۰۰۰</small></span></button>';
}
function drawHome(){
  $('kN').textContent = fa(ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  $('kF').textContent = min ? fa(min) : '—';
  $('bento').innerHTML = CATS.length ? CATS.map(function(c){
    return '<button class="bt" data-go="list" data-cat="' + esc(c.id) + '"><span class="ic">' + ico(c.ic) + '</span>' +
      '<span><b>' + esc(c.n) + '</b><small>' + fa(c.c) + ' سرویس</small><span class="pr">از ' + fa(c.f) + ' تومان</span></span></button>';
  }).join('') : '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';
  var pop = ITEMS.slice().sort(function(a, b){ return a.p - b.p; }).slice(0, 4);
  $('pop').innerHTML = pop.map(priceRow).join('');
  var xl = '';
  if ((B.links || {}).ig) xl += '<button data-open="ig">' + ico('camera') + '<span>خدمات اینستاگرام</span></button>';
  if ((B.links || {}).num) xl += '<button data-open="num">' + ico('sim') + '<span>شماره مجازی تلگرام</span></button>';
  $('xl').innerHTML = xl;
}
$('xl').addEventListener('click', function(ev){ var b = ev.target.closest('[data-open]'); if (b) { tap(); openApp(B.links[b.getAttribute('data-open')]); } });

var LK = '';
function drawList(force){
  var key = S.cat + '|' + S.q;
  if (!force && key === LK && $('slist').children.length) return;
  LK = key;
  $('cchips').innerHTML = '<button data-c="" class="' + (S.cat === '' ? 'on' : '') + '">همه</button>' + CATS.map(function(c){
    return '<button data-c="' + esc(c.id) + '" class="' + (S.cat === c.id ? 'on' : '') + '">' + ico(c.ic) + esc(c.n) + '</button>';
  }).join('');
  var q = S.q.trim().toLowerCase();
  var l = ITEMS.filter(function(i){ return (!S.cat || i.c === S.cat) && (!q || i.k.indexOf(q) >= 0); });
  $('slist').innerHTML = l.length ? l.map(priceRow).join('') :
    '<div class="emp">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>';
}
$('cchips').addEventListener('click', function(ev){ var b = ev.target.closest('[data-c]'); if (!b) return; tap(); S.cat = b.getAttribute('data-c'); drawList(true); });
var QT;
$('q').addEventListener('input', function(){ var v = this.value; clearTimeout(QT); QT = setTimeout(function(){ S.q = v; drawList(true); }, 140); });
D.addEventListener('click', function(ev){ var b = ev.target.closest ? ev.target.closest('[data-sv]') : null; if (b) { tap(); openOrder(b.getAttribute('data-sv')); } });

function total(i, q){ return Math.max(1, Math.ceil(i.p * q / 1000 - 1e-9)); }
function niceQty(i){
  var c = [1000, 500, 100, 5000, 10000]; for (var k = 0; k < c.length; k++) if (c[k] >= i.mn && c[k] <= i.mx) return c[k];
  return i.mn;
}
function linkOk(v){ return /^(@[A-Za-z][A-Za-z0-9_]{3,31}|(https?:\/\/)?(www\.)?(t|telegram)\.(me|dog)\/\S+)$/i.test(v.trim()); }
function openOrder(id){
  var i = ITEM[id]; if (!i) return;
  var c = CAT[i.c] || {}, h = HINT[i.c] || HINT.other;
  S.cur = i;
  var chips = [i.mn, 1000, 5000, 10000, 50000, i.mx].filter(function(v, k, a){ return v >= i.mn && v <= i.mx && a.indexOf(v) === k; })
    .sort(function(a, b){ return a - b; }).slice(0, 5);
  $('shB').innerHTML =
    '<div class="st"><span class="ic">' + ico(c.ic || 'spark') + '</span><div style="flex:1;min-width:0"><b>' + esc(i.n) + '</b><small>' +
      fa(i.p) + ' تومان برای هر ۱۰۰۰ تا · حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx) + '</small></div><button class="x" id="shX">' + ico('x') + '</button></div>' +
    '<div class="fld"><label>' + esc(h[0]) + '</label><input id="oLink" class="ltr" placeholder="' + esc(h[1]) + '" autocomplete="off" autocapitalize="off" spellcheck="false"><small id="oLinkH">کانال یا گروه باید عمومی باشد؛ رمز لازم نیست.</small></div>' +
    '<div class="fld"><label>تعداد</label><div class="qrow"><button id="qM" aria-label="کم">−</button><input id="oQty" inputmode="numeric"><button id="qP" aria-label="زیاد">+</button></div>' +
    '<div class="qchips" id="qC">' + chips.map(function(v){ return '<button data-q="' + v + '">' + fa(v) + '</button>'; }).join('') + '</div></div>' +
    '<div class="sum"><div><span>قیمتِ هر ۱۰۰۰ تا</span><b>' + fa(i.p) + ' تومان</b></div><div><span>موجودیِ شما</span><b id="oBal">' + fa(S.bal) + ' تومان</b></div>' +
    '<div class="t"><span>مبلغِ کل</span><b id="oTot">—</b></div></div>' +
    '<button class="btn" id="oGo">' + ico('plane') + '<span id="oGoT">پرداخت و ثبتِ سفارش</span></button>';
  var qi = $('oQty'); qi.value = fa(niceQty(i));
  function q(){ return parseInt(digits(qi.value), 10) || 0; }
  function upd(){
    var n = q(), ok = n >= i.mn && n <= i.mx, t = ok ? total(i, n) : 0;
    $('oTot').textContent = ok ? fa(t) + ' تومان' : 'تعداد بین ' + fa(i.mn) + ' تا ' + fa(i.mx);
    [].forEach.call($('qC').children, function(b){ b.classList.toggle('on', +b.getAttribute('data-q') === n); });
    var low = ok && t > S.bal;
    $('oBal').parentNode.classList.toggle('lo', low);
    $('oGoT').textContent = low ? 'شارژِ کیف پول (' + fa(t - S.bal) + ' تومان کم است)' : 'پرداخت و ثبتِ سفارش';
    return t;
  }
  function setQ(n){ n = Math.max(i.mn, Math.min(i.mx, n)); qi.value = fa(n); upd(); }
  var step = function(){ var n = q(); return n >= 10000 ? 1000 : n >= 1000 ? 100 : n >= 100 ? 10 : 1; };
  $('qM').onclick = function(){ tap(); setQ(q() - step()); };
  $('qP').onclick = function(){ tap(); setQ(q() + step()); };
  $('qC').onclick = function(ev){ var b = ev.target.closest('[data-q]'); if (b) { tap(); setQ(+b.getAttribute('data-q')); } };
  qi.oninput = function(){ var n = q(); qi.value = n ? fa(n) : ''; upd(); };
  $('oLink').oninput = function(){ var v = this.value.trim(); var hs = $('oLinkH');
    if (v && !linkOk(v)) { hs.textContent = 'لینکِ تلگرام باید مثل ' + h[1] + ' یا @username باشد.'; hs.className = 'er'; }
    else { hs.textContent = 'کانال یا گروه باید عمومی باشد؛ رمز لازم نیست.'; hs.className = ''; } };
  $('shX').onclick = function(){ tap(); closeSheet(); };
  $('oGo').onclick = function(){
    var n = q(), t = upd(), link = $('oLink').value.trim();
    if (n < i.mn || n > i.mx) { toast('تعداد باید بین ' + fa(i.mn) + ' و ' + fa(i.mx) + ' باشد.'); return; }
    if (t > S.bal) { closeSheet(); WAL.pre = t - S.bal; go('wallet'); return; }
    if (!linkOk(link)) { toast('لینکِ درست وارد کنید.'); $('oLink').focus(); return; }
    var b = $('oGo'); b.disabled = true; $('oGoT').textContent = 'در حال ثبت…';
    api('sv_buy', { app: B.app, sid: i.i, link: link, qty: n, seen: t }, function(j){
      b.disabled = false; setBal(j.balance); closeSheet(); S.orders = null; buzz('success');
      toast(j.warn || 'سفارش ثبت شد و به‌زودی شروع می‌شود.', true);
      go('orders');
    }, function(j){
      b.disabled = false; upd();
      if (j && j.balance != null) setBal(j.balance);
      if (j && j.error === 'price_changed' && j.p) { i.p = j.p; upd(); }
      if (j && j.error === 'no_balance') { closeSheet(); WAL.pre = j.need || 0; go('wallet'); }
      toast((j && j.message) || 'ثبت نشد — دوباره امتحان کنید.');
    });
  };
  upd();
  S.sheet = true; $('ov').classList.add('on'); $('sh').classList.add('on'); $('sh').scrollTop = 0; backBtn();
}
function closeSheet(){ S.sheet = false; $('ov').classList.remove('on'); $('sh').classList.remove('on'); backBtn(); }
$('ov').onclick = closeSheet;

function stCls(s){ return s === 'run' ? 'run' : s; }
function ago(ts){
  var d = Math.max(0, Math.floor(Date.now() / 1000 - (ts || 0)));
  if (d < 60) return 'همین الان'; if (d < 3600) return fa(Math.floor(d / 60)) + ' دقیقه پیش';
  if (d < 86400) return fa(Math.floor(d / 3600)) + ' ساعت پیش'; return fa(Math.floor(d / 86400)) + ' روز پیش';
}
function loadOrders(){
  if (!S.orders) $('olist').innerHTML = '<div class="sk"></div><div class="sk"></div><div class="sk"></div>';
  else drawOrders();
  api('sv_orders', { app: B.app }, function(j){
    S.orders = j.list || []; setBal(j.balance); drawOrders();
    var run = S.orders.some(function(o){ return o.st === 'run'; });
    clearTimeout(S.poll);
    if (run && S.page === 'orders') S.poll = setTimeout(function(){ if (!D.hidden && S.page === 'orders') loadOrders(); }, 30000);
  }, function(j){ if (!S.orders) $('olist').innerHTML = '<div class="emp">' + ico('alert') + '<b>بارگذاری نشد</b>' + esc((j && j.message) || '') + '</div>'; });
}
function drawOrders(){
  var l = S.orders || [];
  var run = l.filter(function(o){ return o.st === 'run'; }).length;
  $('ordN').textContent = fa(run); $('ordN').classList.toggle('on', run > 0);
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('list') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="btn" data-go="list" style="margin-top:14px">' + ico('grid') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o){
    var c = CAT[o.c] || {};
    return '<div class="or"><div class="h"><span class="ic">' + ico(c.ic || 'spark') + '</span><div><b>' + esc(o.n) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
      '<span class="pill ' + stCls(o.st) + '">' + esc(o.sx) + '</span></div>' +
      (o.st === 'run' || o.st === 'done' || o.st === 'partial' ? '<div class="prog"><i style="width:' + Math.max(o.st === 'run' ? 4 : 0, o.pc) + '%"></i></div>' : '<div style="height:8px"></div>') +
      '<div class="kv"><span>تعداد: <b>' + fa(o.q) + '</b></span>' + (o.sc >= 0 ? '<span>شروع از: <b>' + fa(o.sc) + '</b></span>' : '') +
      (o.rm >= 0 && o.st === 'run' ? '<span>مانده: <b>' + fa(o.rm) + '</b></span>' : '') + (o.rf > 0 ? '<span>برگشتی: <b>' + fa(o.rf) + '</b> تومان</span>' : '') +
      '<span class="ltr">#' + esc(o.id) + '</span></div>' +
      '<div class="lnk">' + ico('link') + '<span>' + esc(o.l) + '</span></div></div>';
  }).join('');
}
$('oRef').onclick = function(){ tap(); loadOrders(); };

var WAL = { pre: 0 };
function tMin(){ return Math.max(1000, Number((B.topup || {}).min) || 0); }
function drawWallet(){
  var min = tMin();
  var qs = [min, 50000, 100000, 200000, 500000, 1000000].filter(function(v, i, a){ return v >= min && a.indexOf(v) === i; }).sort(function(a, b){ return a - b; }).slice(0, 6);
  $('qa').innerHTML = qs.map(function(v){ return '<button data-v="' + v + '">' + fa(v) + '</button>'; }).join('');
  if (WAL.pre) { $('tAmt').value = fa(Math.max(min, Math.ceil(WAL.pre / 1000) * 1000)); WAL.pre = 0; }
  markQa();
  var t = B.topup || {};
  if (!t.on && !t.gw) $('tNote').textContent = 'روشِ پرداخت هنوز تنظیم نشده است؛ با پشتیبانی تماس بگیرید.';
}
function markQa(){ var v = parseInt(digits($('tAmt').value), 10) || 0; [].forEach.call($('qa').children, function(b){ b.classList.toggle('on', +b.getAttribute('data-v') === v); }); }
$('qa').onclick = function(ev){ var b = ev.target.closest('[data-v]'); if (!b) return; tap(); $('tAmt').value = fa(+b.getAttribute('data-v')); markQa(); };
$('tAmt').oninput = function(){ var n = parseInt(digits(this.value), 10) || 0; this.value = n ? fa(n) : ''; markQa(); };
$('tGo').onclick = function(){
  var n = parseInt(digits($('tAmt').value), 10) || 0;
  if (n < tMin()) { toast('کمترین مبلغِ شارژ ' + fa(tMin()) + ' تومان است.'); return; }
  var b = this; b.disabled = true;
  api('topup', { amount: n }, function(j){ b.disabled = false; toast(j.message || 'درخواستِ شارژ ثبت شد.', true); },
      function(j){ b.disabled = false; toast((j && j.message) || 'ثبت نشد.'); });
};
$('balBtn').onclick = function(){ tap(); go('wallet'); };
function support(){ tap(); if (B.sup) openLink(B.sup); else if (B.bot) openLink('https://t.me/' + B.bot); }
$('supBtn').onclick = support; $('supBtn2').onclick = support;

(function(){
  var sh = $('sh'), y0 = null, dy = 0;
  sh.addEventListener('touchstart', function(e){ if (sh.scrollTop > 0 && !e.target.closest('.grab')) { y0 = null; return; } y0 = e.touches[0].clientY; dy = 0; }, { passive: true });
  sh.addEventListener('touchmove', function(e){ if (y0 == null) return; dy = e.touches[0].clientY - y0; if (dy > 0) { sh.style.transition = 'none'; sh.style.transform = 'translate3d(0,' + dy + 'px,0)'; } }, { passive: true });
  sh.addEventListener('touchend', function(){ if (y0 == null) return; sh.style.transition = ''; sh.style.transform = ''; if (dy > 110) closeSheet(); y0 = null; });
})();

function tgSetup(){
  if (!TG) return;
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#050B18'); TG.setBackgroundColor && TG.setBackgroundColor('#050B18'); TG.setBottomBarColor && TG.setBottomBarColor('#050B18'); } catch(e){}
  try { if (TG.BackButton) TG.BackButton.onClick(goBack); } catch(e){}
  backBtn();
}
if (TG) tgSetup();
else { var tries = 0, iv = setInterval(function(){
  if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; U = tgUser() || U; tgSetup(); drawSelf(''); }
  else if (++tries > 40) clearInterval(iv); }, 100); }
D.addEventListener('visibilitychange', function(){ if (!D.hidden && S.page === 'orders') loadOrders(); });

drawSelf('');
drawHome();
var WANT = (function(){ try { return String(new URLSearchParams(location.search).get('p') || ''); } catch(e){ return ''; } })();
go(WANT === 'orders' || WANT === 'wallet' || WANT === 'list' ? WANT : 'home');
api('me', {}, function(j){ setBal(j.balance); drawSelf(j.avatar); }, function(){});
})();
</script>
</body>
</html>
HTML;
}
