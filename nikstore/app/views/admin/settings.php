<?php $s = fn($k) => e(setting($k)); $c = fn($k) => setting($k) === '1' ? ' checked' : ''; ?>
<form method="post" class="set-form">
  <?= csrf_field() ?>
  <?php if ($err !== ''): ?><div class="alert a-err g"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('star') ?> فروشگاه</h3>
    <label class="field"><span>نامِ فروشگاه</span><input class="inp" name="site_name" value="<?= $s('site_name') ?>"></label>
    <label class="field"><span>شعار</span><input class="inp" name="tagline" value="<?= $s('tagline') ?>"></label>
    <label class="field span2"><span>نوارِ اطلاعیه‌ی بالای سایت (خالی = پنهان)</span><input class="inp" name="notice" value="<?= $s('notice') ?>"></label>
    <label class="field"><span>آیدیِ پشتیبانیِ تلگرام</span><input class="inp ltr" name="support" value="<?= $s('support') ?>" placeholder="username"></label>
    <label class="field"><span>آیدیِ کانالِ تلگرام</span><input class="inp ltr" name="channel" value="<?= $s('channel') ?>" placeholder="channel"></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('card') ?> پرداخت</h3>
    <label class="field chk span2"><input type="checkbox" name="card_on" value="1"<?= $c('card_on') ?>><span>کارت‌به‌کارت با آپلودِ رسید</span></label>
    <label class="field"><span>شماره کارت</span><input class="inp ltr" name="card_number" value="<?= $s('card_number') ?>" placeholder="6037 9900 0000 0000"></label>
    <label class="field"><span>به نامِ</span><input class="inp" name="card_holder" value="<?= $s('card_holder') ?>"></label>
    <label class="field"><span>بانک</span><input class="inp" name="card_bank" value="<?= $s('card_bank') ?>"></label>
    <span></span>
    <label class="field chk span2"><input type="checkbox" name="zp_on" value="1"<?= $c('zp_on') ?>><span>درگاهِ زرین‌پال</span></label>
    <label class="field"><span>مرچنت‌کدِ زرین‌پال</span><input class="inp ltr" name="zp_merchant" value="<?= $s('zp_merchant') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
    <label class="field chk"><input type="checkbox" name="zp_sandbox" value="1"<?= $c('zp_sandbox') ?>><span>حالتِ آزمایشی (sandbox)</span></label>
    <label class="field chk"><input type="checkbox" name="wallet_on" value="1"<?= $c('wallet_on') ?>><span>کیف پول برای کاربرانِ عضو</span></label>
    <label class="field"><span>حداقلِ شارژ (تومان)</span><input class="inp num" name="topup_min" value="<?= $s('topup_min') ?>"></label>
    <label class="field"><span>لغوِ سفارشِ پرداخت‌نشده بعد از (ساعت)</span><input class="inp num" name="pending_hours" value="<?= $s('pending_hours') ?>"></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('bolt') ?> تحویلِ خودکار</h3>
    <label class="field"><span>آدرسِ API پنلِ SMM</span><input class="inp ltr" name="smm_url" value="<?= $s('smm_url') ?>" placeholder="https://panel.example/api/v2"></label>
    <label class="field"><span>کلیدِ API پنلِ SMM</span><input class="inp ltr" name="smm_key" value="<?= $s('smm_key') ?>"></label>
    <label class="field span2"><span>توکنِ API سایتِ 5sim (شماره مجازی)</span><input class="inp ltr" name="fivesim_key" value="<?= $s('fivesim_key') ?>"></label>
    <div class="field span2"><span>آدرسِ کران (هر ۵ دقیقه)</span><div class="copy"><span dir="ltr" class="brk"><?= e($cron) ?></span><button type="button" class="btn b-glass b-sm" data-copy="<?= e($cron) ?>"><?= ic('copy') ?></button></div></div>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('list') ?> متن‌ها</h3>
    <label class="field span2"><span>درباره ما</span><textarea class="inp" name="about" rows="3"><?= $s('about') ?></textarea></label>
    <label class="field span2"><span>قوانین</span><textarea class="inp" name="terms" rows="6"><?= $s('terms') ?></textarea></label>
    <label class="field span2"><span>متنِ صفحه‌ی تماس</span><textarea class="inp" name="contact_text" rows="3"><?= $s('contact_text') ?></textarea></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('lock') ?> امنیت</h3>
    <label class="field"><span>رمزِ تازه‌ی مدیر (خالی = بدونِ تغییر)</span><input class="inp ltr" type="password" name="new_pass" minlength="8" autocomplete="new-password"></label>
    <label class="field chk"><input type="checkbox" name="trust_proxy" value="1"<?= $c('trust_proxy') ?>><span>سایت پشتِ کلادفلر/پروکسی است</span></label>
  </div>

  <div class="sticky-save"><button class="btn b-pri b-lg" type="submit"><?= ic('check') ?> ذخیره‌ی تنظیمات</button></div>
</form>
