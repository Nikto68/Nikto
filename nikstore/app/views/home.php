<?php
$S = $K['stars'] ?? null; $PR = $K['premium'] ?? null; $G = $K['gifts'] ?? null;
$N = $K['numbers'] ?? null; $IG = $K['instagram'] ?? null; $TG = $K['telegram'] ?? null;
$starCustom = null; $starPk = [];
foreach ($S['items'] ?? [] as $p) { if ($p['pricing'] === 'unit') $starCustom = $starCustom ?: $p; else $starPk[] = $p; }
$hero500 = null; foreach ($starPk as $p) if (str_contains($p['title'], '۵۰۰')) $hero500 = $p;
$hero500 = $hero500 ?: ($starPk[3] ?? $starPk[0] ?? null);
$heroPrem = !empty($PR['items']) ? $PR['items'][count($PR['items']) - 1] : null;
$heroGift = $G['items'][0] ?? null;
$heroNum = $N['items'][0] ?? null;
$words = array_values(array_filter(['استارز', $PR ? 'پریمیوم' : null, $G ? 'گیفت' : null, $N ? 'شماره مجازی' : null, $IG ? 'فالوور' : null]));
$ticker = [];
if ($starCustom) $ticker[] = ['star', 'هر ۱ استارز', toman($starCustom['price'] / max(1, (int)$starCustom['per']))];
foreach ($PR['items'] ?? [] as $p) $ticker[] = ['crown', $p['title'], toman($p['price'])];
if ($N && $N['from']) $ticker[] = ['phone', 'شماره مجازی از', toman($N['from'])];
if ($G && $G['from']) $ticker[] = ['gift', 'گیفت تلگرام از', toman($G['from'])];
foreach (array_slice($IG['items'] ?? [], 0, 3) as $p) $ticker[] = ['insta', $p['title'], product_price_label($p)];
?>
<section class="hero">
  <div class="wrap hero-in">
    <div>
      <span class="pill g"><span class="dot"></span>پرداختِ امن · تحویلِ سریع · پشتیبانیِ واقعی</span>
      <h1>خرید <span class="shine rot" data-words="<?= e(json_encode($words, JSON_UNESCAPED_UNICODE)) ?>"><?= e($words[0]) ?></span><br>در چند ثانیه</h1>
      <p class="hero-sub">استارز، پریمیوم و گیفت تلگرام، شماره مجازیِ کشورهای مختلف و خدماتِ اینستاگرام — با قیمتِ شفاف، پرداختِ آنلاین یا کارت‌به‌کارت و پیگیریِ لحظه‌ای. بدون رمز، فقط با آیدی یا لینک.</p>
      <div class="hero-cta">
        <a class="btn b-pri b-lg" href="#services"><?= ic('bag') ?> شروع خرید</a>
        <a class="btn b-glass b-lg" href="<?= e(url('track')) ?>"><?= ic('search') ?> پیگیری سفارش</a>
      </div>
      <div class="trust">
        <span class="g"><?= ic('check') ?>بدون نیاز به رمز</span>
        <span class="g"><?= ic('check') ?>درگاهِ امن و کارت‌به‌کارت</span>
        <span class="g"><?= ic('check') ?>بازگشتِ وجه در صورتِ خطا</span>
      </div>
    </div>
    <div class="bento" aria-hidden="true">
      <a class="bt bt-star g blur" href="<?= $hero500 ? e(url('buy', ['id' => $hero500['id']])) : '#stars' ?>">
        <i class="ring"></i>
        <div class="big-ic"><?= ic('star') ?></div>
        <div class="bt-k">استارز تلگرام</div>
        <div class="bt-t"><?= e($hero500['title'] ?? 'استارز') ?></div>
        <div class="bt-k">واریزِ مستقیم به آیدیِ شما</div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($hero500['price'] ?? 0) ?></span></div>
        <span class="btn b-gold b-block"><?= ic('bolt') ?> خرید فوری</span>
      </a>
      <a class="bt bt-f1 g blur" href="<?= e(url('cat', ['slug' => 'premium'])) ?>">
        <div class="bt-row"><span class="orb o-vio"><?= ic('crown') ?></span><div><div class="bt-k">تلگرام پریمیوم</div><div class="bt-t"><?= e($heroPrem['title'] ?? 'اشتراک پریمیوم') ?></div></div></div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($heroPrem['price'] ?? 0) ?></span></div>
      </a>
      <a class="bt g blur" href="<?= e(url('cat', ['slug' => 'gifts'])) ?>">
        <div class="bt-row"><span class="emo"><?= e($heroGift['emoji'] ?? '🎁') ?></span><div><div class="bt-k">گیفت تلگرام</div><div class="bt-t"><?= e($heroGift['title'] ?? 'گیفت') ?></div></div></div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($heroGift['price'] ?? 0) ?></span></div>
      </a>
      <a class="bt g blur" href="<?= e(url('cat', ['slug' => 'numbers'])) ?>">
        <div class="bt-row"><span class="emo"><?= e($heroNum['emoji'] ?? '🌍') ?></span><div><div class="bt-k">شماره مجازی</div><div class="bt-t"><?= e($heroNum['title'] ?? 'کشورهای مختلف') ?></div></div></div>
        <div class="bt-p"><span class="muted">از</span><span><?= ui_amt($N['from'] ?? 0) ?></span></div>
      </a>
      <a class="bt bt-f2 g blur" href="<?= e(url('cat', ['slug' => 'instagram'])) ?>">
        <div class="bt-row"><span class="orb o-ig"><?= ic('insta') ?></span><div><div class="bt-k">اینستاگرام</div><div class="bt-t">فالوور · لایک · بازدید</div></div></div>
        <div class="bt-p"><span class="muted">از</span><span><?= ui_amt($IG['from'] ?? 0) ?></span></div>
      </a>
    </div>
  </div>
  <?php if ($ticker): ?>
  <div class="ticker g"><div class="ticker-track">
    <?php for ($r = 0; $r < 2; $r++): foreach ($ticker as [$i, $l, $v]): ?>
    <div class="tk"<?= $r ? ' aria-hidden="true"' : '' ?>><?= ic($i) ?><span class="muted"><?= e($l) ?></span><b><?= e($v) ?></b><span class="live"></span></div>
    <?php endforeach; endfor; ?>
  </div></div>
  <?php endif; ?>
