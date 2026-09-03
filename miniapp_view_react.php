<?php
/**
 * 💫 نمای مینی‌اپ «ری‌اکشن و استوری» — تم «منشور» (Prism), شبِ بنفش‌ارغوانی
 *
 * کاملا جدا از دو مینی‌اپ دیگر: نه رنگش یکی است، نه حسش — بنفش‌ارغوانیِ
 * تیره، جدا از آبیِ اورورا (تلگرام) و سرمه‌ای اقیانوس (شماره). چهار
 * رنگِ تاکید دارد: آبی، سبز، بنفش، قرمز — تا هم دسته‌های مختلف
 * (ری‌اکشنِ پست/استوری/بقیه) از هم جدا باشند، هم رنگِ خطا/موفقیت
 * روشن و خوانا بماند.
 *
 * اسکلت (ساختار HTML+JS) کاملا با مینی‌اپ «خدمات تلگرام» مشترک است —
 * maTplApp()/maTplBody() در miniapp_view_tg.php — همان موتورِ امن و
 * آزموده (initData، هدرهای امنیتی، صفحه‌ی لودینگ، سبد خرید). صفحه‌ی
 * لودینگ هم مشترک است، ولی محتوایش (آیکون/شکل/متن/تگ‌ها) از رویِ
 * placeholderهایِ __SPLASH_*__ همین‌جا پر می‌شود — پس با اینکه کد
 * یکی است، ظاهرش با تلگرام قاطی نمی‌شود. فقط پوسته‌ی CSS اینجا عوض می‌شود.
 */

function maViewReact($a, $boot) {
    $th   = $a['theme'] ?? [];
    $c1   = $th['c1'] ?? '#2F6FED';
    $c2   = $th['c2'] ?? '#17C978';
    $c3   = $th['c3'] ?? '#8B5CF6';
    $c4   = $th['c4'] ?? '#F23557';
    $glow = !empty($th['glow']) ? '1' : '0';
    $grain= !empty($th['grain']) ? '1' : '0';
    $fx   = (string)maFxLevel($th);

    return strtr(maTplApp(maSkinPrism()), [
        '__C1__'    => $c1,
        '__C2__'    => $c2,
        '__C3__'    => $c3,
        '__C4__'    => $c4,
        '__GLOW__'  => $glow,
        '__GRAIN__' => $grain,
        '__FX__'    => $fx,
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        // 💫 سیلوئت و متنِ خودِ این اپ — جدا از خدماتِ تلگرام، حتی
        // با این‌که پوسته‌ی مشترک استفاده می‌کند (خواستِ کارفرما: هرکدام واقعا متفاوت باشد)
        '__SPLASH_SHAPE__' => 'blob',
        '__SPLASH_ICON__'  => '💫',
        '__SPLASH_SUB__'   => 'ری‌اکشن پست · استوری · بازدید و اشتراک‌گذاری',
        '__SPLASH_TAGS__'  => '<span>❤️ ری‌اکشن</span><span>📖 استوری</span><span>👁 بازدید</span>',
        '__SPLASH_PWR__'   => 'در حال آماده‌سازی…',
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/**
 * 🌤 پوسته‌ی «منشور» — مینی‌اپ ری‌اکشن و استوری
 * پس‌زمینه‌ی تیره‌ی بنفش‌ارغوانی، کارت‌های شیشه‌ایِ روی آن، چهار رنگِ
 * تاکیدِ زنده (آبی/سبز/بنفش/قرمز)، انیمیشن‌های نرم روی همان کلاس‌هایی
 * که موتورِ مشترک می‌سازد.
 */
function maSkinPrism() {
    return <<<'CSS'
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --c4:__C4__;
  /* 💫 شبِ بنفش‌مایل‌به‌ارغوانی — سومین هویتِ تیره، جدا از آبیِ اورورا
     و سرمه‌ای اقیانوس، تا هر سه مینی‌اپ واقعا حسِ جدا داشته باشند. */
  --bg:#0D0714;
  --ink:#F6EFFF; --dim:#A899C2; --line:rgba(255,255,255,.12);
  --pane:rgba(255,255,255,.055); --pane2:rgba(255,255,255,.035);
  --blur:18px;
  --r:24px; --safe:env(safe-area-inset-bottom,0px);
  color-scheme:dark;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}
img{max-width:100%}

/* ═══ آسمانِ شب ═══ لکه‌های رنگیِ درخشان روی زمینه‌ی تیره، بدون filter */
.sky{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(60vw 60vw at 88% -8%,color-mix(in srgb,var(--c4) 34%,transparent),transparent 68%),
    radial-gradient(54vw 54vw at 4% 30%,color-mix(in srgb,var(--c3) 30%,transparent),transparent 66%),
    radial-gradient(48vw 48vw at 80% 106%,color-mix(in srgb,var(--c1) 26%,transparent),transparent 64%),
    var(--bg)}
.sky:after{content:"";position:absolute;inset:0;opacity:0;
  background:radial-gradient(50vw 50vw at 22% 10%,color-mix(in srgb,var(--c2) 22%,transparent),transparent 62%)}
body.fx2 .sky:after{animation:breathe 9s ease-in-out infinite}
@keyframes breathe{0%,100%{opacity:0}50%{opacity:.9}}
#stars{display:none}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:radial-gradient(122% 78% at 50% 0%,transparent 42%,var(--bg) 96%)}
.grain{display:none}
@media (prefers-reduced-motion:reduce){ .sky:after{animation:none!important} }

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 15px calc(112px + var(--safe))}

/* ═══ سربرگ ═══ */
.top{display:flex;align-items:center;gap:11px;margin:14px 0 4px;padding:11px 12px;border-radius:22px;
  border:1px solid var(--line);background:var(--pane);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  box-shadow:0 10px 28px -20px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06)}
body.fx0 .top{backdrop-filter:none;-webkit-backdrop-filter:none}
.ava{width:46px;height:46px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  font-weight:900;font-size:18px;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  box-shadow:0 0 0 2px var(--pane),0 0 0 4px color-mix(in srgb,var(--c1) 30%,transparent)}
.ava img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:14.5px;font-weight:800;letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chipbal{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:5px 6px 5px 10px;border-radius:12px;
  border:1px solid var(--line);background:rgba(255,255,255,.06);font-size:12.5px;font-weight:900;cursor:pointer}
.chipbal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.chipbal b{width:20px;height:20px;border-radius:7px;display:grid;place-items:center;font-size:14px;line-height:1;
  color:#fff;background:linear-gradient(135deg,var(--c2),var(--c1))}
.cta{flex:0 0 auto;padding:11px 14px;border:0;border-radius:15px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3))}
.cta:active{transform:scale(.96)}
body.glow-on .cta{box-shadow:0 10px 22px -12px color-mix(in srgb,var(--c1) 65%,transparent)}

