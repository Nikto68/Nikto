<?php
$kind = $c['kind'];
$custom = null; $list = [];
foreach ($items as $p) { if ($kind === 'stars' && $p['pricing'] === 'unit') $custom = $custom ?: $p; else $list[] = $p; }
$grid = ['stars' => 'pk-grid', 'premium' => 'plans', 'gifts' => 'gift-grid', 'numbers' => 'ctry-grid'][$kind] ?? 'svc-grid';
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="./">خانه</a><?= ic('arrow') ?><span><?= e($c['title']) ?></span></nav>
    <div class="ph-in">
      <span class="orb big <?= ui_orb($kind) ?>"><?= ic($c['icon'] ?: 'bag') ?></span>
      <div><h1><?= e($c['title']) ?></h1><p class="muted"><?= e($c['subtitle']) ?></p></div>
    </div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap">
    <?php if (!$items): ?>
      <div class="empty g">فعلا خدمتی در این بخش نیست.</div>
    <?php else: ?>
      <?php if ($kind === 'numbers'): ?>
      <label class="search g"><?= ic('search') ?><input type="search" data-filter="#list" placeholder="جستجوی کشور… مثلا آمریکا" aria-label="جستجو"></label>
      <?php endif; ?>
      <?php if ($custom): ?><div class="stars-lay"><?= ui_star_calc($custom) ?><div class="<?= $grid ?>" id="list"><?php foreach ($list as $p) echo ui_card($p, $kind); ?></div></div>
      <?php else: ?><div class="<?= $grid ?>" id="list"><?php foreach ($list as $p) echo ui_card($p, $kind); ?></div><?php endif; ?>
      <div class="empty g" id="noResult" hidden>نتیجه‌ای پیدا نشد.</div>
    <?php endif; ?>
    <div class="info-row">
      <div class="info g"><?= ic('lock') ?><div><b>بدونِ رمز</b><span>فقط آیدی یا لینک؛ هیچ اطلاعاتِ ورودی لازم نیست.</span></div></div>
      <div class="info g"><?= ic('shield') ?><div><b>پرداختِ امن</b><span>درگاهِ آنلاین، کارت‌به‌کارت یا کیف پول.</span></div></div>
      <div class="info g"><?= ic('refresh') ?><div><b>پیگیریِ لحظه‌ای</b><span>وضعیتِ سفارش در صفحه‌ی سفارش به‌روز می‌شود.</span></div></div>
    </div>
  </div>
</section>