</section>

<section class="sec" id="services">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('bag') ?> همه‌ی خدمات</span>
      <h2>هرچه برای <span class="gt">تلگرام و اینستاگرام</span> لازم دارید</h2>
      <p>خدمت را انتخاب کنید، مشخصات را وارد کنید، پرداخت کنید — سفارش در صفِ انجام قرار می‌گیرد و وضعیتش را لحظه‌ای می‌بینید.</p>
    </div>
    <div class="cat-grid">
      <?php foreach ($cats as $c): $kk = $K[$c['kind']] ?? null; ?>
      <a class="catc g lift rv" href="<?= e(url('cat', ['slug' => $c['slug']])) ?>">
        <span class="halo <?= ui_orb($c['kind']) ?>"></span>
        <span class="orb <?= ui_orb($c['kind']) ?>"><?= ic($c['icon'] ?: 'bag') ?></span>
        <h3><?= e($c['title']) ?></h3>
        <p><?= e($c['subtitle']) ?></p>
        <div class="catc-foot"><span><?php if (!empty($kk['from'])): ?><span class="muted">از</span> <?= ui_amt($kk['from']) ?><?php endif; ?></span><span class="go">مشاهده <?= ic('arrow') ?></span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($S): ?>
<section class="sec" id="stars">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker gold g"><?= ic('star') ?> <?= e($S['title']) ?></span>
      <h2>خرید <span class="gt-gold">استارز تلگرام</span></h2>
      <p><?= e($S['subtitle']) ?>. فقط آیدیِ گیرنده لازم است.</p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $S['slug']])) ?>">همه‌ی بسته‌ها <?= ic('arrow') ?></a>
    </div>
    <div class="stars-lay">
      <?= ui_star_calc($starCustom) ?>
      <div class="pk-grid"><?php foreach (array_slice($starPk, 0, 6) as $p) echo ui_card($p, 'stars'); ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($PR && $PR['items']): ?>