/* 🔔 زنگ */
.bell{position:relative;flex:0 0 auto;width:38px;height:38px;border-radius:13px;cursor:pointer;
  border:1px solid var(--line);background:rgba(255,255,255,.06);
  color:inherit;display:grid;place-items:center;font-size:16px;font-family:inherit}
.bell:active{transform:scale(.94)}
.bell .bdot{position:absolute;top:6px;inset-inline-end:6px;width:8px;height:8px;border-radius:99px;
  background:var(--c4);opacity:0;transform:scale(.4);transition:opacity .2s,transform .2s}
.bell.has .bdot{opacity:1;transform:scale(1);animation:bping 1.8s ease-out infinite}
@keyframes bping{0%,60%,100%{box-shadow:0 0 0 0 color-mix(in srgb,var(--c4) 55%,transparent)}30%{box-shadow:0 0 0 6px transparent}}
@media (prefers-reduced-motion:reduce){.bell.has .bdot{animation:none}}

/* 🔔 کارت‌های اعلان */
.note{border:1px solid var(--line);background:var(--pane2);
  border-radius:16px;padding:13px 14px;margin-bottom:10px;position:relative;overflow:hidden}
.note.new::before{content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:3px;
  background:linear-gradient(180deg,var(--c1),var(--c3))}
.note .nh{display:flex;align-items:center;gap:8px;margin-bottom:5px}
.note .nh i{font-style:normal;font-size:16px;line-height:1}
.note .nh b{font-size:13px;font-weight:800;flex:1;min-width:0}
.note .nh time{font-size:10px;color:var(--dim);white-space:nowrap}
.note p{font-size:12px;color:var(--dim);line-height:1.8;white-space:pre-line;margin:0}
.note .ncp{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px}
.note .ncp button{border:1px solid var(--line);background:rgba(255,255,255,.06);
  color:inherit;border-radius:10px;padding:5px 10px;font-size:11px;font-family:inherit;cursor:pointer}
.note .ncp button:active{transform:scale(.95)}
.wsub{margin:0 0 10px;font-size:11.5px;color:var(--dim);text-align:center}

/* ═══ صفحه‌ها ═══ */
.pg{display:none;animation:pgIn .3s cubic-bezier(.2,.9,.3,1)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .pg{animation:none} }

