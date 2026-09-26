<?php
$sup = trim((string)setting('support'), '@ ');
$chan = trim((string)setting('channel'), '@ ');
$text = $k === 'terms' ? setting('terms') : ($k === 'about' ? setting('about') : setting('contact_text'));
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="./">خانه</a><?= ic('arrow') ?><span><?= e($title) ?></span></nav>
    <div class="ph-in"><span class="orb big o-tg"><?= ic($k === 'terms' ? 'shield' : ($k === 'about' ? 'star' : 'headset')) ?></span><div><h1><?= e($title) ?></h1></div></div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap narrow">
    <div class="panel g prose">
      <?php if (trim((string)$text) !== ''): ?><p><?= nl2br(e($text)) ?></p><?php endif; ?>
      <?php if ($k === 'contact'): ?>
      <div class="contact-list">
        <?php if ($sup !== ''): ?><a class="info g" href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener"><?= ic('headset') ?><div><b>پشتیبانی در تلگرام</b><span dir="ltr">@<?= e($sup) ?></span></div></a><?php endif; ?>
        <?php if ($chan !== ''): ?><a class="info g" href="https://t.me/<?= e(rawurlencode($chan)) ?>" target="_blank" rel="noopener"><?= ic('send') ?><div><b>کانالِ اطلاع‌رسانی</b><span dir="ltr">@<?= e($chan) ?></span></div></a><?php endif; ?>
        <a class="info g" href="<?= e(url('track')) ?>"><?= ic('search') ?><div><b>پیگیریِ سفارش</b><span>با کدِ سفارش و شماره موبایل</span></div></a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
