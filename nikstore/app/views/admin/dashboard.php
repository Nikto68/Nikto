<?php $max = max(1, max(array_column($chart, 1))); ?>
<?php if (setting('seed_review') === '1'): ?>
<div class="alert a-warn g"><?= ic('alert') ?><span>قیمت‌های محصولات نمونه‌اند. قبل از شروعِ فروش، از «محصولات» همه را بازبینی کنید و در «تنظیمات» شماره کارت یا درگاه را وارد کنید.</span>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="seed_ok"><button class="btn b-glass b-sm" type="submit">بازبینی کردم</button></form></div>
<?php endif; ?>
<?php if (!card_on() && !zp_on()): ?>
<div class="alert a-err g"><?= ic('alert') ?><span>هیچ روشِ پرداختی فعال نیست — مشتری نمی‌تواند پرداخت کند. <a href="<?= e(aurl('settings')) ?>">تنظیمات</a></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat g"><span class="orb sm o-grn"><?= ic('coin') ?></span><div><span>فروشِ امروز</span><b><?= toman($S['today']) ?></b><small><?= fa($S['orders_today']) ?> سفارش</small></div></div>
  <div class="stat g"><span class="orb sm o-tg"><?= ic('chart') ?></span><div><span>۷ روزِ اخیر</span><b><?= toman($S['week']) ?></b></div></div>
  <div class="stat g"><span class="orb sm o-vio"><?= ic('chart') ?></span><div><span>۳۰ روزِ اخیر</span><b><?= toman($S['month']) ?></b></div></div>
  <div class="stat g"><span class="orb sm o-pink"><?= ic('users') ?></span><div><span>کاربران</span><b><?= fa($S['users']) ?></b><small>موجودیِ کیف‌پول‌ها: <?= toman($S['wallets']) ?></small></div></div>
</div>

<div class="attn-grid">
  <a class="attn g <?= $S['review'] ? 'hot' : '' ?>" href="<?= e(aurl('orders', ['st' => 'review'])) ?>"><b><?= fa($S['review']) ?></b><span>رسید در انتظارِ تایید</span></a>
  <a class="attn g <?= $S['queue'] ? 'hot' : '' ?>" href="<?= e(aurl('orders', ['st' => 'paid'])) ?>"><b><?= fa($S['queue']) ?></b><span>پرداخت‌شده — منتظرِ انجام</span></a>
  <a class="attn g" href="<?= e(aurl('orders', ['st' => 'processing'])) ?>"><b><?= fa($S['proc']) ?></b><span>در حالِ انجام</span></a>
  <a class="attn g <?= $S['failed'] ? 'bad' : '' ?>" href="<?= e(aurl('orders', ['st' => 'failed'])) ?>"><b><?= fa($S['failed']) ?></b><span>ناموفق — نیازِ بررسی</span></a>
</div>

<div class="panel g">
  <h3><?= ic('chart') ?> فروشِ ۱۴ روزِ اخیر</h3>
  <div class="bars">
    <?php foreach ($chart as [$d0, $v]): ?>
    <div class="bar" title="<?= e(jdate($d0, false) . ' — ' . toman($v)) ?>"><i style="height:<?= max(2, round($v / $max * 100)) ?>%"></i><span><?= fa_digits(date('j', $d0)) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel g">
  <div class="panel-h"><h3><?= ic('list') ?> آخرین سفارش‌ها</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="expire"><button class="btn b-glass b-sm" type="submit">لغوِ پرداخت‌نشده‌های کهنه</button></form></div>
  <?php require NS_APP . '/views/admin/_orders_table.php'; ?>
</div>