.sect{display:flex;align-items:baseline;justify-content:space-between;margin:20px 2px 11px}
.sect h2{margin:0;font-size:11.5px;font-weight:800;letter-spacing:1.2px;color:var(--dim);
  display:flex;align-items:center;gap:8px}
.sect h2 s{text-decoration:none;width:5px;height:16px;border-radius:3px;
  background:linear-gradient(180deg,var(--c1),var(--c3))}
.sect a{font-size:11.5px;color:var(--c1);font-weight:700;cursor:pointer}

/* 🛡 چند خطِ اعتماد پشتِ هم */
.trustwrap{display:flex;flex-direction:column;gap:8px;margin:15px 2px 2px}
.trust{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:14px;
  background:color-mix(in srgb,var(--c2) 9%,transparent);
  border:1px solid color-mix(in srgb,var(--c2) 22%,transparent)}
.trust i{font-style:normal;font-size:17px;flex:0 0 auto}
.trust b{display:block;font-size:12.5px;font-weight:800;color:color-mix(in srgb,var(--c2) 70%,black)}
.trust span{display:block;font-size:11px;color:var(--dim);line-height:1.7;margin-top:1px}

/* ═══ کارت کیف پول ═══ گرادیانِ رنگی، متنِ سفید — تنها سطحِ کاملا رنگی صفحه */
.purse{position:relative;overflow:hidden;padding:19px 18px;border-radius:26px;
  border:1px solid rgba(255,255,255,.16);color:#fff;
  background:linear-gradient(140deg,var(--c1),var(--c3) 68%,var(--c2))}
.purse:before{content:"";position:absolute;inset:0;opacity:.5;pointer-events:none;
  background:
    radial-gradient(70% 120% at 100% 0%,rgba(255,255,255,.28),transparent 62%),
    radial-gradient(60% 100% at 0% 100%,rgba(255,255,255,.16),transparent 60%)}
