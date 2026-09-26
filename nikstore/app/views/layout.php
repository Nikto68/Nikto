<?php
$site = setting('site_name');
$cats = $cats ?? categories();
$sup = trim((string)setting('support'), '@ ');
$chan = trim((string)setting('channel'), '@ ');
?><!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' ? $title . ' — ' . $site : $site . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($site . ' — خرید استارز، پریمیوم و گیفت تلگرام، شماره مجازی و خدمات اینستاگرام با پرداخت امن و تحویل سریع.') ?>">
<meta name="theme-color" content="#05060F">
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body>
<?php require NS_APP . '/views/_sprite.php'; ?>
<div class="bg" aria-hidden="true">
  <div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><div class="aur a4"></div><div class="aur a5"></div>
  <canvas id="sky"></canvas><div class="bg-grid"></div><div class="bg-spot" id="spot"></div><div class="bg-noise"></div>
</div>

<?php if (trim((string)setting('notice')) !== ''): ?>
<div class="notice"><?= ic('bolt') ?><?= e(setting('notice')) ?></div>
<?php endif; ?>

<header class="hdr">
  <div class="hdr-in g blur">
    <a href="./" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e($site) ?><small><?= e(setting('tagline')) ?></small></span></a>
    <nav class="nav">
      <?php foreach ($cats as $c): ?><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= e(preg_replace('/^(خدمات |تلگرام )/u', '', $c['title'])) ?></a><?php endforeach; ?>
      <a href="<?= e(url('track')) ?>">پیگیری سفارش</a>
    </nav>
    <div class="hdr-act">
      <button type="button" class="icon-btn" id="theme" aria-label="تغییر تم"><?= ic('sun', 'i-sun') ?><?= ic('moon', 'i-moon') ?></button>
      <?php if ($me): ?>
        <a class="acct" href="<?= e(url('account')) ?>"><span class="t"><b><?= e($me['name']) ?></b><span><?= toman($me['balance']) ?></span></span><span class="av"><?= e(mb_substr($me['name'], 0, 1)) ?></span></a>
      <?php else: ?>
        <a class="btn b-pri hide-sm" href="<?= e(url('login')) ?>"><?= ic('user') ?> ورود / ثبت‌نام</a>
        <a class="icon-btn show-sm" href="<?= e(url('login')) ?>" aria-label="ورود"><?= ic('user') ?></a>
      <?php endif; ?>
      <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="منو" aria-expanded="false"><?= ic('menu') ?></button>
    </div>
  </div>
</header>
<nav class="drawer g blur" id="drawer">
  <?php foreach ($cats as $c): ?><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= ic($c['icon'] ?: 'bag') ?><?= e($c['title']) ?></a><?php endforeach; ?>
  <a href="<?= e(url('track')) ?>"><?= ic('search') ?>پیگیری سفارش</a>
  <a href="<?= e(url($me ? 'account' : 'login')) ?>"><?= ic('user') ?><?= $me ? 'حساب من' : 'ورود / ثبت‌نام' ?></a>
</nav>

<?php $fl = flashes(); if ($fl): ?>
<div class="wrap flashes">
  <?php foreach ($fl as [$t, $m]): ?><div class="alert <?= $t === 'ok' ? 'a-ok' : 'a-err' ?> g"><?= ic($t === 'ok' ? 'check' : 'alert') ?><span><?= e($m) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main"><?= $content ?></main>

<footer class="ftr g">
  <div class="wrap">
    <div class="ftr-grid">
      <div>
        <a href="./" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e($site) ?></span></a>
        <p><?= e(setting('about')) ?></p>
      </div>
      <div>
        <h4>خدمات</h4>
        <ul><?php foreach ($cats as $c): ?><li><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= e($c['title']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <div>
        <h4>راهنما</h4>
        <ul>
          <li><a href="<?= e(url('track')) ?>">پیگیری سفارش</a></li>
          <li><a href="<?= e(url('page', ['k' => 'terms'])) ?>">قوانین و مقررات</a></li>
          <li><a href="<?= e(url('page', ['k' => 'about'])) ?>">درباره ما</a></li>
          <li><a href="<?= e(url('page', ['k' => 'contact'])) ?>">تماس با ما</a></li>
        </ul>
      </div>
      <div>
        <h4>ارتباط با ما</h4>
        <ul>
          <?php if ($sup !== ''): ?><li><a href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener"><?= ic('headset') ?> پشتیبانی: <span dir="ltr">@<?= e($sup) ?></span></a></li><?php endif; ?>
          <?php if ($chan !== ''): ?><li><a href="https://t.me/<?= e(rawurlencode($chan)) ?>" target="_blank" rel="noopener"><?= ic('send') ?> کانال: <span dir="ltr">@<?= e($chan) ?></span></a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="ftr-bot">
      <span>© <?= fa_digits(ns_g2j((int)date('Y'), (int)date('n'), (int)date('j'))[0]) ?> <?= e($site) ?> — همه‌ی حقوق محفوظ است.</span>
      <span>پرداختِ امن · تحویلِ سریع · پشتیبانیِ واقعی</span>
    </div>
  </div>
</footer>

<div class="fab">
  <button type="button" class="top g blur" id="toTop" aria-label="بالا"><?= ic('up') ?></button>
  <?php if ($sup !== ''): ?><a class="sup" href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener" aria-label="پشتیبانی"><?= ic('headset') ?></a><?php endif; ?>
</div>
<script src="assets/app.js?v=<?= NS_VERSION ?>"></script>
</body>
</html>
