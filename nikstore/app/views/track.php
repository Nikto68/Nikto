<section class="sec tight">
  <div class="wrap narrow">
    <form class="form-card g blur auth" method="post">
      <?= csrf_field() ?>
      <span class="orb big o-tg"><?= ic('search') ?></span>
      <h1>پیگیریِ سفارش</h1>
      <p class="muted">کدِ سفارش و شماره موبایلی که موقعِ خرید وارد کردید را بنویسید.</p>
      <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
      <label class="field"><span>کدِ سفارش</span><input class="inp ltr" type="text" name="code" maxlength="20" placeholder="مثلا 7KQ2MX9HTA" required autocomplete="off"></label>
      <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" placeholder="09123456789" required></label>
      <button class="btn b-pri b-lg b-block" type="submit"><?= ic('search') ?> نمایشِ سفارش</button>
      <p class="hint center">با حساب خرید کرده‌اید؟ <a href="<?= e(url($me ? 'account' : 'login')) ?>">سفارش‌ها در حسابِ شما</a></p>
    </form>
  </div>
</section>