.purse .spark{position:absolute;inset:0;transform:translateX(-100%);pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.32),transparent)}
body.fx2 .purse .spark{animation:spark 5s ease-in-out infinite}
@keyframes spark{0%,72%{transform:translateX(-100%)}100%{transform:translateX(120%)}}
.purse .lbl{position:relative;font-size:11.5px;color:rgba(255,255,255,.85);margin-bottom:6px;display:flex;align-items:center;gap:6px}
.purse .val{position:relative;font-size:31px;font-weight:900;letter-spacing:-1px;line-height:1.1;color:#fff}
.purse .cur{font-size:13px;font-weight:600;color:rgba(255,255,255,.8);margin-inline-start:5px}
.purse .acts{position:relative;display:flex;gap:9px;margin-top:16px}
.purse .acts button{flex:1;padding:12px;border:0;border-radius:15px;cursor:pointer;
  font-family:inherit;font-size:12.5px;font-weight:800;color:var(--c1);
  background:#fff}
.purse .acts button.g{color:#fff;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.32)}
.purse .acts button:active{transform:scale(.97)}

/* ═══ کارت خوش‌آمد ═══ */
.welcome{margin-top:16px;padding:22px 18px;border-radius:26px;text-align:center;position:relative;overflow:hidden;
  border:1px solid var(--line);background:var(--pane2);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
body.fx0 .welcome{backdrop-filter:none;-webkit-backdrop-filter:none}
.welcome:before{content:"";position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(118deg,color-mix(in srgb,var(--c1) 16%,transparent) 0%,transparent 45%)}
.logo{width:82px;height:82px;margin:0 auto 13px;position:relative}
.logo svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.logo i{position:absolute;inset:-6px;border-radius:50%;border:1.5px dashed color-mix(in srgb,var(--c1) 45%,transparent)}
.logo i:nth-child(2){inset:2px;border-style:solid;border-color:color-mix(in srgb,var(--c3) 35%,transparent)}
.logo .halo{position:absolute;inset:-18px;border-radius:50%;z-index:0;
  background:radial-gradient(circle,color-mix(in srgb,var(--c1) 30%,transparent),transparent 68%)}
body.fx2 .logo i{animation:spin 14s linear infinite}
body.fx2 .logo i:nth-child(2){animation:spin 9s linear infinite reverse}
body.fx2 .logo svg{animation:float 4.5s ease-in-out infinite}
body.fx2 .logo .halo{animation:glow 3.6s ease-in-out infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@keyframes glow{0%,100%{opacity:.4;transform:scale(.94)}50%{opacity:.85;transform:scale(1.08)}}
@media (prefers-reduced-motion:reduce){ .logo i,.logo svg,.logo .halo{animation:none!important} }
.welcome h2{position:relative;margin:0 0 8px;font-size:18px;font-weight:900;letter-spacing:-.3px;
  background:linear-gradient(92deg,var(--c1),var(--c3));-webkit-background-clip:text;background-clip:text;color:transparent}
.welcome p{position:relative;margin:0;font-size:12.5px;line-height:1.95;color:var(--dim)}

/* ═══ میان‌بر دسته‌ها ═══ */
.rail{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail .rc{flex:0 0 auto;width:82px;padding:13px 6px;border-radius:20px;text-align:center;cursor:pointer;
  border:1px solid var(--line);background:var(--pane);transition:border-color .18s,transform .18s,background .18s;
  box-shadow:0 6px 18px -14px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.06)}
.rail .rc:active{transform:scale(.95)}
.rail .rc .ico{margin:0 auto 7px}
.rail .rc span{display:block;font-size:10.5px;font-weight:800;color:var(--dim);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rail .rc.on{border-color:color-mix(in srgb,var(--c1) 55%,transparent)}
.rail .rc.on span{color:var(--ink)}

/* ═══ آیکون شیشه‌ای ═══ خودش یک نشانِ رنگی است، فارغ از پس‌زمینه‌ی روشن */
.ico{position:relative;width:38px;height:38px;border-radius:14px;display:grid;place-items:center;color:#fff;
  background-image:linear-gradient(158deg,var(--c1),var(--c3));
  border:1px solid rgba(255,255,255,.24);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 6px 16px -8px color-mix(in srgb,var(--c1) 55%,transparent);
  overflow:hidden;
  transition:transform .26s cubic-bezier(.34,1.56,.64,1),box-shadow .26s,border-color .26s}
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(115deg,transparent 41%,rgba(255,255,255,.6) 50%,transparent 59%);
  transform:translateX(-130%)}
.ico:after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:radial-gradient(120% 70% at 50% -10%,rgba(255,255,255,.35),transparent 60%)}
.ico svg{position:relative;width:21px;height:21px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
.ico .ico-em{font-size:19px;font-style:normal;line-height:1}
.on>.ico,.rc.on .ico{box-shadow:inset 0 1px 0 rgba(255,255,255,.5),0 10px 22px -8px color-mix(in srgb,var(--c1) 60%,transparent)}
.on>.ico:before,.rc.on .ico:before{animation:icoSheen 2.8s cubic-bezier(.4,0,.2,1) infinite}
@keyframes icoSheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}
.i-spin{transform-box:fill-box;transform-origin:50% 50%}
.on .i-spin {animation:icoSpin 5.5s linear infinite}
.on .i-pulse{animation:icoPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-float{animation:icoFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-lid  {animation:icoLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
.on .i-draw {stroke-dasharray:64;animation:icoDraw 2.6s ease-in-out infinite}
.on .i-tick {animation:icoTick 4s steps(12) infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes icoSpin {to{transform:rotate(360deg)}}
@keyframes icoPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.75}}
@keyframes icoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes icoLid  {0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@keyframes icoDraw {0%{stroke-dashoffset:64}45%,100%{stroke-dashoffset:0}}
@keyframes icoTick {to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){
  .ico,.ico:before,.on>.ico:before,.rc.on .ico:before,
  .on .i-spin,.on .i-pulse,.on .i-float,.on .i-lid,.on .i-draw,.on .i-tick{animation:none!important;transition:none!important}
}

/* ═══ جستجو و چیپ دسته ═══ */
.find{position:relative;margin:4px 0 12px}
.find input{width:100%;padding:13px 42px 13px 14px;border-radius:16px;border:1px solid var(--line);
  background:var(--pane);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none;transition:.2s}
.find input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 16%,transparent)}
.find span{position:absolute;top:50%;right:14px;transform:translateY(-50%);opacity:.5;font-size:15px}
.tabs{display:flex;gap:7px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs b{flex:0 0 auto;padding:9px 15px;border-radius:13px;cursor:pointer;font-size:12px;font-weight:800;
  color:var(--dim);border:1px solid var(--line);background:var(--pane);transition:.18s;white-space:nowrap}
.tabs b.on{color:#fff;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c3))}

/* ═══ شبکه‌ی محصول — دوتا دوتا ═══ */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.tile{position:relative;overflow:hidden;padding:14px 12px 12px;border-radius:22px;cursor:pointer;contain:content;
  border:1px solid var(--line);background:var(--pane);
  box-shadow:0 10px 26px -22px rgba(20,25,45,.5);
  display:flex;flex-direction:column;min-height:172px;
  transition:border-color .18s,transform .14s,box-shadow .18s;
  /* 🐛 بدونِ حذفِ backwards، هر کارتی که نوبتِ delay-اش نرسیده باشه
     (تا ۳۰۰ms) واقعاً opacity:0 و نامرئیه — با اسکرولِ سریع دقیقاً
     همون لحظه که کاربر بهش می‌رسه، «محصولِ ناپدید» به‌نظر می‌رسه. */
  animation:rise .4s cubic-bezier(.2,.9,.3,1)}
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.grid:not(.first) .tile{animation:none}
/* 📜 جلوه‌ی ورود/خروجِ اسکرولی برداشته شد — روی گوشیِ کاربر دقیقا
   اسکرولِ رو-به-بالا رو شدیدا کند می‌کرد. */
@media (prefers-reduced-motion:reduce){ .tile{animation:none} }
.tile:active{transform:scale(.975)}
.tile:before{content:"";position:absolute;inset:0;opacity:0;transition:opacity .25s;pointer-events:none;
  background:linear-gradient(150deg,color-mix(in srgb,var(--c1) 10%,transparent),transparent 62%)}
.tile.hot:before{opacity:1}
.tile.hot{border-color:color-mix(in srgb,var(--c1) 38%,transparent);box-shadow:0 14px 30px -20px color-mix(in srgb,var(--c1) 45%,transparent)}
.tile.hide{display:none}
/* 🐢 عمدی، ساکن است — همان فیکسِ عملکردیِ tg.php: قبلا هر کارت،
   همیشه (حتی بیرونِ دید) دو انیمیشنِ بی‌پایان داشت که با شصت-هفتاد
   کارت روی گوشیِ ضعیف هنگ می‌کرد. حالا فقط کارتِ روی صفحه (کلاسِ
   instage) یک تبدیلِ سبک دارد. */
.orb{position:relative;width:52px;height:52px;border-radius:18px;display:grid;place-items:center;font-size:25px;
  margin-bottom:11px;color:#fff;
  background-image:linear-gradient(140deg,var(--c1),var(--c3));
  border:1px solid rgba(255,255,255,.22)}
@keyframes orbFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-3px) scale(1.04)}}
.tile.instage .orb{animation:orbFloat 2.4s ease-in-out infinite}
body.glow-on .tile.instage .orb{animation:orbFloat 2.4s ease-in-out infinite,orbPulse 2.4s ease-in-out infinite}
@keyframes orbPulse{0%,100%{box-shadow:0 10px 24px -13px color-mix(in srgb,var(--c1) 60%,transparent)}50%{box-shadow:0 15px 30px -10px color-mix(in srgb,var(--c1) 60%,transparent)}}
body.fx0 .tile.instage .orb{animation:none}
@media (prefers-reduced-motion:reduce){ .orb,.tile.instage .orb{animation:none!important} }
/* هر دسته رنگِ آیکونِ خودش را دارد: ری‌اکشنِ پست=آبی، استوری=سبز، بقیه=بنفش */
.tile[data-cat="c_storyr"] .orb{background-image:linear-gradient(140deg,var(--c2),var(--c1))}
.tile[data-cat="c_postsv"] .orb{background-image:linear-gradient(140deg,var(--c3),var(--c4))}
.tile h3{position:relative;margin:0;font-size:13px;font-weight:800;line-height:1.55;color:var(--ink);
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile p{position:relative;margin:4px 0 0;font-size:10.5px;color:var(--dim);line-height:1.65;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile .foot{position:relative;margin-top:auto;padding-top:11px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .cost b{display:block;font-size:15px;font-weight:900;letter-spacing:-.4px;
  background:linear-gradient(90deg,var(--c1),var(--c3));-webkit-background-clip:text;background-clip:text;color:transparent}
.tile .cost i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:1px}
.tile .plus{width:29px;height:29px;flex:0 0 auto;border-radius:11px;display:grid;place-items:center;
  font-size:17px;font-weight:700;color:#fff;line-height:1;
  background:linear-gradient(135deg,var(--c1),var(--c3))}
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
body.fx0 .prof{backdrop-filter:none;-webkit-backdrop-filter:none}
.prof:before{content:"";position:absolute;inset:0;opacity:.5;pointer-events:none;
  background:radial-gradient(70% 120% at 100% 0%,color-mix(in srgb,var(--c1) 22%,transparent),transparent 62%)}
.prof .big{position:relative;width:64px;height:64px;border-radius:22px;flex:0 0 auto;overflow:hidden;
  display:grid;place-items:center;font-size:26px;font-weight:900;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3))}
.prof .big img{width:100%;height:100%;object-fit:cover}
.prof .d{position:relative;flex:1;min-width:0}
.prof .d b{display:block;font-size:16px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prof .d span{display:block;font-size:11.5px;color:var(--dim);margin-top:4px;direction:ltr;text-align:right}
.prof .d code{display:inline-block;margin-top:7px;font-size:10.5px;padding:3px 9px;border-radius:8px;
  background:var(--pane);border:1px solid var(--line);direction:ltr;font-family:ui-monospace,monospace}

.pane{margin-top:13px;padding:16px;border-radius:22px;border:1px solid var(--line);background:var(--pane);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
body.fx0 .pane{backdrop-filter:none;-webkit-backdrop-filter:none}
.pane h3{margin:0 0 12px;font-size:13px;font-weight:900;display:flex;align-items:center;gap:7px}
.card-no{padding:14px;border-radius:16px;
  border:1px dashed color-mix(in srgb,var(--c1) 40%,transparent);background:var(--pane2)}
.card-no b{display:block;font-size:19px;font-weight:900;letter-spacing:1.5px;direction:ltr;text-align:center;
  font-family:ui-monospace,monospace;white-space:nowrap;overflow-x:auto;
  scrollbar-width:none;color:var(--c1)}
.card-no b::-webkit-scrollbar{display:none}
.card-no button{width:100%;margin-top:11px;padding:11px;border:0;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3))}
.card-no button:active{transform:scale(.985)}
.card-holder{margin-top:9px;font-size:11.5px;color:var(--dim)}
.card-holder b{color:var(--ink)}
.amt{display:flex;gap:9px;margin-top:13px}
.amt input{flex:1;min-width:0;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:var(--pane2);color:var(--ink);font-family:inherit;font-size:15px;font-weight:800;
  outline:none;text-align:center;transition:.2s}
.amt input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 16%,transparent)}
.quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.quick i{padding:7px 12px;border-radius:11px;font-style:normal;font-size:11.5px;font-weight:800;cursor:pointer;
  border:1px solid var(--line);background:var(--pane2);color:var(--dim)}
.quick i:active{background:color-mix(in srgb,var(--c1) 22%,transparent);color:#fff}
.link{display:flex;align-items:center;gap:11px;padding:14px;border-radius:16px;margin-top:9px;cursor:pointer;
  border:1px solid var(--line);background:var(--pane2);font-size:12.5px;font-weight:700}
.link:active{transform:scale(.985)}
.link em{flex:1;font-style:normal}
.link s{text-decoration:none;color:var(--dim);font-size:16px}

.void{text-align:center;padding:46px 20px;color:var(--dim);font-size:12.5px;line-height:1.9}
.void div{font-size:44px;margin-bottom:10px;opacity:.55}
.skel{height:172px;border-radius:22px;border:1px solid var(--line);
  background:linear-gradient(90deg,var(--pane),var(--pane2),var(--pane));
  background-size:200% 100%;animation:sh 1.3s linear infinite}
@keyframes sh{to{background-position:-200% 0}}

/* ═══ 👑 صفحه‌ی مدیریت — فقط برای مدیر ═══ */
.adm{display:none}
body.is-admin .adm{display:block}
/* 🐛 #pgAdm هم .pg است هم .adm — و «body.is-admin .adm» به‌خاطرِ
   سلکتورِ body یک واحد اسپسیفیسیتی بیشتر از «.pg.on» دارد، پس همیشه
   می‌برد، حتی وقتی صفحه‌ی مدیریت باز نیست. نتیجه: برای هر مدیری، روی
   هر صفحه‌ای، جعبه‌ی «👑 در حال خواندن…» همیشه‌روشن می‌ماند — دقیقا
   شبیهِ هنگ. این خط با ۴ کلاس، آن ترکیب را می‌بَرد. */
body.is-admin .pg.adm:not(.on){display:none}
.arow{display:flex;align-items:center;gap:11px;padding:12px 13px;border-radius:16px;margin-bottom:8px;
  border:1px solid var(--line);background:var(--pane);cursor:pointer}
.arow .e{width:38px;height:38px;flex:0 0 auto;border-radius:13px;display:grid;place-items:center;font-size:19px;
  background:var(--pane2);border:1px solid var(--line)}
.arow .m{flex:1;min-width:0}
.arow .m b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arow .m span{display:block;font-size:10px;color:var(--dim);margin-top:3px}
.arow .p{flex:0 0 auto;font-size:11.5px;font-weight:800;color:var(--c1)}
.arow.off{opacity:.5}
.aform .field{margin-bottom:11px}
.aform label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.aform input,.aform select,.aform textarea{width:100%;padding:12px;border-radius:14px;border:1px solid var(--line);
  background:var(--pane2);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none}
.aform textarea{min-height:64px;resize:vertical;font-size:12.5px}
.aform select{appearance:none}
.a2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.aswitch{display:flex;align-items:center;justify-content:space-between;padding:12px 13px;border-radius:14px;
  border:1px solid var(--line);background:var(--pane2);font-size:12.5px;font-weight:700;cursor:pointer}
.aswitch i{width:44px;height:25px;border-radius:13px;background:rgba(255,255,255,.14);position:relative;transition:.2s}
.aswitch i:after{content:"";position:absolute;top:3px;right:3px;width:19px;height:19px;border-radius:50%;
  background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(20,25,45,.3)}
.aswitch.on i{background:linear-gradient(135deg,var(--c1),var(--c3))}
.aswitch.on i:after{right:22px}

/* ═══ جزیره‌ی پایین ═══ شیشه‌ی روشن روی سفید */
.dock{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(11px + var(--safe));z-index:30;
  width:min(94vw,420px);display:flex;gap:3px;padding:7px;border-radius:26px;
  border:1px solid var(--line);background:rgba(255,255,255,.09);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  box-shadow:0 18px 44px -12px rgba(0,0,0,.65),inset 0 1px 0 rgba(255,255,255,.08)}
body.fx0 .dock{backdrop-filter:none;-webkit-backdrop-filter:none;background:#150A1E}
.dock b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;border-radius:19px;cursor:pointer;color:var(--dim);
  font-size:9.5px;font-weight:800;transition:color .16s,background .16s}
.dock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dock b.on{color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3))}
.dock b[data-p="adm"]{display:none}
body.is-admin .dock b[data-p="adm"]{display:flex}

/* ═══ شیت خرید ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(6,2,10,.55);backdrop-filter:blur(6px);
  opacity:0;pointer-events:none;transition:.28s}
.scrim.on{opacity:1;pointer-events:auto}
/* ⌨️ کیبورد باز = بلور خاموش — همان قاعده‌ای که در tg.php هست، اینجا
   جا افتاده بود؛ بدونش هر تقه روی فیلدهای شیت خرید یک لحظه هنگ می‌کرد. */
body.kb-open .scrim{backdrop-filter:none;-webkit-backdrop-filter:none;transition:opacity .28s}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .38s cubic-bezier(.2,.9,.25,1);
  background:color-mix(in srgb,var(--bg) 88%,#1F1030);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-radius:30px 30px 0 0;border-top:1px solid var(--line);
  padding:10px 17px calc(22px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -24px 60px -20px rgba(20,25,45,.35)}
.sheet.on{transform:none}
.grip{width:42px;height:4px;border-radius:4px;background:rgba(255,255,255,.22);margin:4px auto 16px}
.sheet .head{display:flex;align-items:center;gap:13px;margin-bottom:16px}
.sheet .head .orb{width:56px;height:56px;font-size:27px;margin:0}
.sheet .head h2{margin:0;font-size:16.5px;font-weight:900}
.sheet .head p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.7}

.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--dim);margin-bottom:7px}
.field input,.field textarea{width:100%;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:var(--pane2);color:var(--ink);font-family:inherit;font-size:14.5px;outline:none;transition:.2s}
.field textarea{min-height:80px;resize:vertical;font-size:13px}
.field input:focus,.field textarea:focus{border-color:var(--c1);
  box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 16%,transparent)}
