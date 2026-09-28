<?php

function svTplIg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#0A0510">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
__FONT__
<style>
:root{
  --bg:#0A0510;--glass:rgba(38,14,44,.5);--glass2:rgba(52,18,58,.6);--solid:#170A1C;
  --line:rgba(255,255,255,.09);--line2:rgba(236,72,153,.32);
  --ink:#FFF3F9;--dim:#C0A6BF;--dim2:#846C86;
  --o:#F58529;--p:#DD2A7B;--pk:#FF5FA2;--v:#8134AF;--b:#515BD4;--y:#FEDA75;--ok:#4ADE80;--warn:#FBBF24;--red:#FB7185;
  --grad:linear-gradient(45deg,#F58529 0%,#DD2A7B 45%,#8134AF 78%,#515BD4 100%);
  --grad2:linear-gradient(135deg,#FEDA75 0%,#FA7E1E 30%,#D62976 60%,#962FBF 85%,#4F5BD5 100%);
  --ring:conic-gradient(#FEDA75,#FA7E1E,#D62976,#962FBF,#4F5BD5,#962FBF,#D62976,#FA7E1E,#FEDA75);
  --safe:env(safe-area-inset-bottom,0px);--top:0px;color-scheme:dark;
  --dots:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cg fill='%23FFD1E6'%3E%3Ccircle cx='24' cy='40' r='1.1' opacity='.8'/%3E%3Ccircle cx='98' cy='14' r='.8' opacity='.6'/%3E%3Ccircle cx='162' cy='72' r='1.3' opacity='.5'/%3E%3Ccircle cx='252' cy='28' r='.9' opacity='.8'/%3E%3Ccircle cx='282' cy='118' r='1' opacity='.55'/%3E%3Ccircle cx='198' cy='162' r='.8' opacity='.7'/%3E%3Ccircle cx='58' cy='142' r='1' opacity='.5'/%3E%3Ccircle cx='122' cy='212' r='1.2' opacity='.6'/%3E%3Ccircle cx='28' cy='252' r='.8' opacity='.7'/%3E%3Ccircle cx='232' cy='238' r='1.1' opacity='.6'/%3E%3Ccircle cx='172' cy='288' r='.9' opacity='.5'/%3E%3Ccircle cx='88' cy='282' r='.7' opacity='.8'/%3E%3C/g%3E%3Cg fill='%23FEDA75'%3E%3Ccircle cx='142' cy='118' r='1' opacity='.6'/%3E%3Ccircle cx='268' cy='198' r='1' opacity='.5'/%3E%3Ccircle cx='52' cy='92' r='.9' opacity='.6'/%3E%3C/g%3E%3C/svg%3E")
}
html.scr .mesh *{animation-play-state:paused!important}
html.fs{--top:calc(var(--tg-content-safe-area-inset-top,var(--tg-safe-area-inset-top,34px)) + 46px)}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg);color:var(--ink);min-height:100%}
body{font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:13px;line-height:1.7;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
input{font-family:inherit;-webkit-user-select:text;user-select:text}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
svg{display:block}
.hid{display:none!important}
.ltr{direction:ltr;unicode-bidi:isolate}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes ping{0%{transform:scale(1);opacity:.7}80%,100%{transform:scale(2.8);opacity:0}}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-6px,0)}}
@keyframes shine{0%,72%{transform:translate3d(-130%,0,0) skewX(-20deg)}100%{transform:translate3d(360%,0,0) skewX(-20deg)}}
@keyframes up{from{opacity:0;transform:translate3d(0,14px,0) scale(.98)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}
@keyframes beat{0%,100%{transform:scale(1)}14%{transform:scale(1.22)}28%{transform:scale(1)}42%{transform:scale(1.12)}56%{transform:scale(1)}}
.gb{position:relative}
.gb:before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;pointer-events:none;z-index:3;
  background:linear-gradient(120deg,rgba(254,218,117,.65),rgba(245,133,41,.6),rgba(221,42,123,.6),rgba(129,52,175,.55),rgba(81,91,212,.7));
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude}

