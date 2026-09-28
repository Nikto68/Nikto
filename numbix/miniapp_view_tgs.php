<?php

function svTplTg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#030A17">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
__FONT__
<style>
:root{
  --bg:#030A17;--glass:rgba(10,30,58,.52);--glass2:rgba(16,42,78,.62);--solid:#08172D;
  --line:rgba(125,211,252,.12);--line2:rgba(56,189,248,.32);
  --ink:#EAF6FF;--dim:#8FA9C7;--dim2:#5F7B9B;
  --tg:#2AABEE;--sky:#38BDF8;--cy:#5EEAD4;--vi:#A78BFA;--gold:#FCD34D;--red:#FB7185;--ok:#34D399;
  --grad:linear-gradient(120deg,#2AABEE 0%,#38BDF8 45%,#5EEAD4 100%);
  --safe:env(safe-area-inset-bottom,0px);--top:0px;color-scheme:dark;
  --dots:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cg fill='%237DD3FC'%3E%3Ccircle cx='22' cy='38' r='1.2' opacity='.8'/%3E%3Ccircle cx='96' cy='12' r='.8' opacity='.6'/%3E%3Ccircle cx='160' cy='70' r='1.4' opacity='.5'/%3E%3Ccircle cx='250' cy='30' r='.9' opacity='.8'/%3E%3Ccircle cx='280' cy='120' r='1.1' opacity='.55'/%3E%3Ccircle cx='200' cy='160' r='.8' opacity='.7'/%3E%3Ccircle cx='60' cy='140' r='1' opacity='.5'/%3E%3Ccircle cx='120' cy='210' r='1.3' opacity='.65'/%3E%3Ccircle cx='30' cy='250' r='.8' opacity='.7'/%3E%3Ccircle cx='230' cy='240' r='1.2' opacity='.6'/%3E%3Ccircle cx='170' cy='290' r='.9' opacity='.5'/%3E%3Ccircle cx='90' cy='280' r='.7' opacity='.8'/%3E%3C/g%3E%3Cg fill='%235EEAD4'%3E%3Ccircle cx='140' cy='120' r='1' opacity='.6'/%3E%3Ccircle cx='270' cy='200' r='1.1' opacity='.5'/%3E%3Ccircle cx='50' cy='90' r='.9' opacity='.7'/%3E%3C/g%3E%3C/svg%3E")
}
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
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-4px,0)}}
@keyframes shine{0%,72%{transform:translate3d(-130%,0,0) skewX(-20deg)}100%{transform:translate3d(360%,0,0) skewX(-20deg)}}
@keyframes up{from{opacity:0;transform:translate3d(0,14px,0)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}

.sky{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;contain:strict;
  background:radial-gradient(130vw 70vh at 100% -12%,rgba(42,171,238,.24),transparent 62%),
    radial-gradient(100vw 60vh at -15% 110%,rgba(94,234,212,.12),transparent 62%),
    linear-gradient(180deg,#030A17 0%,#05142C 52%,#030A17 100%)}
.sky>*{position:absolute;display:block}
.sky .bl{border-radius:50%;will-change:transform}
.sky .b1{width:110vw;height:110vw;left:-35vw;top:-40vw;background:radial-gradient(closest-side,rgba(42,171,238,.30),transparent);animation:d1 21s ease-in-out infinite alternate}
.sky .b2{width:95vw;height:95vw;right:-45vw;top:38vh;background:radial-gradient(closest-side,rgba(167,139,250,.20),transparent);animation:d2 27s ease-in-out infinite alternate}
.sky .b3{width:100vw;height:100vw;left:-25vw;bottom:-50vw;background:radial-gradient(closest-side,rgba(94,234,212,.17),transparent);animation:d3 18s ease-in-out infinite alternate}
@keyframes d1{to{transform:translate3d(22vw,14vh,0) scale(1.18)}}
@keyframes d2{to{transform:translate3d(-34vw,-16vh,0) scale(1.22)}}
@keyframes d3{to{transform:translate3d(18vw,-12vh,0) scale(.86)}}
.sky .rb{left:-40%;width:180%;top:14vh;height:30vh;opacity:.7;will-change:transform;
  background:linear-gradient(90deg,transparent 8%,rgba(56,189,248,.2) 28%,rgba(94,234,212,.16) 48%,rgba(167,139,250,.15) 68%,transparent 90%);
  -webkit-mask-image:linear-gradient(180deg,transparent,#000 42%,#000 58%,transparent);mask-image:linear-gradient(180deg,transparent,#000 42%,#000 58%,transparent);
  animation:rb 16s ease-in-out infinite alternate}
@keyframes rb{from{transform:rotate(-17deg) translate3d(-10%,0,0) scaleY(.75)}to{transform:rotate(-12deg) translate3d(10%,5vh,0) scaleY(1.2)}}
.sky .pt{left:0;right:0;top:0;height:calc(100% + 300px);background:var(--dots) 0 0/300px 300px repeat;opacity:.75;will-change:transform;animation:rise 38s linear infinite}
@keyframes rise{to{transform:translate3d(0,-300px,0)}}
.sky .gd{inset:0;opacity:.28;background-image:radial-gradient(rgba(143,169,199,.45) 1px,transparent 1.2px);background-size:24px 24px;
  -webkit-mask-image:linear-gradient(180deg,#000,transparent 42%);mask-image:linear-gradient(180deg,#000,transparent 42%)}
.sky .fp{left:0;top:0;width:24px;height:24px;color:#7DD3FC;opacity:0;will-change:transform,opacity}
.sky .fp svg{width:100%;height:100%;filter:drop-shadow(0 0 6px rgba(56,189,248,.8))}
.sky .fp:before{content:"";position:absolute;right:88%;top:62%;width:120px;height:1.5px;border-radius:2px;
  background:repeating-linear-gradient(90deg,rgba(125,211,252,.55) 0 7px,transparent 7px 12px);
  -webkit-mask-image:linear-gradient(90deg,transparent,#000);mask-image:linear-gradient(90deg,transparent,#000)}
.sky .f1{animation:fp1 17s linear infinite}
.sky .f2{width:18px;height:18px;animation:fp2 23s linear 6s infinite}
@keyframes fp1{0%{transform:translate3d(-20vw,34vh,0) rotate(-6deg);opacity:0}6%{opacity:.85}46%{transform:translate3d(112vw,8vh,0) rotate(-12deg);opacity:.85}50%,100%{transform:translate3d(122vw,5vh,0) rotate(-12deg);opacity:0}}
@keyframes fp2{0%{transform:translate3d(-20vw,80vh,0) rotate(-14deg);opacity:0}8%{opacity:.6}52%{transform:translate3d(112vw,46vh,0) rotate(-18deg);opacity:.6}56%,100%{transform:translate3d(122vw,43vh,0) rotate(-18deg);opacity:0}}
.sky .tw{width:3px;height:3px;border-radius:50%;background:#fff;box-shadow:0 0 6px 1px rgba(125,211,252,.9);opacity:.2;animation:twk 3.4s ease-in-out infinite}
.sky .tw:nth-of-type(1){left:14%;top:11%}
.sky .tw:nth-of-type(2){left:76%;top:19%;animation-delay:-.8s}
.sky .tw:nth-of-type(3){left:38%;top:34%;animation-delay:-1.6s;background:#99F6E4}
.sky .tw:nth-of-type(4){left:88%;top:52%;animation-delay:-2.4s}
.sky .tw:nth-of-type(5){left:9%;top:63%;animation-delay:-.4s}
.sky .tw:nth-of-type(6){left:58%;top:74%;animation-delay:-1.2s;background:#C4B5FD}
.sky .tw:nth-of-type(7){left:26%;top:88%;animation-delay:-2s}
@keyframes twk{0%,100%{opacity:.15;transform:scale(.6)}50%{opacity:1;transform:scale(1.3)}}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:calc(var(--top) + 8px) 14px calc(30px + var(--safe));overflow-x:clip}

.hd0{position:sticky;top:calc(var(--top) + 6px);z-index:30;margin-bottom:12px}
.hd0:before{content:"";position:fixed;left:0;right:0;top:0;height:calc(var(--top) + 6px);z-index:-1;pointer-events:none;
  background:rgba(3,10,23,.84);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px)}
.hdr{border-radius:22px;padding:8px;border:1px solid var(--line2);
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),rgba(5,18,38,.76);
  -webkit-backdrop-filter:blur(18px) saturate(160%);backdrop-filter:blur(18px) saturate(160%);
  box-shadow:0 18px 38px -22px #000,inset 0 1px 0 rgba(255,255,255,.09)}
.hrow{display:flex;align-items:center;gap:10px}
.ava{position:relative;width:40px;height:40px;flex:0 0 auto;border-radius:50%;padding:2px;overflow:hidden}
.ava:before{content:"";position:absolute;inset:-30%;background:conic-gradient(#2AABEE,#5EEAD4,#A78BFA,#2AABEE);animation:spin 5s linear infinite}
.ava span{position:relative;display:grid;place-items:center;width:100%;height:100%;border-radius:50%;overflow:hidden;
  background:#06162C;border:2px solid #06162C;color:var(--cy);font-weight:900;font-size:15px}
.ava span img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim);white-space:nowrap;overflow:hidden}
.who small span{overflow:hidden;text-overflow:ellipsis}
.dot{position:relative;display:inline-block;flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.dot:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 2s ease-out infinite}
.bal{display:flex;align-items:center;gap:6px;height:36px;padding:0 6px 0 11px;border-radius:13px;font-weight:900;font-size:12px;
  border:1px solid var(--line2);background:linear-gradient(135deg,rgba(42,171,238,.2),rgba(94,234,212,.08))}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.bal i{width:24px;height:24px;border-radius:8px;display:grid;place-items:center;background:var(--grad);color:#03101F;box-shadow:0 4px 12px -4px rgba(42,171,238,.9)}
.bal i svg{width:14px;height:14px}
.ib{width:36px;height:36px;flex:0 0 auto;border-radius:12px;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.04)}
.ib svg{width:18px;height:18px;color:var(--cy)}

.tabs{position:relative;display:grid;grid-template-columns:repeat(4,1fr);margin-top:8px;padding:3px;border-radius:15px;
  background:rgba(2,10,22,.55);border:1px solid var(--line)}
.tabs button{position:relative;z-index:1;height:36px;border-radius:12px;font-size:11.5px;font-weight:800;color:var(--dim);
  display:flex;align-items:center;justify-content:center;gap:5px;transition:color .25s}
.tabs button svg{width:16px;height:16px}
.tabs button.on{color:#03101F}
.tabs .ind{position:absolute;z-index:0;top:3px;bottom:3px;right:3px;width:calc((100% - 6px)/4);border-radius:12px;overflow:hidden;
  background:var(--grad);box-shadow:0 6px 18px -6px rgba(42,171,238,.9);transition:transform .38s cubic-bezier(.3,.9,.3,1)}
.tabs .ind:after{content:"";position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);animation:shine 4.5s ease-in-out infinite}
.tabs .bd{position:absolute;top:2px;left:6px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--gold);
  color:#1a1300;font-size:9.5px;font-weight:900;display:none;place-items:center}
.tabs .bd.on{display:grid}

.pg{display:none}
.pg.on{display:block}
.pg.on>*{animation:up .5s cubic-bezier(.2,.85,.25,1) backwards}
.pg.on>:nth-child(2){animation-delay:.05s}
.pg.on>:nth-child(3){animation-delay:.1s}
.pg.on>:nth-child(4){animation-delay:.15s}
.pg.on>:nth-child(n+5){animation-delay:.2s}

.hero{position:relative;overflow:hidden;border-radius:26px;padding:1.3px;isolation:isolate;box-shadow:0 26px 50px -30px rgba(42,171,238,.8)}
.hero .rim{position:absolute;left:50%;top:50%;width:170%;height:0;padding-bottom:170%;margin:-85% 0 0 -85%;
  background:conic-gradient(rgba(42,171,238,.12) 0deg,rgba(42,171,238,.12) 200deg,#38BDF8 262deg,#5EEAD4 300deg,#A78BFA 332deg,rgba(42,171,238,.12) 360deg);
  animation:spin 6s linear infinite}
.hero .in{position:relative;border-radius:25px;padding:18px 16px 16px;overflow:hidden;
  background:radial-gradient(120% 90% at 0% 0%,rgba(42,171,238,.26),transparent 60%),linear-gradient(155deg,#0C2748 0%,#081B35 55%,#0A1E3B 100%)}
.art{position:absolute;left:10px;top:14px;width:124px;height:124px}
.art>i{position:absolute;display:block;border-radius:50%}
.art .o1{inset:8px;border:1.5px dashed rgba(94,234,212,.38);animation:spin 20s linear infinite}
.art .o2{inset:-4px;border:1px solid rgba(56,189,248,.16);border-top-color:rgba(94,234,212,.75);animation:spin 7s linear infinite}
.art .rp{inset:28px;border:2px solid rgba(56,189,248,.55);animation:rpl 2.8s ease-out infinite}
.art .r2{animation-delay:1.4s}
@keyframes rpl{from{transform:scale(.85);opacity:.9}to{transform:scale(1.8);opacity:0}}
.art .st{inset:0;border-radius:0;animation:spin 9s linear infinite}
.art .st:before{content:"";position:absolute;top:1px;left:50%;width:8px;height:8px;margin-left:-4px;border-radius:50%;background:var(--cy);box-shadow:0 0 10px 2px rgba(94,234,212,.9)}
.art .s2{animation-duration:13s;animation-direction:reverse}
.art .s2:before{top:auto;bottom:6px;width:6px;height:6px;background:var(--vi);box-shadow:0 0 10px 2px rgba(167,139,250,.9)}
.art svg{position:absolute;left:22px;top:22px;width:80px;height:80px;filter:drop-shadow(0 14px 18px rgba(42,171,238,.6));animation:fly 4s ease-in-out infinite}
@keyframes fly{0%,100%{transform:translate3d(0,0,0) rotate(-4deg)}50%{transform:translate3d(-4px,-7px,0) rotate(3deg)}}
.hero .tx{position:relative;max-width:60%;min-height:118px}
.kick{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;
  color:var(--cy);background:rgba(94,234,212,.1);border:1px solid rgba(94,234,212,.28)}
.kick i{position:relative;width:6px;height:6px;border-radius:50%;background:var(--cy)}
.kick i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.8s ease-out infinite}
.hero h1{margin-top:9px;font-size:22px;font-weight:900;line-height:1.35;
  background:linear-gradient(90deg,#fff 0%,#BAE6FD 30%,#fff 50%,#99F6E4 75%,#fff 100%);background-size:220% 100%;
  -webkit-background-clip:text;background-clip:text;color:transparent;animation:tsh 7s linear infinite}
@keyframes tsh{to{background-position:-220% 0}}
.hero p{margin-top:5px;font-size:11.5px;color:var(--dim)}
.go{position:relative;overflow:hidden;margin-top:12px;height:46px;padding:0 20px;border-radius:15px;background:var(--grad);color:#03101F;
  font-weight:900;font-size:13.5px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 14px 26px -12px rgba(42,171,238,.95)}
.go:after{content:"";position:absolute;top:0;bottom:0;left:0;width:34%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);animation:shine 3.6s ease-in-out infinite}
.go svg{width:17px;height:17px}
.kpis{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}
.kpis div{padding:9px 10px;border-radius:15px;background:rgba(3,12,26,.5);border:1px solid var(--line);box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}
.kpis b{display:block;font-size:15px;font-weight:900}
.kpis small{display:block;font-size:9.5px;color:var(--dim);white-space:nowrap}

.tick{overflow:hidden;contain:paint;direction:ltr;margin:14px -14px 0;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.tick .tr{display:flex;width:max-content;animation:mq 32s linear infinite;will-change:transform}
@keyframes mq{to{transform:translate3d(-50%,0,0)}}
.tick span{direction:rtl;display:inline-flex;align-items:center;gap:6px;margin-right:8px;white-space:nowrap;font-size:11px;font-weight:800;padding:7px 12px;border-radius:30px;
  border:1px solid var(--line2);background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02)),rgba(6,20,40,.5);color:var(--ink)}
.tick span svg{width:14px;height:14px;color:var(--cy)}

.hd{display:flex;align-items:center;gap:8px;margin:20px 2px 10px}
.hd h3{flex:1;font-size:14px;font-weight:900;display:flex;align-items:center;gap:8px}
.hd h3:before{content:"";width:4px;height:16px;border-radius:4px;background:var(--grad);box-shadow:0 0 10px rgba(56,189,248,.8)}
.hd button{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:800;color:var(--sky)}
.hd button svg{width:15px;height:15px}

.bento{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.bt{position:relative;overflow:hidden;isolation:isolate;text-align:right;border-radius:20px;padding:14px;min-height:118px;
  background:linear-gradient(160deg,rgba(255,255,255,.07),rgba(255,255,255,.01) 60%),var(--glass);border:1px solid var(--line);
  display:flex;flex-direction:column;justify-content:space-between;gap:10px;box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 16px 30px -24px #000;
  animation:up .55s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 60ms);transition:transform .15s}
.bt:before{content:"";position:absolute;z-index:-1;width:150px;height:150px;border-radius:50%;left:-55px;top:-65px;
  background:radial-gradient(closest-side,rgba(42,171,238,.34),transparent);animation:glw 7s ease-in-out infinite alternate}
@keyframes glw{to{transform:translate3d(60px,70px,0) scale(1.2)}}
.bt:first-child{grid-column:1/-1;min-height:92px;flex-direction:row;align-items:center;gap:14px;border-color:var(--line2);
  background:linear-gradient(120deg,rgba(42,171,238,.24),rgba(94,234,212,.07)),var(--glass)}
.bt .ic{width:46px;height:46px;border-radius:15px;display:grid;place-items:center;flex:0 0 auto;color:var(--tg);
  background:rgba(42,171,238,.14);border:1px solid rgba(56,189,248,.25);animation:bob 3.6s ease-in-out infinite}
.bt:first-child .ic{width:56px;height:56px;border-radius:18px;background:var(--grad);color:#03101F;border:0;box-shadow:0 10px 22px -10px rgba(42,171,238,.9)}
.bt .ic svg{width:22px;height:22px}
.bt b{display:block;font-size:13.5px;font-weight:900}
.bt small{display:block;font-size:10.5px;color:var(--dim)}
.bt .pr{display:inline-block;margin-top:2px;font-size:11px;font-weight:800;color:var(--cy)}
.bt:nth-child(2n):last-child{grid-column:1/-1;min-height:92px;flex-direction:row;align-items:center;gap:14px}
.bt:active{transform:scale(.98)}
.bt:nth-child(3n+2) .ic{color:var(--cy);background:rgba(94,234,212,.12);border-color:rgba(94,234,212,.25);animation-delay:-1.2s}
.bt:nth-child(3n) .ic{color:var(--vi);background:rgba(167,139,250,.14);border-color:rgba(167,139,250,.28);animation-delay:-2.4s}
.bt:nth-child(3n+2):before{background:radial-gradient(closest-side,rgba(94,234,212,.26),transparent);animation-delay:-2s}
.bt:nth-child(3n):before{background:radial-gradient(closest-side,rgba(167,139,250,.3),transparent);animation-delay:-4s}

.steps{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.steps:before{content:"";position:absolute;z-index:0;top:27px;left:17%;right:17%;height:2px;
  background:repeating-linear-gradient(90deg,rgba(94,234,212,.55) 0 6px,transparent 6px 12px);background-size:24px 2px;animation:flow 1.1s linear infinite}
@keyframes flow{to{background-position:-24px 0}}
.steps div{position:relative;z-index:1;padding:12px 8px;border-radius:18px;text-align:center;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.steps i{position:relative;width:30px;height:30px;margin:0 auto 6px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-weight:900;
  background:var(--grad);color:#03101F;font-size:13px;box-shadow:0 0 0 4px rgba(42,171,238,.15)}
.steps b{display:block;font-size:11.5px;font-weight:800}
.steps small{font-size:9.5px;color:var(--dim)}

.chips{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;margin:0 -14px;padding:2px 14px 4px}
.chips::-webkit-scrollbar{display:none}
.chips button{flex:0 0 auto;height:34px;padding:0 13px;border-radius:11px;font-size:11.5px;font-weight:800;color:var(--dim);
  background:var(--glass);border:1px solid var(--line);display:flex;align-items:center;gap:6px;transition:color .2s,background .2s}
.chips button svg{width:14px;height:14px}
.chips button.on{color:#03101F;background:var(--grad);border-color:transparent;box-shadow:0 8px 18px -10px rgba(42,171,238,.9)}
.srch{display:flex;align-items:center;gap:8px;height:46px;margin-bottom:10px;padding:0 13px;border-radius:15px;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line2)}
.srch svg{width:17px;height:17px;color:var(--sky)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}
.srch input::placeholder{color:var(--dim2)}

.list{display:grid;gap:9px;margin-top:10px}
.tk{position:relative;overflow:hidden;isolation:isolate;display:flex;align-items:stretch;width:100%;text-align:right;border-radius:18px;
  background:linear-gradient(90deg,rgba(255,255,255,.05),rgba(255,255,255,.015)),var(--glass);border:1px solid var(--line);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 14px 26px -22px #000;
  animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 45ms);transition:transform .15s}
.tk:after{content:"";position:absolute;z-index:2;top:0;bottom:0;left:0;width:30%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(125,211,252,.16),transparent);transform:translate3d(-130%,0,0) skewX(-20deg);
  animation:shine 7s ease-in-out infinite;animation-delay:calc(var(--i,0) * .7s)}
.tk:nth-child(n+13):after{display:none}
.tk:active{transform:scale(.985)}
.tk .ic{position:relative;flex:0 0 auto;align-self:center;width:42px;height:42px;margin-right:11px;border-radius:14px;display:grid;place-items:center;
  color:var(--tg);background:rgba(42,171,238,.13);border:1px solid rgba(56,189,248,.24)}
.tk .ic svg{width:20px;height:20px;animation:bob 3.4s ease-in-out infinite;animation-delay:calc(var(--i,0) * -.35s)}
.tk .m{flex:1;min-width:0;padding:11px 10px 11px 8px}
.tk .m b{display:block;font-size:12.5px;font-weight:800;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tk .m small{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.tk .m small span{font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:7px;background:rgba(143,169,199,.1);color:var(--dim)}
.tk .m small span.g{background:rgba(52,211,153,.12);color:var(--ok)}
.tk .m small .lv{display:inline-flex;align-items:center;gap:4px;background:rgba(52,211,153,.1);color:var(--ok)}
.tk .lv i{position:relative;width:6px;height:6px;border-radius:50%;background:var(--ok)}
.tk .lv i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.8s ease-out infinite}
.tk .p{position:relative;flex:0 0 92px;padding:10px 6px;display:flex;flex-direction:column;align-items:center;justify-content:center;
  background:linear-gradient(180deg,rgba(42,171,238,.2),rgba(94,234,212,.06));border-right:1.5px dashed rgba(94,234,212,.3)}
.tk .p:before,.tk .p:after{content:"";position:absolute;right:-8px;width:14px;height:14px;border-radius:50%;background:var(--bg)}
.tk .p:before{top:-7px}.tk .p:after{bottom:-7px}
.tk .p b{font-size:14px;font-weight:900;color:var(--cy);text-shadow:0 0 14px rgba(94,234,212,.45)}
.tk .p small{font-size:9px;color:var(--dim)}

.emp{text-align:center;padding:30px 18px;border-radius:22px;border:1px dashed var(--line2);color:var(--dim);
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass)}
.emp>svg{width:42px;height:42px;margin:0 auto 10px;color:var(--sky);animation:bob 3s ease-in-out infinite}
.emp>b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{position:relative;overflow:hidden;height:76px;border-radius:18px;margin-bottom:9px;background:var(--glass);border:1px solid var(--line)}
.sk:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(125,211,252,.1),transparent);animation:skl 1.2s linear infinite}
@keyframes skl{from{transform:translate3d(-100%,0,0)}to{transform:translate3d(100%,0,0)}}

.or{position:relative;overflow:hidden;border-radius:20px;padding:13px;margin-bottom:10px;
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line);
  animation:up .45s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 50ms)}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h .ic{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;background:rgba(42,171,238,.14);color:var(--tg);flex:0 0 auto}
.or .h .ic svg{width:20px;height:20px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(42,171,238,.14);color:var(--sky)}
.pill.run:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;animation:twk 1.4s ease-in-out infinite}
.pill.done{background:rgba(52,211,153,.14);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(252,211,77,.14);color:var(--gold)}
.pill.canceled,.pill.failed{background:rgba(251,113,133,.14);color:var(--red)}
.prog{height:8px;border-radius:8px;background:rgba(143,169,199,.14);margin:11px 0 7px;overflow:hidden}
.prog i{position:relative;display:block;height:100%;border-radius:8px;background:var(--grad);overflow:hidden;transition:width .6s ease}
.prog.run i:after{content:"";position:absolute;top:0;bottom:0;left:0;width:calc(100% + 17px);
  background:repeating-linear-gradient(-45deg,rgba(255,255,255,.3) 0 6px,transparent 6px 12px);animation:strp .8s linear infinite}
@keyframes strp{to{transform:translate3d(-16.97px,0,0)}}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:var(--sky);direction:ltr;overflow:hidden}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}

.wal{position:relative;overflow:hidden;isolation:isolate;border-radius:24px;padding:16px 18px 18px;
  background:linear-gradient(135deg,#1C6DB5 0%,#0F3E73 42%,#0A1E3B 100%);border:1px solid rgba(125,211,252,.38);
  box-shadow:0 24px 44px -26px rgba(42,171,238,.95),inset 0 1px 0 rgba(255,255,255,.16)}
.wal:before{content:"";position:absolute;z-index:-1;top:-50%;bottom:-50%;left:-30%;width:40%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);transform:skewX(-20deg);animation:wsh 5.5s ease-in-out infinite}
@keyframes wsh{0%,45%{transform:translate3d(0,0,0) skewX(-20deg)}100%{transform:translate3d(420%,0,0) skewX(-20deg)}}
.wal .wm{position:absolute;z-index:-1;left:-22px;bottom:-30px;width:140px;height:140px;color:rgba(255,255,255,.07);transform:rotate(-14deg)}
.wal .tp{display:flex;align-items:center;justify-content:space-between}
.wal .tp span{font-size:11px;font-weight:800;color:rgba(234,246,255,.85)}
.wal .chip{width:36px;height:27px;border-radius:7px;background:linear-gradient(135deg,#FDE68A,#F59E0B);box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)}
.wal small{display:block;margin-top:16px;color:rgba(234,246,255,.72);font-size:11px}
.wal b{display:block;font-size:30px;font-weight:900;line-height:1.3}
.wal b em{font-style:normal;font-size:12px;color:rgba(234,246,255,.72);font-weight:700;margin-right:4px}
.wal .ft{margin-top:6px;font-size:10px;color:rgba(234,246,255,.6)}
.qa{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
.qa button{height:42px;border-radius:13px;background:var(--glass);border:1px solid var(--line);font-weight:800;font-size:12px;transition:all .2s}
.qa button.on{background:rgba(42,171,238,.18);border-color:var(--sky);color:var(--sky);box-shadow:0 0 0 3px rgba(56,189,248,.12)}
.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:50px;padding:0 14px;border-radius:15px;background:rgba(3,12,26,.6);border:1px solid var(--line2);color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input::placeholder{color:var(--dim2)}
.fld input:focus{border-color:var(--sky);box-shadow:0 0 0 3px rgba(56,189,248,.18)}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.pay{margin-top:12px;border-radius:16px;padding:2px 12px;background:var(--glass);border:1px solid var(--line)}
.pay .r{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;font-size:11.5px;color:var(--dim)}
.pay .r+.r{border-top:1px dashed var(--line)}
.pay .r b{color:var(--ink);font-weight:800}
.pay .r b.g{color:var(--ok)}
.cpy{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 10px;border-radius:10px;background:rgba(42,171,238,.12);border:1px solid var(--line2);font-weight:800;font-size:12px}
.cpy svg{width:14px;height:14px;color:var(--sky)}
.warn{display:flex;align-items:flex-start;gap:8px;margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(252,211,77,.08);
  border:1px solid rgba(252,211,77,.25);color:#FDE68A;font-size:11px;line-height:1.9}
.warn svg{width:17px;height:17px;flex:0 0 auto;margin-top:2px}
.btn{position:relative;overflow:hidden;width:100%;height:52px;margin-top:14px;border-radius:16px;background:var(--grad);color:#03101F;font-weight:900;font-size:14px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 14px 26px -14px rgba(42,171,238,.95)}
.btn:after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);animation:shine 4s ease-in-out infinite}
.btn svg{width:18px;height:18px}
.btn[disabled]{opacity:.45;filter:grayscale(.4)}
.btn[disabled]:after{display:none}
.btn.gh{background:var(--glass);color:var(--ink);border:1px solid var(--line2);box-shadow:none}
.btn.gh:after{display:none}
.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(42,171,238,.08);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}

.links{display:grid;gap:9px;margin-top:14px}
.xl{position:relative;overflow:hidden;display:flex;align-items:center;gap:12px;min-height:62px;padding:10px 12px;border-radius:18px;text-align:right;
  background:linear-gradient(90deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.xl .ic{width:42px;height:42px;flex:0 0 auto;border-radius:14px;display:grid;place-items:center;color:#fff;box-shadow:0 10px 20px -12px #000}
.xl .ic svg{width:21px;height:21px}
.xl.ig .ic{background:linear-gradient(45deg,#F58529,#DD2A7B 50%,#8134AF)}
.xl.num .ic{background:linear-gradient(135deg,#22C55E,#2563EB)}
.xl.sup .ic{background:rgba(42,171,238,.16);color:var(--sky)}
.xl span{flex:1;min-width:0;font-weight:800;font-size:13px}
.xl span small{display:block;font-size:10px;color:var(--dim);font-weight:600}
.xl .ch{width:18px;height:18px;color:var(--dim);animation:nud 1.8s ease-in-out infinite}
@keyframes nud{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(-4px,0,0)}}

.ov{position:fixed;inset:0;z-index:40;background:rgba(1,5,12,.6);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;
  border-radius:26px 26px 0 0;padding:8px 16px calc(18px + var(--safe));border-top:1px solid var(--line2);
  background:linear-gradient(180deg,rgba(14,38,70,.97),rgba(5,16,33,.99));box-shadow:0 -20px 50px -20px rgba(42,171,238,.35);
  transform:translate3d(0,105%,0);transition:transform .34s cubic-bezier(.2,.85,.25,1)}
.sh.on{transform:none}
.grab{width:42px;height:4px;border-radius:4px;background:rgba(143,169,199,.35);margin:0 auto 10px}
.sh .st{display:flex;align-items:flex-start;gap:12px}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#03101F;flex:0 0 auto;box-shadow:0 10px 22px -10px rgba(42,171,238,.9)}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.06);flex:0 0 auto}
.sh .x svg{width:16px;height:16px}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:50px;height:50px;border-radius:15px;background:var(--glass);border:1px solid var(--line2);font-size:20px;font-weight:900;color:var(--sky)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:30px;padding:0 11px;border-radius:10px;background:var(--glass);border:1px solid var(--line);font-size:11px;font-weight:800;color:var(--dim)}
.qchips button.on{border-color:var(--cy);color:var(--cy);background:rgba(94,234,212,.1)}
.sum{margin-top:14px;border-radius:18px;padding:12px 14px;background:linear-gradient(120deg,rgba(42,171,238,.16),rgba(94,234,212,.06));border:1px solid var(--line2)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t b{font-size:19px;font-weight:900;color:var(--cy)}
.sum div.lo b{color:var(--red)}
.res{text-align:center;padding:6px 0 4px}
.res .rc{position:relative;width:66px;height:66px;margin:4px auto 10px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#03101F}
.res .rc:after{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid rgba(94,234,212,.6);animation:rpl 1.8s ease-out infinite}
.res .rc svg{width:32px;height:32px}
.res b{display:block;font-size:15px;font-weight:900}
.res small{font-size:12px;color:var(--cy);font-weight:800}

.toast{position:fixed;left:16px;right:16px;bottom:calc(20px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:16px;background:rgba(12,32,60,.96);border:1px solid var(--line2);font-size:12px;font-weight:700;
  -webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  transform:translate3d(0,140%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px #000}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:var(--ok)}.toast.er svg{color:var(--red)}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate>svg{width:64px;height:64px;color:var(--tg);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
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
    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.8 4.5 5.8v5.6c0 4.6 3.1 8.3 7.5 9.8 4.4-1.5 7.5-5.2 7.5-9.8V5.8z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="8" y="8" width="12.5" height="12.5" rx="2.5"/><path d="M16 8V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7.5A2.5 2.5 0 0 0 6 16h2"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 2.5 10 14M21.5 2.5l-7 19-4.5-7.5-7.5-4.5z"/></symbol>
  </defs>
</svg>
<div class="sky" aria-hidden="true"><i class="bl b1"></i><i class="bl b2"></i><i class="bl b3"></i><i class="rb"></i><i class="pt"></i><i class="gd"></i><i class="fp f1"><svg><use href="#i-plane"/></svg></i><i class="fp f2"><svg><use href="#i-plane"/></svg></i><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b></div>

<div class="app">
  <div class="hd0">
    <header class="hdr">
      <div class="hrow">
        <div class="ava"><span id="ava"></span></div>
        <div class="who"><b id="uName">—</b><small><i class="dot"></i><span>آنلاین · ثبتِ خودکار</span></small></div>
        <button class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em><i><svg><use href="#i-plus"/></svg></i></button>
        <button class="ib" id="supBtn" aria-label="پشتیبانی"><svg><use href="#i-headset"/></svg></button>
      </div>
      <nav class="tabs" id="tabs">
        <span class="ind" id="ind"></span>
        <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
        <button data-go="list"><svg><use href="#i-grid"/></svg>سرویس‌ها</button>
        <button data-go="orders"><svg><use href="#i-list"/></svg>سفارش‌ها<span class="bd" id="ordN"></span></button>
        <button data-go="wallet"><svg><use href="#i-wallet"/></svg>کیف پول</button>
      </nav>
    </header>
  </div>

  <section class="pg on" id="pg-home">
    <div class="hero"><i class="rim"></i><div class="in">
      <div class="art" aria-hidden="true"><i class="o2"></i><i class="o1"></i><i class="rp"></i><i class="rp r2"></i><i class="st"></i><i class="st s2"></i>
        <svg viewBox="0 0 120 120"><defs><linearGradient id="gp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7DD3FC"/><stop offset="1" stop-color="#2AABEE"/></linearGradient></defs>
        <circle cx="60" cy="60" r="44" fill="url(#gp)"/><path d="M34 58.5 84 39c2.3-.9 4.3.6 3.6 3.8l-8.5 40c-.6 2.8-2.3 3.5-4.7 2.2l-13-9.6-6.3 6c-.7.7-1.3 1.3-2.6 1.3l.9-13.3 24.3-22c1-.9-.2-1.4-1.6-.5l-30 18.9-12.9-4c-2.8-.9-2.9-2.8.6-4.2z" fill="#fff"/></svg></div>
      <div class="tx">
        <span class="kick"><i></i>آنلاین · شروعِ خودکار</span>
        <h1 id="hTitle">__TITLE__</h1>
        <p id="hTag">__TAG__</p>
      </div>
      <button class="go" data-go="list"><svg><use href="#i-plane"/></svg>ثبت سفارش</button>
      <div class="kpis"><div><b id="kN">—</b><small>سرویسِ فعال</small></div><div><b id="kF">—</b><small>شروع از / ۱۰۰۰</small></div><div><b>۲۴/۷</b><small>ثبتِ خودکار</small></div></div>
    </div></div>
    <div class="tick"><div class="tr" id="tick"></div></div>
    <div class="hd"><h3>دسته‌بندی‌ها</h3></div>
    <div class="bento" id="bento"></div>
    <div class="hd" id="popH"><h3>پیشنهادِ امروز</h3><button data-go="list">همه<svg><use href="#i-chev"/></svg></button></div>
    <div class="list" id="pop"></div>
    <div class="hd"><h3>چطور کار می‌کند؟</h3></div>
    <div class="steps">
      <div><i>۱</i><b>سرویس را بزن</b><small>ممبر، بازدید، …</small></div>
      <div><i>۲</i><b>لینک و تعداد</b><small>بدونِ نیاز به رمز</small></div>
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
    <div class="hd" style="margin-top:4px"><h3>سفارش‌های من</h3><button id="oRef"><svg><use href="#i-refresh"/></svg>تازه کن</button></div>
    <div id="olist"></div>
  </section>

  <section class="pg" id="pg-wallet">
    <div class="wal"><svg class="wm"><use href="#i-plane"/></svg>
      <div class="tp"><span>کیف پولِ شما</span><i class="chip"></i></div>
      <small>موجودی</small><b><span id="wBal">…</span><em>تومان</em></b>
      <div class="ft">یک کیف پول برای همه‌ی بخش‌های ربات</div>
    </div>
    <div class="fld"><label>مبلغِ شارژ (تومان)</label><input id="tAmt" inputmode="numeric" placeholder="مثلا ۱۰۰٬۰۰۰"></div>
    <div class="qa" id="qa"></div>
    <div id="payInfo"></div>
    <button class="btn" id="tGo"><svg><use href="#i-wallet"/></svg>درخواستِ شارژ</button>
    <div class="note" id="tNote">فاکتور و مقصدِ پرداخت داخلِ ربات برایتان فرستاده می‌شود؛ بعد از واریز، «ارسال رسید» را بزنید.</div>
    <div class="links"><button class="xl sup" id="supBtn2"><span class="ic"><svg><use href="#i-headset"/></svg></span><span>پشتیبانی<small>سوال یا مشکل دارید؟ همین‌جا بپرسید</small></span><svg class="ch"><use href="#i-chev"/></svg></button></div>
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

function priceRow(i, k){
  var c = CAT[i.c] || {};
  return '<button class="tk" style="--i:' + Math.min(k || 0, 14) + '" data-sv="' + esc(i.i) + '"><span class="ic">' + ico(c.ic || 'spark') + '</span>' +
    '<span class="m"><b>' + esc(i.n) + '</b><small><span class="lv"><i></i>فعال</span>' +
    '<span>' + esc(c.n || '') + '</span><span>' + fa(i.mn) + ' تا ' + fa(i.mx) + '</span>' + (i.r ? '<span class="g">ضمانتِ ریزش</span>' : '') +
    '</small></span><span class="p"><b>' + fa(i.p) + '</b><small>تومان / ۱۰۰۰</small></span></button>';
}
var TICK = [['bolt', 'شروعِ خودکار در چند دقیقه'], ['shield', 'بدونِ نیاز به رمز'], ['refresh', 'ضمانتِ ریزش در سرویس‌های مشخص'],
            ['chart', 'گزارشِ لحظه‌ایِ پیشرفت'], ['headset', 'پشتیبانیِ ۲۴ ساعته']];
function drawHome(){
  var th = TICK.map(function(t){ return '<span>' + ico(t[0]) + esc(t[1]) + '</span>'; }).join('');
  $('tick').innerHTML = th + th;
  countUp($('kN'), ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  if (min) countUp($('kF'), min); else $('kF').textContent = '—';
  $('bento').innerHTML = CATS.length ? CATS.map(function(c, k){
    return '<button class="bt" style="--i:' + k + '" data-go="list" data-cat="' + esc(c.id) + '"><span class="ic">' + ico(c.ic) + '</span>' +
      '<span><b>' + esc(c.n) + '</b><small>' + fa(c.c) + ' سرویس</small><span class="pr">از ' + fa(c.f) + ' تومان</span></span></button>';
  }).join('') : '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';
  var pop = ITEMS.slice().sort(function(a, b){ return a.p - b.p; }).slice(0, 4);
  $('pop').innerHTML = pop.map(priceRow).join('');
  $('popH').classList.toggle('hid', !pop.length);
  var xl = '';
  if ((B.links || {}).ig) xl += '<button class="xl ig" data-open="ig"><span class="ic">' + ico('camera') + '</span><span>خدمات اینستاگرام<small>فالوور، لایک، ویو و کامنت</small></span>' + ico('chev', 'ch') + '</button>';
  if ((B.links || {}).num) xl += '<button class="xl num" data-open="num"><span class="ic">' + ico('sim') + '</span><span>شماره مجازی تلگرام<small>تحویلِ آنی، کد همین‌جا</small></span>' + ico('chev', 'ch') + '</button>';
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
  $('slist').innerHTML = l.length ? l.map(priceRow).join('') : (ITEMS.length
    ? '<div class="emp">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>'
    : '<div class="emp">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>');
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
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('list') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="btn" data-go="list">' + ico('grid') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o, k){
    var c = CAT[o.c] || {};
    return '<div class="or" style="--i:' + Math.min(k, 10) + '"><div class="h"><span class="ic">' + ico(c.ic || 'spark') + '</span><div><b>' + esc(o.n) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
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
      (B.bot ? '<button class="btn" id="toBot">' + ico('send') + 'رفتن به ربات و پرداخت</button>' : '') +
      '<button class="btn gh" id="resX">بستن</button>';
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
  try { TG.setHeaderColor && TG.setHeaderColor('#030A17'); TG.setBackgroundColor && TG.setBackgroundColor('#030A17'); TG.setBottomBarColor && TG.setBottomBarColor('#030A17'); } catch(e){}
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