.field .hint{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.7}
.field .selfrow{display:flex;align-items:center;gap:7px;margin-top:8px;flex-wrap:wrap}
.field .self{
  display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:11.5px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  border:1px solid transparent;
  box-shadow:0 8px 18px -12px color-mix(in srgb,var(--c1) 60%,transparent);transition:transform .16s ease,filter .16s ease
}
.field .self:active{transform:scale(.94)}
.field .self[disabled]{opacity:.45;pointer-events:none;box-shadow:none}
.field .self.done{filter:saturate(.5)}
.field .selfrow em{font-style:normal;font-size:10.5px;color:var(--dim);direction:ltr}
.lbl{font-size:10.5px;font-weight:800;letter-spacing:1.1px;color:var(--dim);margin:14px 0 9px}
.plans{display:grid;gap:9px}
.plans i{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:18px;cursor:pointer;
  font-style:normal;border:1px solid var(--line);background:var(--pane2);
  transition:border-color .18s,background .18s}
.plans i:active{transform:scale(.985)}
.plans i .pg{width:38px;height:38px;flex:0 0 auto;border-radius:13px;display:grid;place-items:center;font-size:20px;
  text-decoration:none;color:#fff;
  background:linear-gradient(140deg,var(--c1),var(--c3));
  border:1px solid rgba(255,255,255,.2)}
