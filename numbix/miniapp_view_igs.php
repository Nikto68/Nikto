<?php

function svTplIg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#FFFBF8">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
__FONT__
<style>
:root{
  --bg:#FFFBF8;--card:#FFFFFF;--soft:#F6F1EE;--soft2:#FBF6F3;--line:#EFE5DF;--ink:#16121A;--dim:#6E6470;--dim2:#A69CA6;
  --o:#F58529;--p:#DD2A7B;--v:#8134AF;--b:#515BD4;--ok:#16A34A;--warn:#C2410C;--red:#DC2626;
  --grad:linear-gradient(45deg,#F58529 0%,#DD2A7B 45%,#8134AF 78%,#515BD4 100%);
  --grad2:linear-gradient(135deg,#FEDA75 0%,#FA7E1E 30%,#D62976 60%,#962FBF 85%,#4F5BD5 100%);
  --sh:0 10px 30px -18px rgba(88,28,70,.45);--safe:env(safe-area-inset-bottom,0px);color-scheme:light
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
.blob{position:fixed;left:0;right:0;top:0;height:260px;z-index:0;pointer-events:none;
  background:radial-gradient(60% 90% at 88% 0,rgba(245,133,41,.16),transparent 70%),radial-gradient(55% 80% at 10% 0,rgba(129,52,175,.12),transparent 70%)}
.app{position:relative;z-index:1;max-width:480px;margin:0 auto;padding:0 16px calc(96px + var(--safe))}

.hdr{position:sticky;top:0;z-index:20;margin:0 -16px;padding:12px 16px 10px;display:flex;align-items:center;gap:10px;
  background:rgba(255,251,248,.94);border-bottom:1px solid transparent;transition:border-color .2s}
.hdr.sc{border-bottom-color:var(--line)}
.wm{flex:1;min-width:0;font-size:21px;font-weight:900;letter-spacing:-.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.chipb{display:flex;align-items:center;gap:6px;height:36px;padding:0 5px 0 11px;border-radius:18px;background:var(--card);
  border:1.5px solid transparent;background-image:linear-gradient(var(--card),var(--card)),var(--grad);background-origin:border-box;
  background-clip:padding-box,border-box;font-weight:900;font-size:12px;box-shadow:var(--sh)}
.chipb em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.chipb i{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff}
.chipb i svg{width:14px;height:14px}
.icb{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--card);border:1px solid var(--line)}
.icb svg{width:19px;height:19px}

.stories{display:flex;gap:14px;overflow-x:auto;scrollbar-width:none;margin:4px -16px 0;padding:6px 16px 8px}
.stories::-webkit-scrollbar{display:none}
.story{flex:0 0 auto;width:68px;text-align:center}
.story .rg{width:66px;height:66px;margin:0 auto;border-radius:50%;padding:2.5px;background:var(--grad2)}
.story.off .rg{background:var(--line)}
.story .in{width:100%;height:100%;border-radius:50%;background:var(--card);padding:3px}
.story .in div{width:100%;height:100%;border-radius:50%;display:grid;place-items:center;background:var(--soft);color:var(--p)}
.story .in svg{width:24px;height:24px}
.story.on .in div{background:var(--grad);color:#fff}
.story small{display:block;margin-top:5px;font-size:10.5px;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.pg{display:none}
.pg.on{display:block;animation:fi .3s ease}
@keyframes fi{from{opacity:0}to{opacity:1}}

.post{margin-top:12px;border-radius:24px;background:var(--card);border:1px solid var(--line);box-shadow:var(--sh);overflow:hidden}
.post .u{display:flex;align-items:center;gap:9px;padding:11px 13px}
.post .u .a{width:36px;height:36px;border-radius:50%;padding:2px;background:var(--grad2)}
.post .u .a div{width:100%;height:100%;border-radius:50%;border:2px solid #fff;background:var(--grad);display:grid;place-items:center;color:#fff}
.post .u .a svg{width:16px;height:16px}
.post .u b{font-size:12.5px;font-weight:800;display:flex;align-items:center;gap:4px}
.post .u b svg{width:14px;height:14px;color:#3897F0}
.post .u small{display:block;font-size:10px;color:var(--dim)}
.post .img{position:relative;height:210px;background:var(--grad);overflow:hidden;display:grid;place-items:center}
.post .img:before{content:"";position:absolute;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.12);top:-150px;left:-110px}
.post .img:after{content:"";position:absolute;width:200px;height:200px;border-radius:50%;border:26px solid rgba(255,255,255,.12);bottom:-90px;right:-50px}
.post .img .big{position:relative;display:flex;gap:10px;align-items:flex-end}
.post .img .big span{width:62px;height:62px;border-radius:20px;display:grid;place-items:center;background:rgba(255,255,255,.2);color:#fff;
  border:1px solid rgba(255,255,255,.35);animation:bob 3.4s ease-in-out infinite}
.post .img .big span:nth-child(2){width:78px;height:78px;border-radius:24px;animation-delay:-.8s}
.post .img .big span:nth-child(3){animation-delay:-1.6s}
.post .img .big svg{width:30px;height:30px}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-8px,0)}}
.post .img .tag{position:absolute;bottom:12px;right:12px;padding:4px 10px;border-radius:14px;background:rgba(0,0,0,.35);color:#fff;font-size:10.5px;font-weight:700}
.post .act{display:flex;align-items:center;gap:14px;padding:10px 13px 2px}
.post .act svg{width:23px;height:23px}
.post .act .sv{margin-right:auto}
.post .act .lk{color:var(--p)}
.post .cap{padding:4px 13px 14px}
.post .cap b{font-size:14px;font-weight:900}
.post .cap p{font-size:11.5px;color:var(--dim);margin-top:2px}
.cta{margin-top:12px;width:100%;height:46px;border-radius:14px;background:var(--grad);color:#fff;font-weight:900;font-size:13.5px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 12px 24px -14px rgba(221,42,123,.9)}
.cta svg{width:18px;height:18px}
.cta[disabled]{opacity:.55}
.cta.gh{background:var(--card);color:var(--ink);border:1px solid var(--line);box-shadow:none}

.stat3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.stat3 div{padding:10px;border-radius:16px;background:var(--card);border:1px solid var(--line);text-align:center}
.stat3 b{display:block;font-size:15px;font-weight:900;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat3 small{font-size:10px;color:var(--dim)}

.hd{display:flex;align-items:center;margin:20px 2px 10px}
.hd h3{flex:1;font-size:15px;font-weight:900}
.hd button{font-size:11.5px;font-weight:800;color:var(--p)}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tile{position:relative;display:flex;flex-direction:column;text-align:right;padding:12px;border-radius:20px;background:var(--card);
  border:1px solid var(--line);box-shadow:var(--sh);min-height:176px;width:100%}
.tile .ic{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;color:#fff;background:var(--grad)}
.tile .ic svg{width:22px;height:22px}
.tile.c1 .ic{background:linear-gradient(135deg,#F58529,#DD2A7B)}
.tile.c2 .ic{background:linear-gradient(135deg,#DD2A7B,#8134AF)}
.tile.c3 .ic{background:linear-gradient(135deg,#8134AF,#515BD4)}
.tile.c4 .ic{background:linear-gradient(135deg,#FEDA75,#F58529)}
.tile b{margin-top:10px;font-size:12px;font-weight:800;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile small{font-size:9.5px;color:var(--dim2);margin-top:2px}
.tile .ft{margin-top:auto;padding-top:8px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .ft span{font-size:10px;color:var(--dim)}
.tile .ft span b{display:inline;margin:0;font-size:14px;font-weight:900;color:var(--ink)}
.tile .ft i{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:var(--soft);color:var(--p);flex:0 0 auto}
.tile .ft i svg{width:15px;height:15px}
.tile .rf{position:absolute;top:12px;left:12px;font-size:9px;font-weight:800;padding:2px 7px;border-radius:8px;background:rgba(22,163,74,.1);color:var(--ok)}
.tile:active{transform:scale(.98)}

.srch{display:flex;align-items:center;gap:8px;height:42px;margin-top:6px;padding:0 13px;border-radius:13px;background:var(--soft)}
.srch svg{width:17px;height:17px;color:var(--dim)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}

.emp{text-align:center;padding:34px 18px;border-radius:22px;background:var(--card);border:1px dashed var(--line);color:var(--dim)}
.emp svg{width:40px;height:40px;margin:0 auto 10px;color:var(--p)}
.emp b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{height:84px;border-radius:18px;margin-bottom:10px;background:linear-gradient(90deg,var(--soft) 30%,var(--soft2) 50%,var(--soft) 70%);
  background-size:300% 100%;animation:sk 1.3s linear infinite}
@keyframes sk{from{background-position:100% 0}to{background-position:-200% 0}}

.or{position:relative;border-radius:20px;background:var(--card);border:1px solid var(--line);box-shadow:var(--sh);padding:13px 16px 13px 13px;margin-bottom:10px;overflow:hidden}
.or:before{content:"";position:absolute;right:0;top:0;bottom:0;width:4px;background:var(--grad)}
.or.done:before{background:var(--ok)}.or.partial:before,.or.check:before{background:#F59E0B}.or.canceled:before,.or.failed:before{background:var(--red)}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(221,42,123,.1);color:var(--p)}
.pill.done{background:rgba(22,163,74,.1);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(245,158,11,.14);color:var(--warn)}
.pill.canceled,.pill.failed{background:rgba(220,38,38,.1);color:var(--red)}
.prog{height:6px;border-radius:6px;background:var(--soft);margin:11px 0 7px;overflow:hidden}
.prog i{display:block;height:100%;border-radius:6px;background:var(--grad);transition:width .6s ease}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:#3897F0;direction:ltr;overflow:hidden}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}

.wal{position:relative;overflow:hidden;margin-top:10px;border-radius:24px;padding:20px 18px;background:var(--grad);color:#fff;box-shadow:0 18px 34px -18px rgba(129,52,175,.8)}
.wal small{opacity:.85;font-size:11px}
.wal b{display:block;font-size:28px;font-weight:900;margin-top:2px}
.wal b em{font-style:normal;font-size:12px;opacity:.85;font-weight:700;margin-right:4px}
.wal:after{content:"";position:absolute;left:-50px;top:-60px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.13)}
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.qa button{height:40px;border-radius:12px;background:var(--card);border:1px solid var(--line);font-weight:800;font-size:12px}
.qa button.on{border-color:var(--p);color:var(--p);background:rgba(221,42,123,.06)}
.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:48px;padding:0 14px;border-radius:14px;background:var(--soft);border:1.5px solid transparent;color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input:focus{border-color:var(--p);background:#fff}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:var(--soft2);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}
.links{display:grid;gap:8px;margin-top:12px}
.links button{display:flex;align-items:center;gap:10px;height:52px;padding:0 14px;border-radius:16px;background:var(--card);border:1px solid var(--line);font-weight:800;font-size:12.5px;text-align:right}
.links button svg{width:20px;height:20px;color:var(--p)}
.links button span{flex:1}

.nav{position:fixed;left:0;right:0;bottom:0;z-index:30;background:rgba(255,255,255,.97);border-top:1px solid var(--line);padding-bottom:var(--safe)}
.nav div{max-width:480px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);height:62px}
.nav button{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:10px;font-weight:700;color:var(--dim2)}
.nav button svg{width:24px;height:24px}
.nav button.on{color:var(--ink)}
.nav button.on svg{color:var(--p)}
.nav button.on:after{content:"";position:absolute;bottom:6px;width:5px;height:5px;border-radius:50%;background:var(--grad)}
.nav .bd{position:absolute;top:8px;left:calc(50% - 20px);min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--p);color:#fff;font-size:9.5px;font-weight:900;display:none;place-items:center}
.nav .bd.on{display:grid}

.ov{position:fixed;inset:0;z-index:40;background:rgba(22,18,26,.45);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;
  border-radius:26px 26px 0 0;background:#fff;padding:8px 16px calc(18px + var(--safe));
  transform:translate3d(0,105%,0);transition:transform .34s cubic-bezier(.2,.85,.25,1)}
.sh.on{transform:none}
.grab{width:42px;height:4px;border-radius:4px;background:var(--line);margin:0 auto 10px}
.sh .st{display:flex;align-items:flex-start;gap:12px}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--soft);flex:0 0 auto}
.sh .x svg{width:16px;height:16px}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:48px;height:48px;border-radius:50%;background:var(--soft);font-size:20px;font-weight:900;color:var(--p)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:30px;padding:0 12px;border-radius:15px;background:var(--soft);font-size:11px;font-weight:800;color:var(--dim)}
.qchips button.on{background:var(--grad);color:#fff}
.sum{margin-top:14px;border-radius:18px;padding:12px 14px;background:var(--soft2);border:1px solid var(--line)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t b{font-size:19px;font-weight:900;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.sum div.lo b{color:var(--red)}

.toast{position:fixed;left:16px;right:16px;bottom:calc(76px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:16px;background:#16121A;color:#fff;font-size:12px;font-weight:700;
  transform:translate3d(0,200%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px rgba(0,0,0,.6)}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:#4ADE80}.toast.er svg{color:#FB7185}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate svg{width:64px;height:64px;color:var(--p);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.5a4 4 0 0 0-3-3.8M15.5 4.2a3.5 3.5 0 0 1 0 6.6"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="m10 8.5 5.5 3.5-5.5 3.5z"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/></symbol>
    <symbol id="i-ring" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="8.5" stroke-dasharray="3.5 2.4"/><circle cx="12" cy="12" r="4"/></symbol>
    <symbol id="i-bookmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M6 3.5h12v17l-6-4.2-6 4.2z"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-explore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></symbol>
    <symbol id="i-receipt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 2.5h14v19l-2.3-1.6-2.4 1.6-2.3-1.6-2.3 1.6-2.4-1.6L5 21.5z"/><path d="M9 8h6M9 12h6M9 16h3"/></symbol>
    <symbol id="i-wallet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="14" rx="3"/><path d="M2.5 10h19M16 15h2.5M6 6V5a2 2 0 0 1 2-2h9"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m7.5 12.3 3 3 6-6.3"/></symbol>
    <symbol id="i-verified" viewBox="0 0 24 24" fill="currentColor"><path d="m12 1.8 2.6 2 3.3-.2.9 3.2 2.8 1.8-1.2 3.1 1.2 3.1-2.8 1.8-.9 3.2-3.3-.2-2.6 2-2.6-2-3.3.2-.9-3.2-2.8-1.8 1.2-3.1-1.2-3.1 2.8-1.8.9-3.2 3.3.2z"/><path d="m8 12.2 2.7 2.7L16.2 9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.3"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.6-4.5L3 9M3 4v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15M21 20v-5h-5"/></symbol>
    <symbol id="i-headset" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.6"/><rect x="17" y="13" width="4" height="6" rx="1.6"/><path d="M19 19a3 3 0 0 1-3 3h-3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 2.5 10 14M21.5 2.5l-7 19-4.5-7.5-7.5-4.5z"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor"/></symbol>
    <symbol id="i-plane" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 3.5 2.8 10.6c-.9.3-.9 1.5 0 1.8l4.7 1.6 1.8 5.6c.3.8 1.3 1 1.8.4l2.7-2.8 4.6 3.4c.7.5 1.6.1 1.8-.7L23 4.7c.2-.9-.7-1.6-1.5-1.2z"/></symbol>
    <symbol id="i-sim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 2.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 6 20V4a1.5 1.5 0 0 1 1-1.5z"/><rect x="9" y="11" width="6" height="6" rx="1"/></symbol>
  </defs>
</svg>
<div class="blob" aria-hidden="true"></div>

<div class="app">
  <header class="hdr" id="hdr">
    <div class="wm" id="wm">__TITLE__</div>
    <button class="icb" id="supBtn" aria-label="پشتیبانی"><svg><use href="#i-headset"/></svg></button>
    <button class="chipb" id="balBtn"><span id="bal">…</span><em>تومان</em><i><svg><use href="#i-plus"/></svg></i></button>
  </header>

  <section class="pg on" id="pg-home">
    <div class="stories" id="stH"></div>
    <article class="post">
      <div class="u"><div class="a"><div><svg><use href="#i-camera"/></svg></div></div>
        <div><b><span id="pName">نامبیکس</span><svg><use href="#i-verified"/></svg></b><small>سفارشِ آنی · بدونِ نیاز به رمز</small></div></div>
      <div class="img"><div class="big"><span><svg><use href="#i-heart"/></svg></span><span><svg><use href="#i-users"/></svg></span><span><svg><use href="#i-play"/></svg></span></div>
        <span class="tag">پیج‌های عمومی</span></div>
      <div class="act"><svg class="lk"><use href="#i-heart"/></svg><svg><use href="#i-chat"/></svg><svg><use href="#i-send"/></svg><svg class="sv"><use href="#i-bookmark"/></svg></div>
      <div class="cap"><b id="hTitle">__TITLE__</b><p id="hTag">__TAG__</p>
        <button class="cta" data-go="list"><svg><use href="#i-explore"/></svg>شروعِ سفارش</button></div>
    </article>
    <div class="stat3"><div><b id="kN">—</b><small>سرویس فعال</small></div><div><b id="kF">—</b><small>شروع از (هر ۱۰۰۰)</small></div><div><b>۲۴/۷</b><small>ثبت خودکار</small></div></div>
    <div class="hd"><h3>محبوب‌ترین‌ها</h3><button data-go="list">همه</button></div>
    <div class="grid" id="pop"></div>
    <div class="links" id="xl"></div>
  </section>

  <section class="pg" id="pg-list">
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجو: فالوور، لایک…" autocomplete="off"></div>
    <div class="stories" id="stL"></div>
    <div class="grid" id="slist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="hd" style="margin-top:10px"><h3>سفارش‌های من</h3><button id="oRef">تازه کن</button></div>
    <div id="olist"></div>
  </section>

  <section class="pg" id="pg-wallet">
    <div class="wal"><small>موجودی کیف پول</small><b><span id="wBal">…</span><em>تومان</em></b></div>
    <div class="fld"><label>مبلغِ شارژ (تومان)</label><input id="tAmt" inputmode="numeric" placeholder="مثلا ۱۰۰٬۰۰۰"></div>
    <div class="qa" id="qa"></div>
    <button class="cta" id="tGo"><svg><use href="#i-wallet"/></svg>درخواستِ شارژ</button>
    <div class="note" id="tNote">فاکتور و روشِ پرداخت داخلِ ربات برایتان فرستاده می‌شود؛ بعد از واریز، موجودی خودکار یا با تاییدِ پشتیبانی شارژ می‌شود.</div>
    <div class="links"><button id="supBtn2"><svg><use href="#i-headset"/></svg><span>پشتیبانی</span></button></div>
  </section>
</div>

<nav class="nav" id="nav"><div>
  <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
  <button data-go="list"><svg><use href="#i-explore"/></svg>سرویس‌ها</button>
  <button data-go="orders"><svg><use href="#i-receipt"/></svg>سفارش‌ها<span class="bd" id="ordN"></span></button>
  <button data-go="wallet"><svg><use href="#i-wallet"/></svg>کیف پول</button>
</div></nav>

<div class="ov" id="ov"></div>
<div class="sh" id="sh"><div class="grab"></div><div id="shB"></div></div>
<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var D = document;
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
  g.innerHTML = ico('camera') + '<b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p>' +
    (B.bot ? '<button class="cta" style="max-width:260px">رفتن به ربات</button>' : '');
  D.body.appendChild(g);
  var b = g.querySelector('button'); if (b) b.onclick = function(){ openLink('https://t.me/' + B.bot); };
}

var CATS = B.cats || [], ITEMS = B.items || [], CAT = {}, ITEM = {};
CATS.forEach(function(c, k){ CAT[c.id] = c; c.tone = 'c' + ((k % 4) + 1); });
ITEMS.forEach(function(i){ ITEM[i.i] = i; i.k = String(i.n || '').toLowerCase(); });
var HINT = { followers: ['آیدی یا لینکِ پیج', '@mypage'], likes: ['لینکِ پست', 'instagram.com/p/XXXX'],
  views: ['لینکِ ریلز یا ویدیو', 'instagram.com/reel/XXXX'], comments: ['لینکِ پست', 'instagram.com/p/XXXX'],
  story: ['آیدیِ پیج', '@mypage'], saves: ['لینکِ پست', 'instagram.com/p/XXXX'], other: ['لینک یا آیدی', '@mypage'] };
var S = { page: '', stack: [], bal: 0, cat: '', q: '', orders: null, cur: null, sheet: false, poll: null };
var U = tgUser() || {};

function setBal(v){ if (v == null || isNaN(Number(v))) return; S.bal = Number(v); $('bal').textContent = fa(S.bal); $('wBal').textContent = fa(S.bal); }

var PAGES = ['home', 'list', 'orders', 'wallet'];
function go(p, back){
  if (PAGES.indexOf(p) < 0) p = 'home';
  if (p === S.page) { window.scrollTo(0, 0); return; }
  if (!back && S.page) S.stack.push(S.page);
  S.page = p;
  PAGES.forEach(function(x){ $('pg-' + x).classList.toggle('on', x === p); });
  [].forEach.call(D.querySelectorAll('#nav button'), function(b){ b.classList.toggle('on', b.getAttribute('data-go') === p); });
  window.scrollTo(0, 0);
  clearTimeout(S.poll);
  if (p === 'list') drawList();
  if (p === 'orders') loadOrders();
  if (p === 'wallet') drawWallet();
  backBtn();
}
function goBack(){ if (S.sheet) { closeSheet(); return; } var p = S.stack.pop(); go(p || 'home', true); }
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
window.addEventListener('scroll', function(){ $('hdr').classList.toggle('sc', window.scrollY > 6); }, { passive: true });

function stories(active){
  return '<button class="story' + (active === '' ? ' on' : '') + '" data-go="list" data-cat=""><div class="rg"><div class="in"><div>' + ico('explore') + '</div></div></div><small>همه</small></button>' +
    CATS.map(function(c){
      return '<button class="story' + (active === c.id ? ' on' : '') + '" data-go="list" data-cat="' + esc(c.id) + '"><div class="rg"><div class="in"><div>' + ico(c.ic) + '</div></div></div><small>' + esc(c.n) + '</small></button>';
    }).join('');
}
function tile(i){
  var c = CAT[i.c] || {};
  return '<button class="tile ' + (c.tone || 'c1') + '" data-sv="' + esc(i.i) + '">' + (i.r ? '<span class="rf">ضمانت</span>' : '') +
    '<span class="ic">' + ico(c.ic || 'spark') + '</span><b>' + esc(i.n) + '</b><small>' + fa(i.mn) + ' تا ' + fa(i.mx) + '</small>' +
    '<span class="ft"><span><b>' + fa(i.p) + '</b> تومان<br>هر ۱۰۰۰ تا</span><i>' + ico('plus') + '</i></span></button>';
}
function drawHome(){
  $('stH').innerHTML = stories(null);
  $('kN').textContent = fa(ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  $('kF').textContent = min ? fa(min) : '—';
  if (B.bot) $('pName').textContent = '@' + B.bot;
  var pop = [];
  CATS.forEach(function(c){ var f = ITEMS.filter(function(i){ return i.c === c.id; }).sort(function(a, b){ return a.p - b.p; })[0]; if (f) pop.push(f); });
  ITEMS.slice().sort(function(a, b){ return a.p - b.p; }).forEach(function(i){ if (pop.length < 6 && pop.indexOf(i) < 0) pop.push(i); });
  $('pop').innerHTML = pop.length ? pop.slice(0, 6).map(tile).join('') :
    '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';
  var xl = '';
  if ((B.links || {}).tg) xl += '<button data-open="tg">' + ico('plane') + '<span>خدمات تلگرام</span></button>';
  if ((B.links || {}).num) xl += '<button data-open="num">' + ico('sim') + '<span>شماره مجازی تلگرام</span></button>';
  $('xl').innerHTML = xl;
}
$('xl').addEventListener('click', function(ev){ var b = ev.target.closest('[data-open]'); if (b) { tap(); openApp(B.links[b.getAttribute('data-open')]); } });

var LK = '';
function drawList(force){
  var key = S.cat + '|' + S.q;
  if (!force && key === LK && $('slist').children.length) return;
  LK = key;
  $('stL').innerHTML = stories(S.cat);
  var q = S.q.trim().toLowerCase();
  var l = ITEMS.filter(function(i){ return (!S.cat || i.c === S.cat) && (!q || i.k.indexOf(q) >= 0); });
  $('slist').innerHTML = l.length ? l.map(tile).join('') :
    '<div class="emp" style="grid-column:1/-1">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>';
}
var QT;
$('q').addEventListener('input', function(){ var v = this.value; clearTimeout(QT); QT = setTimeout(function(){ S.q = v; drawList(true); }, 140); });
D.addEventListener('click', function(ev){ var b = ev.target.closest ? ev.target.closest('[data-sv]') : null; if (b) { tap(); openOrder(b.getAttribute('data-sv')); } });

function total(i, q){ return Math.max(1, Math.ceil(i.p * q / 1000 - 1e-9)); }
function niceQty(i){ var c = [1000, 500, 100, 5000, 10000]; for (var k = 0; k < c.length; k++) if (c[k] >= i.mn && c[k] <= i.mx) return c[k]; return i.mn; }
function linkOk(v){ v = v.trim(); return /^@?[A-Za-z0-9._]{1,30}$/.test(v) || /^(https?:\/\/)?(www\.|m\.)?(instagram\.com|instagr\.am)\/\S+$/i.test(v); }
function openOrder(id){
  var i = ITEM[id]; if (!i) return;
  var c = CAT[i.c] || {}, h = HINT[i.c] || HINT.other;
  S.cur = i;
  var chips = [i.mn, 1000, 5000, 10000, 50000, i.mx].filter(function(v, k, a){ return v >= i.mn && v <= i.mx && a.indexOf(v) === k; })
    .sort(function(a, b){ return a - b; }).slice(0, 5);
  $('shB').innerHTML =
    '<div class="st"><span class="ic">' + ico(c.ic || 'spark') + '</span><div style="flex:1;min-width:0"><b>' + esc(i.n) + '</b><small>' +
      fa(i.p) + ' تومان برای هر ۱۰۰۰ تا · حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx) + '</small></div><button class="x" id="shX">' + ico('x') + '</button></div>' +
    '<div class="fld"><label>' + esc(h[0]) + '</label><input id="oLink" class="ltr" placeholder="' + esc(h[1]) + '" autocomplete="off" autocapitalize="off" spellcheck="false"><small id="oLinkH">پیج باید عمومی (Public) باشد؛ رمز لازم نیست.</small></div>' +
    '<div class="fld"><label>تعداد</label><div class="qrow"><button id="qM" aria-label="کم">−</button><input id="oQty" inputmode="numeric"><button id="qP" aria-label="زیاد">+</button></div>' +
    '<div class="qchips" id="qC">' + chips.map(function(v){ return '<button data-q="' + v + '">' + fa(v) + '</button>'; }).join('') + '</div></div>' +
    '<div class="sum"><div><span>قیمتِ هر ۱۰۰۰ تا</span><b>' + fa(i.p) + ' تومان</b></div><div><span>موجودیِ شما</span><b id="oBal">' + fa(S.bal) + ' تومان</b></div>' +
    '<div class="t"><span>مبلغِ کل</span><b id="oTot">—</b></div></div>' +
    '<button class="cta" id="oGo">' + ico('send') + '<span id="oGoT">پرداخت و ثبتِ سفارش</span></button>';
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
    if (v && !linkOk(v)) { hs.textContent = 'مثل ' + h[1] + ' یا لینکِ کاملِ اینستاگرام بفرستید.'; hs.className = 'er'; }
    else { hs.textContent = 'پیج باید عمومی (Public) باشد؛ رمز لازم نیست.'; hs.className = ''; } };
  $('shX').onclick = function(){ tap(); closeSheet(); };
  $('oGo').onclick = function(){
    var n = q(), t = upd(), link = $('oLink').value.trim();
    if (n < i.mn || n > i.mx) { toast('تعداد باید بین ' + fa(i.mn) + ' و ' + fa(i.mx) + ' باشد.'); return; }
    if (t > S.bal) { closeSheet(); WAL.pre = t - S.bal; go('wallet'); return; }
    if (!linkOk(link)) { toast('لینک یا آیدیِ درست وارد کنید.'); $('oLink').focus(); return; }
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
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('receipt') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="cta" data-go="list" style="margin-top:14px">' + ico('explore') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o){
    return '<div class="or ' + esc(o.st) + '"><div class="h"><div><b>' + esc(o.n) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
      '<span class="pill ' + esc(o.st) + '">' + esc(o.sx) + '</span></div>' +
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
  try { TG.setHeaderColor && TG.setHeaderColor('#FFFBF8'); TG.setBackgroundColor && TG.setBackgroundColor('#FFFBF8'); TG.setBottomBarColor && TG.setBottomBarColor('#FFFFFF'); } catch(e){}
  try { if (TG.BackButton) TG.BackButton.onClick(goBack); } catch(e){}
  backBtn();
}
if (TG) tgSetup();
else { var tries = 0, iv = setInterval(function(){
  if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; U = tgUser() || U; tgSetup(); }
  else if (++tries > 40) clearInterval(iv); }, 100); }
D.addEventListener('visibilitychange', function(){ if (!D.hidden && S.page === 'orders') loadOrders(); });

drawHome();
var WANT = (function(){ try { return String(new URLSearchParams(location.search).get('p') || ''); } catch(e){ return ''; } })();
go(WANT === 'orders' || WANT === 'wallet' || WANT === 'list' ? WANT : 'home');
api('me', {}, function(j){ setBal(j.balance); }, function(){});
})();
</script>
</body>
</html>
HTML;
}
