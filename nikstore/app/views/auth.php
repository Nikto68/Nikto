<?php $reg = $mode === 'register'; ?>
<section class="sec tight">
  <div class="wrap narrow">
    <form class="form-card g blur auth" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <span class="orb big <?= $reg ? 'o-grn' : 'o-tg' ?>"><?= ic($reg ? 'plus' : 'user') ?></span>
      <h1><?= $reg ? 'ساختِ حساب' : 'ورود به حساب' ?></h1>
      <p class="muted"><?= $reg ? 'با حساب، کیف پول و تاریخچه‌ی همه‌ی سفارش‌ها را دارید.' : 'با شماره موبایل و رمزتان وارد شوید.' ?></p>
      <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
      <?php if ($reg): ?><label class="field"><span>نام</span><input class="inp" type="text" name="name" value="<?= e($old['name']) ?>" maxlength="60" required autocomplete="name"></label><?php endif; ?>
      <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" value="<?= e($old['mobile']) ?>" placeholder="09123456789" required autocomplete="tel"></label>
      <label class="field"><span>رمز عبور</span><input class="inp ltr" type="password" name="pass" minlength="<?= $reg ? 6 : 1 ?>" required autocomplete="<?= $reg ? 'new-password' : 'current-password' ?>"></label>
      <?php if ($reg): ?><label class="field"><span>تکرارِ رمز</span><input class="inp ltr" type="password" name="pass2" minlength="6" required autocomplete="new-password"></label><?php endif; ?>
      <button class="btn b-pri b-lg b-block" type="submit"><?= ic($reg ? 'check' : 'lock') ?> <?= $reg ? 'ثبت‌نام' : 'ورود' ?></button>
      <p class="hint center"><?= $reg ? 'حساب دارید؟ <a href="' . e(url('login')) . '">ورود</a>' : 'حساب ندارید؟ <a href="' . e(url('register')) . '">ثبت‌نام</a>' ?></p>
    </form>
  </div>
</section>