.mesh{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;overflow:clip;contain:strict;
  background:var(--dots) 0 0/300px 300px repeat,radial-gradient(120vw 70vh at 50% -18%,#2B0D30 0%,transparent 70%),linear-gradient(180deg,#0A0510 0%,#130619 55%,#0A0510 100%)}
.mesh>*{position:absolute;display:block}
.mesh .m{border-radius:50%;will-change:transform}
.mesh .m1{width:95vw;height:95vw;left:-35vw;top:-30vw;background:radial-gradient(closest-side,rgba(245,133,41,.34),transparent);animation:m1 19s ease-in-out infinite alternate}
.mesh .m2{width:105vw;height:105vw;right:-45vw;top:8vh;background:radial-gradient(closest-side,rgba(221,42,123,.34),transparent);animation:m2 23s ease-in-out infinite alternate}
.mesh .m3{width:100vw;height:100vw;left:-35vw;top:52vh;background:radial-gradient(closest-side,rgba(129,52,175,.34),transparent);animation:m3 26s ease-in-out infinite alternate}
@keyframes m1{to{transform:translate3d(30vw,22vh,0) scale(1.2)}}
@keyframes m2{to{transform:translate3d(-28vw,26vh,0) scale(.85)}}
@keyframes m3{to{transform:translate3d(32vw,-20vh,0) scale(1.15)}}
.mesh .bm{left:-60%;width:220%;top:30vh;height:16vh;opacity:.7;will-change:transform;
  background:radial-gradient(50% 50% at 50% 50%,rgba(255,95,162,.16),rgba(129,52,175,.08) 45%,transparent 72%);
  animation:bm 13s ease-in-out infinite alternate}
@keyframes bm{from{transform:rotate(22deg) translate3d(-8%,0,0)}to{transform:rotate(16deg) translate3d(8%,-6vh,0)}}
.mesh .ht{bottom:-40px;width:18px;height:18px;color:rgba(255,95,162,.6);opacity:0;will-change:transform,opacity;animation:hup 15s linear infinite}
.mesh .ht svg{width:100%;height:100%;filter:drop-shadow(0 0 6px rgba(255,95,162,.7))}
.mesh .ht:nth-of-type(1){left:8%}
.mesh .ht:nth-of-type(2){left:28%;width:12px;height:12px;animation-duration:19s;animation-delay:-5s;color:rgba(254,218,117,.6)}
.mesh .ht:nth-of-type(3){left:52%;width:22px;height:22px;animation-duration:17s;animation-delay:-9s}
.mesh .ht:nth-of-type(4){left:72%;width:14px;height:14px;animation-duration:21s;animation-delay:-2s;color:rgba(167,139,250,.65)}
.mesh .ht:nth-of-type(5){left:88%;animation-duration:16s;animation-delay:-12s;color:rgba(245,133,41,.6)}
.mesh .ht:nth-of-type(6){left:40%;width:10px;height:10px;animation-duration:24s;animation-delay:-15s}
@keyframes hup{0%{transform:translate3d(0,0,0) scale(.6) rotate(-12deg);opacity:0}8%{opacity:.85}50%{transform:translate3d(20px,-55vh,0) scale(1) rotate(10deg)}88%{opacity:.5}100%{transform:translate3d(-14px,-112vh,0) scale(.8) rotate(-8deg);opacity:0}}
.mesh .sp{width:4px;height:4px;border-radius:50%;background:#fff;box-shadow:0 0 8px 2px rgba(255,95,162,.8);opacity:.2;animation:twk 3.2s ease-in-out infinite}
.mesh .sp:nth-of-type(1){left:18%;top:14%}
.mesh .sp:nth-of-type(2){left:80%;top:24%;animation-delay:-1s;box-shadow:0 0 8px 2px rgba(254,218,117,.9)}
.mesh .sp:nth-of-type(3){left:46%;top:44%;animation-delay:-2s}
.mesh .sp:nth-of-type(4){left:12%;top:70%;animation-delay:-.5s;box-shadow:0 0 8px 2px rgba(129,52,175,.9)}
.mesh .sp:nth-of-type(5){left:66%;top:84%;animation-delay:-1.5s}
@keyframes twk{0%,100%{opacity:.15;transform:scale(.6)}50%{opacity:1;transform:scale(1.3)}}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:calc(var(--top) + 8px) 14px calc(104px + var(--safe));overflow-x:clip}

.hd0{position:sticky;top:calc(var(--top) + 6px);z-index:30;margin-bottom:10px}
.hd0:before{content:"";position:fixed;left:0;right:0;top:0;height:calc(var(--top) + 6px);z-index:-1;pointer-events:none;
  background:var(--dots) 0 0/300px 300px repeat,
    radial-gradient(circle 47vw at 12vw 17vw,rgba(245,133,41,.34),transparent) 0 0/100vw 100vh no-repeat,
    radial-gradient(circle 52vw at 92vw calc(8vh + 52vw),rgba(221,42,123,.34),transparent) 0 0/100vw 100vh no-repeat,
    radial-gradient(120vw 70vh at 50% -18%,#2B0D30 0%,transparent 70%) 0 0/100vw 100vh no-repeat,
    linear-gradient(180deg,#0A0510 0%,#130619 55%,#0A0510 100%) 0 0/100vw 100vh no-repeat}
.hdr{display:flex;align-items:center;gap:10px;padding:8px 9px;border-radius:22px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),#18091E;
  box-shadow:0 18px 40px -22px #000,inset 0 1px 0 rgba(255,255,255,.08)}
.ava{position:relative;width:42px;height:42px;flex:0 0 auto;border-radius:50%;padding:2.5px;overflow:hidden;overflow:clip}
.ava:before{content:"";position:absolute;inset:-25%;background:var(--ring);animation:spin 4s linear infinite}
.ava span{position:relative;display:grid;place-items:center;width:100%;height:100%;border-radius:50%;overflow:hidden;overflow:clip;
  background:#1A0B20;border:2px solid #1A0B20;font-weight:900;font-size:15px;color:var(--pk)}
.ava span img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim);white-space:nowrap;overflow:hidden}
.who small span{overflow:hidden;text-overflow:ellipsis}
.dot{position:relative;display:inline-block;flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.dot:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 2s ease-out infinite}
.bal{display:flex;align-items:center;gap:6px;height:36px;padding:0 5px 0 11px;border-radius:18px;font-weight:900;font-size:12px;
  background:linear-gradient(135deg,rgba(221,42,123,.22),rgba(129,52,175,.16));border:1px solid var(--line2)}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.bal i{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff;box-shadow:0 4px 12px -3px rgba(221,42,123,.9)}
.bal i svg{width:14px;height:14px}
.ib{width:36px;height:36px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.05)}
.ib svg{width:18px;height:18px;color:var(--pk)}

.pg{display:none}
.pg.on{display:block}
.pg.on>*{animation:up .5s cubic-bezier(.2,.85,.25,1) backwards}
.pg.on>:nth-child(2){animation-delay:.05s}
.pg.on>:nth-child(3){animation-delay:.1s}
.pg.on>:nth-child(4){animation-delay:.15s}
.pg.on>:nth-child(n+5){animation-delay:.2s}

.wmk{display:flex;align-items:center;gap:8px;margin:4px 2px 0}
.wmk b{font-size:20px;font-weight:900;letter-spacing:-.3px;background:linear-gradient(90deg,#FEDA75,#FA7E1E 25%,#FF5FA2 55%,#C084FC 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.wmk span{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:12px;font-size:10px;font-weight:800;color:var(--ok);background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25)}

.stories{display:flex;gap:12px;overflow-x:auto;scrollbar-width:none;margin:6px -14px 0;padding:6px 14px 8px}
.stories::-webkit-scrollbar{display:none}
.story{flex:0 0 auto;width:68px;text-align:center;animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 55ms)}
.story .rg{position:relative;width:66px;height:66px;margin:0 auto;border-radius:50%;padding:3px;overflow:hidden;overflow:clip;box-shadow:0 10px 22px -12px rgba(221,42,123,.8)}
.story .rg:before{content:"";position:absolute;inset:-20%;background:var(--ring)}
.story.on .rg:before{animation:spin 2.6s linear infinite}
.story .in{position:relative;width:100%;height:100%;border-radius:50%;background:#140818;padding:3px}
.story .in div{width:100%;height:100%;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.06);color:var(--pk)}
.story.on .in div{background:var(--grad);color:#fff}
.story .in svg{width:24px;height:24px}
.story small{display:block;margin-top:5px;font-size:10.5px;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.post{margin-top:10px;border-radius:26px;overflow:hidden;overflow:clip;isolation:isolate;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),var(--glass);box-shadow:0 30px 50px -30px rgba(221,42,123,.6)}
.post .u{display:flex;align-items:center;gap:9px;padding:11px 13px}
.post .u .a{position:relative;width:36px;height:36px;border-radius:50%;padding:2px;overflow:hidden;overflow:clip;flex:0 0 auto}
.post .u .a:before{content:"";position:absolute;inset:-25%;background:var(--ring);animation:spin 5s linear infinite}
.post .u .a div{position:relative;width:100%;height:100%;border-radius:50%;border:2px solid #1A0B20;background:var(--grad);display:grid;place-items:center;color:#fff}
.post .u .a svg{width:16px;height:16px}
.post .u b{font-size:12.5px;font-weight:800;display:flex;align-items:center;gap:4px}
.post .u b svg{width:14px;height:14px;color:#3897F0}
.post .u small{display:block;font-size:10px;color:var(--dim)}
.post .img{position:relative;height:220px;overflow:hidden;overflow:clip;display:grid;place-items:center;isolation:isolate}
.post .img .sw{position:absolute;z-index:-2;top:0;bottom:0;right:0;width:200%;will-change:transform;
  background:linear-gradient(100deg,#F58529 0%,#DD2A7B 25%,#8134AF 50%,#515BD4 62%,#8134AF 75%,#DD2A7B 88%,#F58529 100%);animation:slw 14s ease-in-out infinite alternate}
@keyframes slw{to{transform:translate3d(50%,0,0)}}
.post .img:before{content:"";position:absolute;z-index:-1;inset:0;background:radial-gradient(60% 60% at 28% 26%,rgba(255,255,255,.26),transparent 70%),linear-gradient(180deg,transparent 45%,rgba(10,5,16,.4))}
.post .img:after{content:"";position:absolute;z-index:-1;width:210px;height:210px;border-radius:50%;border:26px solid rgba(255,255,255,.1);bottom:-100px;right:-60px}
.post .img .big{position:relative;display:flex;gap:10px;align-items:flex-end}
.post .img .big span{width:62px;height:62px;border-radius:20px;display:grid;place-items:center;color:#fff;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.38);box-shadow:0 14px 26px -14px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.4);
  animation:bob 3.4s ease-in-out infinite}
.post .img .big span:nth-child(2){width:78px;height:78px;border-radius:24px;animation-delay:-.8s}
.post .img .big span:nth-child(3){animation-delay:-1.6s}
.post .img .big svg{width:30px;height:30px}
.post .img .pop{position:absolute;width:96px;height:96px;color:#fff;opacity:0;filter:drop-shadow(0 8px 24px rgba(0,0,0,.35));animation:pop 6s ease-in-out 1.2s infinite}
@keyframes pop{0%,58%{opacity:0;transform:scale(.2)}64%{opacity:1;transform:scale(1.18)}70%{transform:scale(.94)}78%{opacity:1;transform:scale(1)}90%,100%{opacity:0;transform:translate3d(0,-26px,0) scale(1.3)}}
.post .img .tag{position:absolute;bottom:12px;right:12px;padding:4px 10px;border-radius:14px;background:rgba(10,5,16,.45);color:#fff;font-size:10.5px;font-weight:700;
  border:1px solid rgba(255,255,255,.2)}
.post .act{display:flex;align-items:center;gap:14px;padding:11px 13px 2px}
.post .act svg{width:23px;height:23px}
.post .act .sv{margin-right:auto}
.post .act .lk{color:#FF3B6B;animation:beat 1.8s ease-in-out infinite}
.post .cap{padding:4px 13px 14px}
.post .cap b{font-size:14px;font-weight:900}
.post .cap p{font-size:11.5px;color:var(--dim);margin-top:2px}
.cta{position:relative;overflow:hidden;overflow:clip;margin-top:12px;width:100%;height:48px;border-radius:15px;background:var(--grad);color:#fff;font-weight:900;font-size:13.5px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 14px 26px -14px rgba(221,42,123,.95)}
.cta:after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);animation:shine 4s ease-in-out infinite}
.cta svg{width:18px;height:18px}
.cta[disabled]{opacity:.45;filter:grayscale(.4)}
.cta[disabled]:after{display:none}
.cta.gh{background:var(--glass);color:var(--ink);border:1px solid var(--line2);box-shadow:none}
.cta.gh:after{display:none}

.stat3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.stat3 div{padding:10px 8px;border-radius:17px;text-align:center;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.stat3 b{display:block;font-size:15px;font-weight:900;background:linear-gradient(90deg,#FEDA75,#FF5FA2);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat3 small{font-size:10px;color:var(--dim);white-space:nowrap}

.hd{display:flex;align-items:center;margin:20px 2px 10px}
.hd h3{flex:1;font-size:15px;font-weight:900;display:flex;align-items:center;gap:7px}
.hd h3 i{width:8px;height:8px;border-radius:50%;background:var(--grad2);box-shadow:0 0 10px rgba(255,95,162,.9)}
.hd button{font-size:11.5px;font-weight:800;color:var(--pk)}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tile{position:relative;overflow:hidden;overflow:clip;isolation:isolate;display:flex;flex-direction:column;text-align:right;padding:12px;border-radius:22px;min-height:182px;width:100%;
  background:linear-gradient(165deg,rgba(255,255,255,.08),rgba(255,255,255,.015) 55%),var(--glass);border:1px solid var(--line);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.07),0 18px 30px -24px #000;
  animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 55ms);transition:transform .15s}
.tile:before{content:"";position:absolute;z-index:-1;width:130px;height:130px;border-radius:50%;right:-40px;top:-50px;
  background:radial-gradient(closest-side,rgba(221,42,123,.36),transparent)}
.tile:after{content:"";position:absolute;z-index:2;top:-20%;bottom:-20%;left:0;width:34%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);transform:translate3d(-130%,0,0) skewX(-20deg);
  animation:shine 7s ease-in-out infinite;animation-delay:calc(var(--i,0) * .8s)}
.tile:nth-child(n+4):after{display:none}
.tile:nth-child(n+5) .ft i:after,.tile:nth-child(n+7) small i:after{display:none}
.tile .ic{position:relative;width:46px;height:46px;border-radius:15px;display:grid;place-items:center;color:#fff;background:var(--grad);
  box-shadow:0 10px 20px -10px rgba(221,42,123,.9)}
.tile .ic svg{width:22px;height:22px}
.tile.c1 .ic{background:linear-gradient(135deg,#F58529,#DD2A7B)}
.tile.c2 .ic{background:linear-gradient(135deg,#DD2A7B,#8134AF)}
.tile.c3 .ic{background:linear-gradient(135deg,#8134AF,#515BD4)}
.tile.c4 .ic{background:linear-gradient(135deg,#FEDA75,#F58529);color:#3A1500}
.tile.c1:before{background:radial-gradient(closest-side,rgba(245,133,41,.34),transparent)}
.tile.c3:before{background:radial-gradient(closest-side,rgba(81,91,212,.4),transparent)}
.tile.c4:before{background:radial-gradient(closest-side,rgba(254,218,117,.26),transparent)}
.tile b{margin-top:10px;font-size:12px;font-weight:800;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile small{display:flex;align-items:center;gap:5px;font-size:9.5px;color:var(--dim2);margin-top:3px}
.tile small i{position:relative;width:6px;height:6px;border-radius:50%;background:var(--ok);flex:0 0 auto}
.tile small i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.8s ease-out infinite}
.tile .ft{margin-top:auto;padding-top:8px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .ft span{font-size:10px;color:var(--dim);line-height:1.5}
.tile .ft span b{display:inline;margin:0;font-size:14.5px;font-weight:900;color:var(--ink)}
.tile .ft i{position:relative;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto;
  box-shadow:0 8px 16px -8px rgba(221,42,123,.9)}
.tile .ft i:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,95,162,.6);animation:ping 2.4s ease-out infinite}
.tile .ft i svg{width:15px;height:15px}
.tile .rf{position:absolute;top:12px;left:12px;font-size:9px;font-weight:800;padding:2px 7px;border-radius:8px;background:rgba(74,222,128,.12);color:var(--ok);border:1px solid rgba(74,222,128,.25)}
.tile:active{transform:scale(.98)}

.srch{display:flex;align-items:center;gap:8px;height:46px;margin-top:2px;padding:0 14px;border-radius:23px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),var(--glass);border:1px solid var(--line2)}
.srch svg{width:17px;height:17px;color:var(--pk)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}
.srch input::placeholder{color:var(--dim2)}

.emp{text-align:center;padding:30px 18px;border-radius:22px;border:1px dashed var(--line2);color:var(--dim);
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass)}
.emp>svg{width:42px;height:42px;margin:0 auto 10px;color:var(--pk);animation:bob 3s ease-in-out infinite}
.emp>b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{position:relative;overflow:hidden;overflow:clip;height:86px;border-radius:20px;margin-bottom:10px;background:var(--glass);border:1px solid var(--line)}
.sk:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,95,162,.1),transparent);animation:skl 1.2s linear infinite}
@keyframes skl{from{transform:translate3d(-100%,0,0)}to{transform:translate3d(100%,0,0)}}

.or{position:relative;overflow:hidden;overflow:clip;border-radius:20px;padding:13px 16px 13px 13px;margin-bottom:10px;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line);
  animation:up .45s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 50ms)}
.or:before{content:"";position:absolute;right:0;top:0;bottom:0;width:4px;background:var(--grad2)}
.or.done:before{background:var(--ok)}.or.partial:before,.or.check:before{background:var(--warn)}.or.canceled:before,.or.failed:before{background:var(--red)}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(255,95,162,.14);color:var(--pk)}
.pill.run:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;animation:twk 1.4s ease-in-out infinite}
.pill.done{background:rgba(74,222,128,.12);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(251,191,36,.14);color:var(--warn)}
.pill.canceled,.pill.failed{background:rgba(251,113,133,.14);color:var(--red)}
.prog{height:7px;border-radius:7px;background:rgba(255,255,255,.08);margin:11px 0 7px;overflow:hidden;overflow:clip}
.prog i{position:relative;display:block;height:100%;border-radius:7px;background:var(--grad);overflow:hidden;overflow:clip;transition:width .6s ease}
.prog.run i:after{content:"";position:absolute;top:0;bottom:0;left:0;width:calc(100% + 17px);
  background:repeating-linear-gradient(-45deg,rgba(255,255,255,.3) 0 6px,transparent 6px 12px);animation:strp .8s linear infinite}
@keyframes strp{to{transform:translate3d(-16.97px,0,0)}}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:#7DB9FF;direction:ltr;overflow:hidden;overflow:clip}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}

.wal{position:relative;overflow:hidden;overflow:clip;isolation:isolate;margin-top:2px;border-radius:24px;padding:16px 18px 18px;color:#fff;
  box-shadow:0 24px 44px -24px rgba(221,42,123,.9),inset 0 1px 0 rgba(255,255,255,.25)}
.wal .sw{position:absolute;z-index:-2;inset:0;background:linear-gradient(125deg,#F58529 0%,#DD2A7B 38%,#8134AF 72%,#515BD4 100%)}
.wal:before{content:"";position:absolute;z-index:-1;inset:0;background:radial-gradient(70% 90% at 20% 10%,rgba(255,255,255,.22),transparent 60%),linear-gradient(180deg,rgba(10,5,16,.05),rgba(10,5,16,.35))}
.wal:after{content:"";position:absolute;z-index:-1;top:-50%;bottom:-50%;left:-30%;width:40%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent);animation:wsh 5.5s ease-in-out infinite}
@keyframes wsh{0%,45%{transform:translate3d(0,0,0) skewX(-20deg)}100%{transform:translate3d(420%,0,0) skewX(-20deg)}}
.wal .wm{position:absolute;z-index:-1;left:-16px;bottom:-22px;width:120px;height:120px;color:rgba(255,255,255,.12);transform:rotate(-12deg)}
.wal .tp{display:flex;align-items:center;justify-content:space-between}
.wal .tp span{font-size:11px;font-weight:800;opacity:.9}
.wal .chip{width:36px;height:27px;border-radius:7px;background:linear-gradient(135deg,#FFF1C1,#FEDA75 50%,#F59E0B);box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)}
.wal small{display:block;margin-top:16px;opacity:.85;font-size:11px}
.wal b{display:block;font-size:30px;font-weight:900;line-height:1.3;text-shadow:0 4px 18px rgba(0,0,0,.25)}
.wal b em{font-style:normal;font-size:12px;opacity:.85;font-weight:700;margin-right:4px}
.wal .ft{margin-top:6px;font-size:10px;opacity:.75}
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
.qa button{height:42px;border-radius:21px;background:var(--glass);border:1px solid var(--line);font-weight:800;font-size:12px;transition:all .2s}
.qa button.on{border-color:var(--pk);color:#fff;background:linear-gradient(135deg,rgba(221,42,123,.35),rgba(129,52,175,.3));box-shadow:0 0 0 3px rgba(255,95,162,.14)}
.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:50px;padding:0 16px;border-radius:16px;background:rgba(10,5,16,.55);border:1px solid var(--line2);color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input::placeholder{color:var(--dim2)}
.fld input:focus{border-color:var(--pk);box-shadow:0 0 0 3px rgba(255,95,162,.16)}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.pay{margin-top:12px;border-radius:16px;padding:2px 12px;background:var(--glass);border:1px solid var(--line)}
.pay .r{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;font-size:11.5px;color:var(--dim)}
.pay .r+.r{border-top:1px dashed var(--line)}
.pay .r b{color:var(--ink);font-weight:800}
.pay .r b.g{color:var(--ok)}
.cpy{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 10px;border-radius:16px;background:rgba(221,42,123,.14);border:1px solid var(--line2);font-weight:800;font-size:12px}
.cpy svg{width:14px;height:14px;color:var(--pk)}
.warn{display:flex;align-items:flex-start;gap:8px;margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(251,191,36,.08);
  border:1px solid rgba(251,191,36,.25);color:#FDE68A;font-size:11px;line-height:1.9}
.warn svg{width:17px;height:17px;flex:0 0 auto;margin-top:2px}
.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(255,95,162,.07);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}

.links{display:grid;gap:9px;margin-top:14px}
.xl{position:relative;overflow:hidden;overflow:clip;display:flex;align-items:center;gap:12px;min-height:62px;padding:10px 12px;border-radius:20px;text-align:right;
  background:linear-gradient(90deg,rgba(255,255,255,.07),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.xl .ic{width:42px;height:42px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;color:#fff;box-shadow:0 10px 20px -12px #000}
.xl .ic svg{width:21px;height:21px}
.xl.tg .ic{background:linear-gradient(135deg,#2AABEE,#5EEAD4);color:#03101F}
.xl.num .ic{background:linear-gradient(135deg,#22C55E,#2563EB)}
.xl.sup .ic{background:rgba(255,95,162,.16);color:var(--pk)}
.xl span{flex:1;min-width:0;font-weight:800;font-size:13px}
.xl span small{display:block;font-size:10px;color:var(--dim);font-weight:600}
.xl .ch{width:18px;height:18px;color:var(--dim);animation:nud 1.8s ease-in-out infinite}
@keyframes nud{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(-4px,0,0)}}

.nav{position:fixed;left:12px;right:12px;bottom:calc(10px + var(--safe));z-index:30;max-width:456px;margin:0 auto;border-radius:26px;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02)),#16081C;
  box-shadow:0 22px 44px -18px #000,inset 0 1px 0 rgba(255,255,255,.09)}
.nav>div{position:relative;display:grid;grid-template-columns:repeat(4,1fr);height:64px}
.nav .ind{position:absolute;top:8px;bottom:8px;right:0;width:25%;display:flex;justify-content:center;pointer-events:none;transition:transform .42s cubic-bezier(.3,.9,.3,1)}
.nav .ind:before{content:"";width:62px;border-radius:18px;background:var(--grad);box-shadow:0 10px 22px -8px rgba(221,42,123,.95)}
.nav button{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;font-size:10px;font-weight:800;color:var(--dim2);transition:color .25s}
.nav button svg{width:22px;height:22px;transition:transform .25s}
.nav button.on{color:#fff}
.nav button.on svg{transform:translate3d(0,-1px,0) scale(1.08)}
.nav .bd{position:absolute;top:7px;left:calc(50% - 22px);min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:#FEDA75;color:#3A1500;font-size:9.5px;font-weight:900;display:none;place-items:center}
.nav .bd.on{display:grid}

.ov{position:fixed;inset:0;z-index:40;background:rgba(6,2,10,.6);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;
  border-radius:28px 28px 0 0;padding:8px 16px calc(18px + var(--safe));border-top:1px solid var(--line2);
  background:linear-gradient(180deg,rgba(44,16,52,.97),rgba(16,6,22,.99));box-shadow:0 -20px 50px -20px rgba(221,42,123,.35);
  transform:translate3d(0,105%,0);transition:transform .34s cubic-bezier(.2,.85,.25,1)}
.sh.on{transform:none}
.grab{width:42px;height:4px;border-radius:4px;background:rgba(255,255,255,.2);margin:0 auto 10px}
.sh .st{display:flex;align-items:flex-start;gap:12px}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto;box-shadow:0 10px 22px -10px rgba(221,42,123,.9)}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.07);flex:0 0 auto}
.sh .x svg{width:16px;height:16px}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:50px;height:50px;border-radius:50%;background:var(--glass);border:1px solid var(--line2);font-size:20px;font-weight:900;color:var(--pk)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:30px;padding:0 12px;border-radius:15px;background:var(--glass);border:1px solid var(--line);font-size:11px;font-weight:800;color:var(--dim)}
.qchips button.on{background:var(--grad);color:#fff;border-color:transparent}
.sum{margin-top:14px;border-radius:18px;padding:12px 14px;background:linear-gradient(120deg,rgba(221,42,123,.16),rgba(129,52,175,.1));border:1px solid var(--line2)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t b{font-size:19px;font-weight:900;background:linear-gradient(90deg,#FEDA75,#FF5FA2);-webkit-background-clip:text;background-clip:text;color:transparent}
.sum div.lo b{color:var(--red)}
.res{text-align:center;padding:6px 0 4px}
.res .rc{position:relative;width:66px;height:66px;margin:4px auto 10px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff}
.res .rc:after{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid rgba(255,95,162,.6);animation:rpl 1.8s ease-out infinite}
@keyframes rpl{from{transform:scale(.9);opacity:.9}to{transform:scale(1.5);opacity:0}}
.res .rc svg{width:32px;height:32px}
.res b{display:block;font-size:15px;font-weight:900}
.res small{font-size:12px;color:var(--pk);font-weight:800}

.toast{position:fixed;left:16px;right:16px;bottom:calc(86px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:18px;background:rgba(36,12,42,.96);border:1px solid var(--line2);color:#fff;font-size:12px;font-weight:700;
  transform:translate3d(0,200%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px rgba(0,0,0,.8)}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:var(--ok)}.toast.er svg{color:var(--red)}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate>svg{width:64px;height:64px;color:var(--pk);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.5a4 4 0 0 0-3-3.8M15.5 4.2a3.5 3.5 0 0 1 0 6.6"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-heartf" viewBox="0 0 24 24" fill="currentColor"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
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
    <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="8" y="8" width="12.5" height="12.5" rx="2.5"/><path d="M16 8V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7.5A2.5 2.5 0 0 0 6 16h2"/></symbol>
  </defs>
</svg>
<div class="mesh" aria-hidden="true"><i class="m m1"></i><i class="m m2"></i><i class="m m3"></i><i class="bm"></i><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><s class="sp"></s><s class="sp"></s><s class="sp"></s><s class="sp"></s><s class="sp"></s></div>

<div class="app">
  <div class="hd0">
    <header class="hdr gb">
      <div class="ava"><span id="ava"></span></div>
      <div class="who"><b id="uName">—</b><small><i class="dot"></i><span>آنلاین · سفارشِ آنی</span></small></div>
      <button class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em><i><svg><use href="#i-plus"/></svg></i></button>
      <button class="ib" id="supBtn" aria-label="پشتیبانی"><svg><use href="#i-headset"/></svg></button>
    </header>
  </div>

  <section class="pg on" id="pg-home">
    <div class="wmk"><b id="wm">__TITLE__</b><span><i class="dot"></i>فعال</span></div>
    <div class="stories" id="stH"></div>
    <article class="post gb">
      <div class="u"><div class="a"><div><svg><use href="#i-camera"/></svg></div></div>
        <div><b><span id="pName">نامبیکس</span><svg><use href="#i-verified"/></svg></b><small>سفارشِ آنی · بدونِ نیاز به رمز</small></div></div>
      <div class="img"><i class="sw"></i><div class="big"><span><svg><use href="#i-heart"/></svg></span><span><svg><use href="#i-users"/></svg></span><span><svg><use href="#i-play"/></svg></span></div>
        <svg class="pop"><use href="#i-heartf"/></svg><span class="tag">پیج‌های عمومی</span></div>
      <div class="act"><svg class="lk"><use href="#i-heartf"/></svg><svg><use href="#i-chat"/></svg><svg><use href="#i-send"/></svg><svg class="sv"><use href="#i-bookmark"/></svg></div>
      <div class="cap"><b id="hTitle">__TITLE__</b><p id="hTag">__TAG__</p>
        <button class="cta" data-go="list"><svg><use href="#i-explore"/></svg>شروعِ سفارش</button></div>
    </article>
    <div class="stat3"><div><b id="kN">—</b><small>سرویسِ فعال</small></div><div><b id="kF">—</b><small>شروع از / ۱۰۰۰</small></div><div><b>۲۴/۷</b><small>ثبتِ خودکار</small></div></div>
    <div class="hd" id="popH"><h3><i></i>محبوب‌ترین‌ها</h3><button data-go="list">همه</button></div>
    <div class="grid" id="pop"></div>
    <div class="links" id="xl"></div>
  </section>

  <section class="pg" id="pg-list">
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجو: فالوور، لایک…" autocomplete="off"></div>
    <div class="stories" id="stL"></div>
    <div class="grid" id="slist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="hd" style="margin-top:4px"><h3><i></i>سفارش‌های من</h3><button id="oRef">تازه کن</button></div>
    <div id="olist"></div>
  </section>

  <section class="pg" id="pg-wallet">
    <div class="wal"><i class="sw"></i><svg class="wm"><use href="#i-camera"/></svg>
      <div class="tp"><span>کیف پولِ شما</span><i class="chip"></i></div>
      <small>موجودی</small><b><span id="wBal">…</span><em>تومان</em></b>
      <div class="ft">یک کیف پول برای همه‌ی بخش‌های ربات</div>
    </div>
    <div class="fld"><label>مبلغِ شارژ (تومان)</label><input id="tAmt" inputmode="numeric" placeholder="مثلا ۱۰۰٬۰۰۰"></div>
    <div class="qa" id="qa"></div>
    <div id="payInfo"></div>
    <button class="cta" id="tGo"><svg><use href="#i-wallet"/></svg>درخواستِ شارژ</button>
    <div class="note" id="tNote">فاکتور و مقصدِ پرداخت داخلِ ربات برایتان فرستاده می‌شود؛ بعد از واریز، «ارسال رسید» را بزنید.</div>
    <div class="links"><button class="xl sup" id="supBtn2"><span class="ic"><svg><use href="#i-headset"/></svg></span><span>پشتیبانی<small>سوال یا مشکل دارید؟ همین‌جا بپرسید</small></span><svg class="ch"><use href="#i-chev"/></svg></button></div>
  </section>
</div>

<nav class="nav gb" id="nav"><div>
  <span class="ind" id="navInd"></span>
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
var D = document, H = D.documentElement;
var $ = function(id){ return D.getElementById(id); };
var RM = false; try { RM = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); } catch(e){}
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
function ico(n, cls){ return '<svg' + (cls ? ' class="' + cls + '"' : '') + '><use href="#i-' + n + '"/></svg>'; }
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
function copy(txt, what){
  var done = function(){ toast((what || 'متن') + ' کپی شد.', true); };
  function fb(){
    try { var t = D.createElement('textarea'); t.value = txt; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
          D.body.appendChild(t); t.select(); D.execCommand('copy'); D.body.removeChild(t); done(); }
    catch(e){ toast('کپی نشد — دستی کپی کنید.'); }
  }
  try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, fb); return; } } catch(e){}
  fb();
}
function countUp(el, to){
  to = Number(to) || 0;
  if (!to || RM || !window.requestAnimationFrame) { el.textContent = fa(to); return; }
  var t0 = 0;
  function st(ts){ if (!t0) t0 = ts; var k = Math.min(1, (ts - t0) / 900); k = 1 - Math.pow(1 - k, 3);
    el.textContent = fa(Math.round(to * k)); if (k < 1) requestAnimationFrame(st); }
  requestAnimationFrame(st);
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
var S = { page: '', stack: [], bal: 0, cat: '', q: '', orders: null, cur: null, sheet: false, poll: null, ava: '' };
var U = tgUser() || {};

function setBal(v){ if (v == null || isNaN(Number(v))) return; S.bal = Number(v); $('bal').textContent = fa(S.bal); $('wBal').textContent = fa(S.bal); }
function drawSelf(avatar){
  var n = ((U.first_name || '') + ' ' + (U.last_name || '')).trim() || (U.username ? '@' + U.username : 'کاربر');
  $('uName').textContent = n;
  var box = $('ava'), im;
  if (avatar) S.ava = avatar; else avatar = S.ava;
  box.textContent = n.charAt(0).toUpperCase();
  box._im = null;
  if (!avatar) return;
  im = new Image(); im.alt = ''; box._im = im;
  im.onload = function(){ if (box._im === im) { box.textContent = ''; box.appendChild(im); } };
  im.src = avatar;
}

var PAGES = ['home', 'list', 'orders', 'wallet'];
function go(p, back){
  if (PAGES.indexOf(p) < 0) p = 'home';
  if (p === S.page) { window.scrollTo(0, 0); return; }
  if (!back && S.page) S.stack.push(S.page);
  S.page = p;
  PAGES.forEach(function(x){ $('pg-' + x).classList.toggle('on', x === p); });
  [].forEach.call(D.querySelectorAll('#nav button'), function(b){ b.classList.toggle('on', b.getAttribute('data-go') === p); });
  $('navInd').style.transform = 'translate3d(' + (-PAGES.indexOf(p) * 100) + '%,0,0)';
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

function stories(active){
  return '<button class="story' + (active === '' ? ' on' : '') + '" style="--i:0" data-go="list" data-cat=""><div class="rg"><div class="in"><div>' + ico('explore') + '</div></div></div><small>همه</small></button>' +
    CATS.map(function(c, k){
      return '<button class="story' + (active === c.id ? ' on' : '') + '" style="--i:' + (k + 1) + '" data-go="list" data-cat="' + esc(c.id) + '"><div class="rg"><div class="in"><div>' + ico(c.ic) + '</div></div></div><small>' + esc(c.n) + '</small></button>';
    }).join('');
}
function tile(i, k){
  var c = CAT[i.c] || {};
  return '<button class="tile ' + (c.tone || 'c1') + '" style="--i:' + Math.min(k || 0, 12) + '" data-sv="' + esc(i.i) + '">' + (i.r ? '<span class="rf">ضمانت</span>' : '') +
    '<span class="ic">' + ico(c.ic || 'spark') + '</span><b>' + esc(i.n) + '</b><small><i></i>' + fa(i.mn) + ' تا ' + fa(i.mx) + '</small>' +
    '<span class="ft"><span><b>' + fa(i.p) + '</b> تومان<br>هر ۱۰۰۰ تا</span><i>' + ico('plus') + '</i></span></button>';
}
function drawHome(){
  $('stH').innerHTML = stories(null);
  countUp($('kN'), ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  if (min) countUp($('kF'), min); else $('kF').textContent = '—';
  if (B.bot) $('pName').textContent = '@' + B.bot;
  var pop = [];
  CATS.forEach(function(c){ var f = ITEMS.filter(function(i){ return i.c === c.id; }).sort(function(a, b){ return a.p - b.p; })[0]; if (f) pop.push(f); });
  ITEMS.slice().sort(function(a, b){ return a.p - b.p; }).forEach(function(i){ if (pop.length < 6 && pop.indexOf(i) < 0) pop.push(i); });
  $('pop').innerHTML = pop.length ? pop.slice(0, 6).map(tile).join('') :
    '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';
  var xl = '';
  if ((B.links || {}).tg) xl += '<button class="xl tg" data-open="tg"><span class="ic">' + ico('plane') + '</span><span>خدمات تلگرام<small>ممبر، بازدید، ری‌اکشن و بوست</small></span>' + ico('chev', 'ch') + '</button>';
  if ((B.links || {}).num) xl += '<button class="xl num" data-open="num"><span class="ic">' + ico('sim') + '</span><span>شماره مجازی تلگرام<small>تحویلِ آنی، کد همین‌جا</small></span>' + ico('chev', 'ch') + '</button>';
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
  $('slist').innerHTML = l.length ? l.map(tile).join('') : (ITEMS.length
    ? '<div class="emp" style="grid-column:1/-1">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>'
    : '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>');
}
var QT;
$('q').addEventListener('input', function(){ var v = this.value; clearTimeout(QT); QT = setTimeout(function(){ S.q = v; drawList(true); }, 140); });
D.addEventListener('click', function(ev){ var b = ev.target.closest ? ev.target.closest('[data-sv]') : null; if (b) { tap(); openOrder(b.getAttribute('data-sv')); } });

function total(i, q){ return Math.max(1, Math.ceil(i.p * q / 1000 - 1e-9)); }
function niceQty(i){ var c = [1000, 500, 100, 5000, 10000]; for (var k = 0; k < c.length; k++) if (c[k] >= i.mn && c[k] <= i.mx) return c[k]; return i.mn; }
function linkOk(v){ v = v.trim(); return /^@?[A-Za-z0-9._]{1,30}$/.test(v) || /^(https?:\/\/)?(www\.|m\.)?(instagram\.com|instagr\.am)\/\S+$/i.test(v); }
function openSheet(){ S.sheet = true; $('ov').classList.add('on'); $('sh').classList.add('on'); $('sh').scrollTop = 0; backBtn(); }
function closeSheet(){ S.sheet = false; $('ov').classList.remove('on'); $('sh').classList.remove('on'); backBtn(); }
$('ov').onclick = closeSheet;
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
  openSheet();
}

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
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('receipt') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="cta" data-go="list">' + ico('explore') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o, k){
    return '<div class="or ' + esc(o.st) + '" style="--i:' + Math.min(k, 10) + '"><div class="h"><div><b>' + esc(o.n) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
      '<span class="pill ' + esc(o.st) + '">' + esc(o.sx) + '</span></div>' +
      (o.st === 'run' || o.st === 'done' || o.st === 'partial' ? '<div class="prog' + (o.st === 'run' ? ' run' : '') + '"><i style="width:' + Math.max(o.st === 'run' ? 4 : 0, o.pc) + '%"></i></div>' : '<div style="height:8px"></div>') +
      '<div class="kv"><span>تعداد: <b>' + fa(o.q) + '</b></span>' + (o.sc >= 0 ? '<span>شروع از: <b>' + fa(o.sc) + '</b></span>' : '') +
      (o.rm >= 0 && o.st === 'run' ? '<span>مانده: <b>' + fa(o.rm) + '</b></span>' : '') + (o.rf > 0 ? '<span>برگشتی: <b>' + fa(o.rf) + '</b> تومان</span>' : '') +
      '<span class="ltr">#' + esc(o.id) + '</span></div>' +
      '<div class="lnk">' + ico('link') + '<span>' + esc(o.l) + '</span></div></div>';
  }).join('');
}
$('oRef').onclick = function(){ tap(); loadOrders(); };

var WAL = { pre: 0, busy: false };
function tMin(){ return Math.max(1000, Number((B.topup || {}).min) || 0); }
function payOk(){ var t = B.topup || {}; return !!(t.on || t.gw); }
function drawWallet(){
  setBal(S.bal);
  var t = B.topup || {}, min = tMin();
  var qs = [min, 50000, 100000, 200000, 500000, 1000000].filter(function(v, i, a){ return v >= min && a.indexOf(v) === i; }).sort(function(a, b){ return a - b; }).slice(0, 6);
  $('qa').innerHTML = qs.map(function(v){ return '<button data-v="' + v + '">' + fa(v) + '</button>'; }).join('');
  if (WAL.pre) { $('tAmt').value = fa(Math.max(min, Math.ceil(WAL.pre / 1000) * 1000)); WAL.pre = 0; }
  markQa();
  var h = '';
  if (t.on) h += '<div class="r"><span>کارت به کارت</span><button class="cpy" id="cardCp">' + ico('copy') + '<span class="ltr">' + esc(t.card) + '</span></button></div>' +
    (t.name ? '<div class="r"><span>به نامِ</span><b>' + esc(t.name) + '</b></div>' : '');
  if (t.gw) h += '<div class="r"><span>پرداختِ آنلاین (' + esc(t.gwcoin || 'USDT') + ')</span><b class="g">فعال' + (t.gwmin > 0 ? ' — از ' + fa(t.gwmin) + ' تومان' : '') + '</b></div>';
  $('payInfo').innerHTML = h ? '<div class="pay">' + h + '</div>'
    : '<div class="warn">' + ico('alert') + '<span>روشِ پرداخت هنوز تنظیم نشده — برای شارژ با پشتیبانی در تماس باشید.</span></div>';
  var cc = $('cardCp'); if (cc) cc.onclick = function(){ tap(); copy(digits(t.card), 'شماره کارت'); };
  $('tGo').disabled = !payOk();
  $('tNote').classList.toggle('hid', !payOk());
}
function markQa(){ var v = parseInt(digits($('tAmt').value), 10) || 0; [].forEach.call($('qa').children, function(b){ b.classList.toggle('on', +b.getAttribute('data-v') === v); }); }
$('qa').onclick = function(ev){ var b = ev.target.closest('[data-v]'); if (!b) return; tap(); $('tAmt').value = fa(+b.getAttribute('data-v')); markQa(); };
$('tAmt').oninput = function(){ var n = parseInt(digits(this.value).slice(0, 10), 10) || 0; this.value = n ? fa(n) : ''; markQa(); };
$('tGo').onclick = function(){
  if (WAL.busy || !payOk()) return;
  var n = parseInt(digits($('tAmt').value), 10) || 0;
  if (n < tMin()) { toast('کمترین مبلغِ شارژ ' + fa(tMin()) + ' تومان است.'); $('tAmt').focus(); return; }
  WAL.busy = true; tap('medium');
  var b = this; b.disabled = true;
  api('topup', { amount: n }, function(j){
    WAL.busy = false; b.disabled = false; $('tAmt').value = ''; markQa();
    $('shB').innerHTML = '<div class="res"><span class="rc">' + ico('check') + '</span><b>درخواستِ شارژ ثبت شد</b><small>' + fa(j.amount || n) + ' تومان</small></div>' +
      '<div class="note">' + esc(j.message || 'فاکتور و مقصدِ پرداخت داخلِ ربات برایتان رفت.') + '</div>' +
      (j.order ? '<div class="pay"><div class="r"><span>کدِ پیگیری</span><b class="ltr">' + esc(j.order) + '</b></div></div>' : '') +
      (B.bot ? '<button class="cta" id="toBot">' + ico('send') + 'رفتن به ربات و پرداخت</button>' : '') +
      '<button class="cta gh" id="resX">بستن</button>';
    var tb = $('toBot'); if (tb) tb.onclick = function(){ tap(); openLink('https://t.me/' + B.bot); };
    $('resX').onclick = function(){ tap(); closeSheet(); };
    buzz('success'); openSheet();
  }, function(j){ WAL.busy = false; b.disabled = false; toast((j && j.message) || 'ثبت نشد.'); });
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

function fsSync(){ var on = false; try { on = !!(TG && TG.isFullscreen); } catch(e){} H.classList.toggle('fs', on); }
function tgSetup(){
  if (!TG) return;
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#0A0510'); TG.setBackgroundColor && TG.setBackgroundColor('#0A0510'); TG.setBottomBarColor && TG.setBottomBarColor('#0A0510'); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
  try { var pf = String(TG.platform || ''); if (TG.requestFullscreen && /^(ios|android)/.test(pf)) TG.requestFullscreen(); } catch(e){}
  try {
    TG.onEvent('fullscreenChanged', fsSync); TG.onEvent('safeAreaChanged', fsSync); TG.onEvent('contentSafeAreaChanged', fsSync);
    if (TG.BackButton) TG.BackButton.onClick(goBack);
  } catch(e){}
  fsSync(); setTimeout(fsSync, 200); setTimeout(fsSync, 900);
  backBtn();
}
if (TG) tgSetup();
else { var tries = 0, iv = setInterval(function(){
  if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; U = tgUser() || U; tgSetup(); drawSelf(''); }
  else if (++tries > 40) clearInterval(iv); }, 100); }
D.addEventListener('visibilitychange', function(){ if (!D.hidden && S.page === 'orders') loadOrders(); });
var SCT = 0;
window.addEventListener('scroll', function(){ if (!SCT) H.classList.add('scr'); clearTimeout(SCT); SCT = setTimeout(function(){ SCT = 0; H.classList.remove('scr'); }, 160); }, { passive: true });

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
