<!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — مدیریت <?= e(setting('site_name')) ?></title>
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body class="adm">
<?php require NS_APP . '/views/_sprite.php'; ?>
<div class="bg" aria-hidden="true"><div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><canvas id="sky"></canvas><div class="bg-noise"></div></div>
<div class="adm-lay">
  <aside class="side g blur" id="side">
    <a href="<?= e(aurl()) ?>" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e(setting('site_name')) ?><small>پنلِ مدیریت</small></span></a>
    <nav>
      <?php foreach ([['dashboard', 'home', 'داشبورد'], ['orders', 'list', 'سفارش‌ها'], ['products', 'box', 'محصولات'], ['cats', 'grid', 'دسته‌ها'], ['users', 'users', 'کاربران'], ['settings', 'gear', 'تنظیمات']] as [$k, $i, $l]): ?>
      <a href="<?= e(aurl($k, $k === 'orders' && $attn ? ['st' => 'attn'] : [])) ?>" class="<?= $A === $k || ($k === 'orders' && $A === 'order') || ($k === 'products' && $A === 'product') || ($k === 'users' && $A === 'user') ? 'on' : '' ?>"><?= ic($i) ?><?= e($l) ?><?php if ($k === 'orders' && $attn): ?><span class="cnt"><?= fa($attn) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="./" target="_blank" rel="noopener"><?= ic('globe') ?>مشاهده‌ی سایت</a>
      <button type="button" id="theme" class="side-btn"><?= ic('sun', 'i-sun') ?><?= ic('moon', 'i-moon') ?>تغییرِ تم</button>
      <form method="post" action="<?= e(aurl('logout')) ?>"><?= csrf_field() ?><button type="submit" class="side-btn"><?= ic('logout') ?>خروج</button></form>
    </div>
  </aside>
  <div class="adm-main">
    <div class="adm-top">
      <button type="button" class="icon-btn menu-btn" id="sideBtn" aria-label="منو"><?= ic('menu') ?></button>
      <h1><?= e($title) ?></h1>
    </div>
    <?php foreach (flashes() as [$t, $m]): ?><div class="alert <?= $t === 'ok' ? 'a-ok' : 'a-err' ?> g"><?= ic($t === 'ok' ? 'check' : 'alert') ?><span><?= e($m) ?></span></div><?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
<script src="assets/app.js?v=<?= NS_VERSION ?>"></script>
</body>
</html>