<section class="sec" id="premium">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker vio g"><?= ic('crown') ?> <?= e($PR['title']) ?></span>
      <h2>اشتراکِ <span class="gt">تلگرام پریمیوم</span></h2>
      <p><?= e($PR['subtitle']) ?></p>
    </div>
    <div class="plans"><?php foreach ($PR['items'] as $p) echo ui_card($p, 'premium'); ?></div>
    <div class="feats">
      <div class="feat g rv"><?= ic('upload') ?><b>آپلود تا ۴ گیگابایت</b><span>فایل‌های حجیم را بدونِ تقسیم بفرستید.</span></div>
      <div class="feat g rv"><?= ic('bolt') ?><b>دانلود با حداکثر سرعت</b><span>بدونِ محدودیتِ سرعت.</span></div>
      <div class="feat g rv"><?= ic('chat') ?><b>تبدیل ویس به متن</b><span>پیامِ صوتی را بخوانید.</span></div>
      <div class="feat g rv"><?= ic('star') ?><b>نشان و ایموجیِ ویژه</b><span>نشانِ پریمیوم و استیکرهای اختصاصی.</span></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($G && $G['items']): ?>
<section class="sec" id="gifts">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker pink g"><?= ic('gift') ?> <?= e($G['title']) ?></span>
      <h2>گیفت برای <span class="gt">هر مناسبتی</span></h2>
      <p><?= e($G['subtitle']) ?></p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $G['slug']])) ?>">همه‌ی گیفت‌ها <?= ic('arrow') ?></a>
    </div>
    <div class="gift-grid"><?php foreach (array_slice($G['items'], 0, 6) as $p) echo ui_card($p, 'gifts'); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($N && $N['items']): ?>
<section class="sec" id="numbers">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker grn g"><?= ic('phone') ?> <?= e($N['title']) ?></span>
      <h2>شماره مجازیِ <span class="gt">کشورهای مختلف</span></h2>
      <p><?= e($N['subtitle']) ?>. شماره و کدِ تایید در صفحه‌ی سفارش نمایش داده می‌شود.</p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $N['slug']])) ?>">همه‌ی کشورها <?= ic('arrow') ?></a>
    </div>
    <div class="ctry-grid"><?php foreach (array_slice($N['items'], 0, 8) as $p) echo ui_card($p, 'numbers'); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($IG && $IG['items']): ?>
<section class="sec" id="instagram">
  <div class="wrap">
    <div class="ig-band g">
      <span class="halo h1"></span><span class="halo h2"></span>
      <div class="sec-head rv">
        <div><span class="ig-logo"><?= ic('insta') ?></span>
        <h2>افزایشِ <span class="gt-ig">فالوور، لایک و بازدید</span></h2>
        <p><?= e($IG['subtitle']) ?>. پیج باید عمومی (Public) باشد.</p></div>
        <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $IG['slug']])) ?>">همه‌ی خدمات <?= ic('arrow') ?></a>
      </div>
      <div class="svc-grid"><?php foreach (array_slice($IG['items'], 0, 4) as $p) echo ui_card($p, 'instagram'); ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($TG && $TG['items']): ?>