.plans i b{flex:1;min-width:0;font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plans i u{flex:0 0 auto;text-decoration:none;font-size:12px;font-weight:800;color:var(--c1)}
.plans i .chk{width:22px;height:22px;flex:0 0 auto;border-radius:7px;border:1.5px solid var(--line);
  display:grid;place-items:center;font-size:13px;font-style:normal;color:transparent}
.plans i.on{border-color:color-mix(in srgb,var(--c1) 60%,transparent);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 12%,transparent),transparent)}
.plans i.on .chk{border-color:transparent;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3))}

.step{display:flex;align-items:center;gap:6px;padding:7px;border-radius:22px;
  border:1px solid var(--line);background:var(--pane2)}
.step button{width:44px;height:44px;flex:0 0 auto;border-radius:16px;border:none;
  background:linear-gradient(135deg,var(--c1),var(--c3));color:#fff;
  font-size:21px;font-weight:800;line-height:1;cursor:pointer;transition:.16s;
  box-shadow:0 6px 14px -9px color-mix(in srgb,var(--c1) 65%,transparent)}
.step button:active{transform:scale(.9)}
.step button[disabled]{opacity:.35;pointer-events:none;box-shadow:none}
.step input{flex:1 1 auto;min-width:0;width:auto;border:none;background:transparent;
  color:var(--ink);outline:none;text-align:center;font-weight:900;font-size:18px}

