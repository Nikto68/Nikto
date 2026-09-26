<!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>ورود مدیر — <?= e(setting('site_name')) ?></title>
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body>
<?php require NS_APP . '/views/_sprite.php'; ?>
<div class="bg" aria-hidden="true"><div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><canvas id="sky"></canvas><div class="bg-noise"></div></div>
<main class="login-wrap">
  <form class="form-card g blur auth" method="post">
    <?= csrf_field() ?>
    <span class="orb big o-tg"><?= ic('lock') ?></span>
    <h1>پنلِ مدیریت</h1>
    <p class="muted"><?= e(setting('site_name')) ?></p>
    <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
    <label class="field"><span>رمزِ مدیر</span><input class="inp ltr" type="password" name="pass" required autofocus autocomplete="current-password"></label>
    <button class="btn b-pri b-lg b-block" type="submit"><?= ic('lock') ?> ورود</button>
  </form>
</main>
<script src="assets/app.js?v=<?= NS_VERSION ?>"></script>
</body>
</html>