<section class="sec" id="telegram">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker g"><?= ic('megaphone') ?> <?= e($TG['title']) ?></span>
      <h2>ممبر، بازدید و <span class="gt">ری‌اکشنِ کانال</span></h2>
      <p><?= e($TG['subtitle']) ?></p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $TG['slug']])) ?>">همه‌ی خدمات <?= ic('arrow') ?></a>
    </div>
    <div class="svc-grid"><?php foreach (array_slice($TG['items'], 0, 4) as $p) echo ui_card($p, 'telegram'); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="sec" id="how">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('bolt') ?> مراحلِ خرید</span>
      <h2>خرید در <span class="gt">چهار قدم</span></h2>
    </div>
    <div class="steps">
      <div class="step rv"><div class="step-n g blur"><?= ic('bag') ?><i>۱</i></div><b>انتخابِ خدمت</b><span>بسته یا سرویسِ موردنظر را انتخاب کنید.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('edit') ?><i>۲</i></div><b>ثبتِ مشخصات</b><span>آیدی، لینک یا تعداد را وارد کنید.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('card') ?><i>۳</i></div><b>پرداختِ امن</b><span>درگاهِ آنلاین، کارت‌به‌کارت یا کیف پول.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('check') ?><i>۴</i></div><b>تحویل و پیگیری</b><span>وضعیتِ سفارش را لحظه‌ای ببینید.</span></div>
    </div>
  </div>
</section>

<section class="sec" id="faq">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('help') ?> سوالاتِ متداول</span>
      <h2>جوابِ سوال‌های <span class="gt">پرتکرار</span></h2>
    </div>
    <div class="faq">
      <?php foreach ([
        ['برای خرید باید ثبت‌نام کنم؟', 'نه. بدونِ ثبت‌نام هم می‌توانید بخرید؛ فقط شماره موبایل برای پیگیری لازم است. با ثبت‌نام، کیف پول و تاریخچه‌ی سفارش‌ها را هم دارید.'],
        ['رمز یا کدِ ورودِ حسابم را می‌خواهید؟', 'هرگز. برای تلگرام فقط آیدی و برای اینستاگرام فقط لینکِ پیج یا پست لازم است. این اطلاعات را به هیچ‌کس ندهید.'],
        ['پرداخت چطور است؟', 'درگاهِ آنلاین، کارت‌به‌کارت با آپلودِ رسید، یا کیف پولِ حساب. بعد از تاییدِ پرداخت، سفارش در صفِ انجام قرار می‌گیرد.'],
        ['سفارشم را چطور پیگیری کنم؟', 'بعد از ثبت، به صفحه‌ی سفارش می‌روید و وضعیت لحظه‌ای به‌روز می‌شود. بعدا هم با کدِ سفارش و شماره موبایل از «پیگیری سفارش» پیدایش می‌کنید.'],
        ['شماره مجازی چطور تحویل می‌شود؟', 'بعد از پرداخت، شماره در صفحه‌ی سفارش نمایش داده می‌شود؛ آن را در تلگرام وارد کنید تا کدِ تایید همان‌جا ظاهر شود.'],
        ['اگر سفارش انجام نشود؟', 'مبلغ به کیف پولِ حسابتان برمی‌گردد، یا اگر بدونِ حساب خریده‌اید، پشتیبانی با شما هماهنگ و وجه را برمی‌گرداند.'],
      ] as $i => [$q, $a]): ?>
      <details class="g rv"<?= $i === 0 ? ' open' : '' ?>><summary><span class="q"><?= ic('help') ?></span><?= e($q) ?><?= ic('chev', 'chev') ?></summary><p><?= e($a) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="wrap rv">
  <div class="cta">
    <div><h2>همین حالا اولین سفارشتان را ثبت کنید</h2><p>پرداختِ امن، تحویلِ سریع و پیگیریِ لحظه‌ای — بدونِ رمز و دردسر.</p></div>
    <div class="cta-acts">
      <a class="btn b-white b-lg" href="#services"><?= ic('bag') ?> شروع خرید</a>
      <a class="btn b-line b-lg" href="<?= e(url($me ? 'account' : 'register')) ?>"><?= $me ? 'حساب من' : 'ساختِ حساب' ?></a>
    </div>
  </div>
</section>
