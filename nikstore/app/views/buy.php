<?php
$f = field_meta($p['field']);
$unit = $p['pricing'] === 'unit';
$q0 = $unit ? max((int)$p['qmin'], (int)ns_digits($old['qty'])) : 1;
$pmIcons = ['wallet' => 'wallet', 'zarinpal' => 'card', 'card' => 'coin'];
$pmSub = ['wallet' => $me ? 'موجودی: ' . toman($me['balance']) : '', 'zarinpal' => 'پرداختِ آنلاین با همه‌ی کارت‌های عضوِ شتاب', 'card' => 'واریز و آپلودِ تصویرِ رسید'];
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="./">خانه</a><?= ic('arrow') ?><a href="<?= e(url('cat', ['slug' => $p['cat_slug']])) ?>"><?= e($p['cat_title']) ?></a><?= ic('arrow') ?><span><?= e($p['title']) ?></span></nav>
  </div>
</section>
<section class="sec tight">
  <div class="wrap buy-lay">
    <aside class="sum g blur rv">
      <div class="sum-top"><span class="emo xl"><?= e($p['emoji'] ?: '✨') ?></span><div><span class="muted sm"><?= e($p['cat_title']) ?></span><h1><?= e($p['title']) ?></h1></div></div>
      <?php if ($p['descr'] !== ''): ?><p class="muted"><?= e($p['descr']) ?></p><?php endif; ?>
      <div class="kv">
        <div><span>قیمت</span><b><?= e(product_price_label($p)) ?></b></div>
        <?php if ($unit): ?><div><span>حداقل / حداکثر</span><b><?= fa($p['qmin']) ?> / <?= fa($p['qmax']) ?></b></div><?php endif; ?>
        <div><span>تحویل</span><b><?= $p['cat_kind'] === 'numbers' ? 'نمایشِ شماره و کد در صفحه‌ی سفارش' : 'پس از تاییدِ پرداخت' ?></b></div>
      </div>
      <ul class="ticks">
        <li><?= ic('check') ?> بدونِ نیاز به رمز یا کدِ ورود</li>
        <li><?= ic('check') ?> پیگیریِ لحظه‌ای با کدِ سفارش</li>
        <li><?= ic('check') ?> بازگشتِ وجه در صورتِ عدمِ انجام</li>
      </ul>
    </aside>

    <form class="form-card g blur rv" method="post" data-buy data-price="<?= (int)$p['price'] ?>" data-per="<?= max(1, (int)$p['per']) ?>" data-unit="<?= $unit ? 1 : 0 ?>">
      <?= csrf_field() ?>
      <h2>ثبتِ سفارش</h2>
      <?php foreach ($errors as $er): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($er) ?></span></div><?php endforeach; ?>

      <?php if ($unit): ?>
      <label class="field"><span>تعداد (<?= e($p['unit'] ?: 'عدد') ?>)</span>
        <div class="qrow">
          <button type="button" data-step="<?= max(1, (int)$p['qstep']) ?>" aria-label="بیشتر">+</button>
          <input class="inp num" type="number" name="qty" inputmode="numeric" min="<?= (int)$p['qmin'] ?>" max="<?= (int)$p['qmax'] ?>" step="<?= max(1, (int)$p['qstep']) ?>" value="<?= (int)$q0 ?>" required>
          <button type="button" data-step="-<?= max(1, (int)$p['qstep']) ?>" aria-label="کمتر">−</button>
        </div>
        <small class="hint">حداقل <?= fa($p['qmin']) ?> — حداکثر <?= fa($p['qmax']) ?><?= (int)$p['qstep'] > 1 ? ' — مضربِ ' . fa($p['qstep']) : '' ?></small>
      </label>
      <?php endif; ?>

      <?php if ($f): ?>
      <label class="field"><span><?= e($f['label']) ?></span>
        <?php if ($p['field'] === 'text'): ?>
        <textarea class="inp" name="target" maxlength="500" placeholder="<?= e($f['ph']) ?>" required><?= e($old['target']) ?></textarea>
        <?php else: ?>
        <input class="inp ltr" type="text" name="target" value="<?= e($old['target']) ?>" placeholder="<?= e($f['ph']) ?>" autocomplete="off" spellcheck="false" required>
        <?php endif; ?>
        <?php if ($f['hint'] !== ''): ?><small class="hint"><?= e($f['hint']) ?></small><?php endif; ?>
      </label>
      <?php endif; ?>

      <label class="field"><span>توضیحات <em class="muted">(اختیاری)</em></span>
        <textarea class="inp sm" name="note" maxlength="500" placeholder="<?= $p['cat_kind'] === 'instagram' && str_contains($p['title'], 'کامنت') ? 'متنِ کامنت‌ها را اینجا بنویسید، هر خط یک کامنت' : 'اگر نکته‌ای هست بنویسید' ?>"><?= e($old['note']) ?></textarea>
      </label>

      <?php if (!$me): ?>
      <div class="two">
        <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" value="<?= e($old['mobile']) ?>" placeholder="09123456789" inputmode="tel" required></label>
        <label class="field"><span>نام <em class="muted">(اختیاری)</em></span><input class="inp" type="text" name="name" value="<?= e($old['name']) ?>" maxlength="60"></label>
      </div>
      <p class="hint">برای پیگیریِ سفارش لازم است. <a href="<?= e(url('login')) ?>">ورود</a> یا <a href="<?= e(url('register')) ?>">ثبت‌نام</a> برای کیف پول و تاریخچه‌ی سفارش‌ها.</p>
      <?php endif; ?>

      <div class="field"><span>روشِ پرداخت</span>
        <?php if (!$methods): ?>
          <div class="alert a-err"><?= ic('alert') ?><span>هنوز هیچ روشِ پرداختی فعال نشده؛ با پشتیبانی تماس بگیرید.</span></div>
        <?php else: ?>
        <div class="pms">
          <?php foreach ($methods as $k => $lbl): ?>
          <label class="pm"><input type="radio" name="method" value="<?= e($k) ?>"<?= $old['method'] === $k ? ' checked' : '' ?>>
            <span class="pm-in g"><span class="orb sm <?= $k === 'wallet' ? 'o-grn' : ($k === 'zarinpal' ? 'o-tg' : 'o-gold') ?>"><?= ic($pmIcons[$k]) ?></span><span><b><?= e($lbl) ?></b><small><?= e($pmSub[$k]) ?></small></span><i class="dot"></i></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="total g"><span>مبلغِ قابلِ پرداخت</span><b><span data-total><?= fa(product_total($p, $q0)) ?></span> <small>تومان</small></b></div>
      <button class="btn b-pri b-lg b-block" type="submit"<?= $methods ? '' : ' disabled' ?>><?= ic('shield') ?> ثبت و پرداخت</button>
      <p class="hint center">با ثبتِ سفارش، <a href="<?= e(url('page', ['k' => 'terms'])) ?>">قوانین</a> را می‌پذیرید.</p>
    </form>
  </div>
</section>