.total{display:flex;justify-content:space-between;align-items:center;margin:16px 0;padding:15px 16px;
  border-radius:18px;border:1px solid var(--line);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 10%,transparent),color-mix(in srgb,var(--c3) 8%,transparent))}
.total span{font-size:12.5px;color:var(--dim)}
.total b{font-size:20px;font-weight:900;
  background:linear-gradient(90deg,var(--c1),var(--c3));-webkit-background-clip:text;background-clip:text;color:transparent}

.go{width:100%;padding:16px;border:0;border-radius:18px;cursor:pointer;
  font-family:inherit;font-size:15px;font-weight:900;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3));transition:.2s}
body.glow-on .go{box-shadow:0 14px 30px -16px color-mix(in srgb,var(--c1) 65%,transparent)}
.go:active{transform:scale(.985)}
.go[disabled]{cursor:default;color:var(--dim);background:var(--pane2);
  border:1px solid var(--line);box-shadow:none}
.go.alt{margin-top:9px;color:var(--ink);background:var(--pane2);
  border:1px solid var(--line);box-shadow:none;font-weight:700;font-size:13.5px}
.walbox{margin-top:10px;padding:11px 14px;border-radius:13px;font-size:11.5px;line-height:1.8;
  border:1px solid var(--line);background:var(--pane2);color:var(--dim)}
.walbox b{color:var(--c1)}
.ghost{width:100%;margin-top:9px;padding:14px;border-radius:16px;cursor:pointer;
  border:1px solid var(--line);background:transparent;color:var(--dim);font-family:inherit;font-size:13.5px;font-weight:700}

/* ═══ موفقیت ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:30px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 12%,transparent),transparent 72%)}
.win.on{display:grid}
.ring{position:relative;width:110px;height:110px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;font-size:50px;
  color:#fff;background:linear-gradient(135deg,var(--c1),var(--c3));animation:pop .55s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}to{transform:none;opacity:1}}
.ring:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--c1);
  animation:pulse 1.9s ease-out infinite}
@keyframes pulse{from{transform:scale(1);opacity:.75}to{transform:scale(1.9);opacity:0}}
@media (prefers-reduced-motion:reduce){ .ring,.ring:after{animation:none} }
.win h2{margin:0 0 9px;font-size:20px;font-weight:900;color:var(--ink)}
.win p{margin:0 0 24px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.win .code{font-family:ui-monospace,monospace;font-size:12px;padding:8px 14px;border-radius:11px;
  border:1px solid var(--line);background:var(--pane2);margin-bottom:20px;direction:ltr;color:var(--ink)}

/* ═══ پیام ═══ */
.toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:13px 18px;border-radius:15px;font-size:12.5px;font-weight:700;max-width:88vw;text-align:center;
  background:linear-gradient(135deg,var(--c4),#B1003A);color:#fff;
  transition:transform .34s cubic-bezier(.2,1.3,.4,1);box-shadow:0 14px 30px -14px color-mix(in srgb,var(--c4) 55%,transparent);line-height:1.7}
.toast.ok{background:linear-gradient(135deg,var(--c2),var(--c1));color:#fff;box-shadow:0 14px 30px -14px color-mix(in srgb,var(--c2) 55%,transparent)}
.toast.on{transform:translate(-50%,0)}
</style>
CSS;
}
