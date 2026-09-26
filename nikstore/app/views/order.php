<?php
$st = $o['status'];
$ps = provider_state($o);
$isNum = $o['provider'] === '5sim';
$live = in_array($st, ['review', 'paid', 'processing'], true);
$paid = (int)$o['paid_at'] > 0;
$steps = [
    ['ثبتِ سفارش', true],
    ['پرداخت', $paid],
    ['در حالِ انجام', in_array($st, ['processing', 'completed'], true)],
    ['تکمیل', $st === 'completed'],
];
$methodLbl = ['wallet' => 'کیف پول', 'zarinpal' => 'درگاه آنلاین', 'card' => 'کارت به کارت'];
$f = $p ? field_meta($p['field']) : null;
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="./">خانه</a><?= ic('arrow') ?><span>سفارش</span></nav>
    <div class="ord-head">
      <div><span class="muted sm">کدِ سفارش</span><h1 class="code" dir="ltr"><?= e($o['code']) ?> <button type="button" class="icon-btn sm" data-copy="<?= e($o['code']) ?>" aria-label="کپی"><?= ic('copy') ?></button></h1></div>
      <span class="badge <?= status_cls($st) ?>" <?= $live ? 'data-poll="' . e(url('state', ['code' => $o['code']])) . '" data-state="' . e($st . '|' . md5((string)$o['delivery'])) . '"' : '' ?>><?= ic(order_statuses()[$st][2] ?? 'clock') ?><?= e(status_label($st)) ?></span>
    </div>
  </div>
</section>

<section class="sec tight">
  <div class="wrap ord-lay">
    <div class="ord-main">
      <?php if (!in_array($st, ['canceled', 'failed', 'refunded'], true)): ?>
      <div class="timeline g">
        <?php foreach ($steps as $i => [$l, $done]): ?><div class="tl<?= $done ? ' done' : '' ?>"><i><?= $done ? ic('check') : fa($i + 1) ?></i><span><?= e($l) ?></span></div><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($isNum && in_array($st, ['processing', 'completed'], true)): ?>
      <div class="deliv g blur num-box">
        <span class="muted sm">شماره‌ی شما</span>
        <div class="num-phone" dir="ltr"><?= e($ps['phone'] ?? '—') ?></div>
        <?php if (!empty($ps['phone'])): ?><button type="button" class="btn b-glass b-sm" data-copy="<?= e(preg_replace('/\s+/', '', (string)$ps['phone'])) ?>"><?= ic('copy') ?> کپی شماره</button><?php endif; ?>
        <?php if (!empty($ps['code'])): ?>
          <div class="code-box"><span class="muted sm">کدِ تایید</span><div class="big-code" dir="ltr"><?= e($ps['code']) ?></div>
          <button type="button" class="btn b-grn b-sm" data-copy="<?= e($ps['code']) ?>"><?= ic('copy') ?> کپی کد</button></div>
        <?php else: ?>
          <div class="wait"><span class="spin"></span>شماره را در تلگرام وارد کنید؛ کد به‌محضِ رسیدن همین‌جا نمایش داده می‌شود…</div>
          <form method="post" data-confirm="شماره لغو شود؟ فقط تا پیش از رسیدنِ کد ممکن است."><?= csrf_field() ?><input type="hidden" name="op" value="numcancel">
            <button class="btn b-glass b-sm" type="submit"><?= ic('close') ?> لغوِ شماره</button></form>
        <?php endif; ?>
      </div>
      <?php elseif (trim((string)$o['delivery']) !== ''): ?>
      <div class="deliv g blur"><h3><?= ic('bolt') ?> وضعیتِ تحویل</h3><p><?= nl2br(e($o['delivery'])) ?></p></div>
      <?php endif; ?>

      <?php if ($st === 'pending'): ?>
      <div class="pay-box g blur">
        <h3><?= ic('card') ?> پرداختِ سفارش</h3>
        <p class="muted">مبلغِ <?= toman($o['amount']) ?> را با یکی از روش‌های زیر پرداخت کنید.</p>
        <div class="pay-acts">
          <?php if (isset($methods['zarinpal'])): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="pay"><input type="hidden" name="method" value="zarinpal">
            <button class="btn b-pri b-lg b-block" type="submit"><?= ic('card') ?> پرداختِ آنلاین</button></form>
          <?php endif; ?>
          <?php if (isset($methods['wallet']) && $o['kind'] !== 'topup'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="pay"><input type="hidden" name="method" value="wallet">
            <button class="btn b-grn b-lg b-block" type="submit"><?= ic('wallet') ?> پرداخت از کیف پول (<?= toman($me['balance'] ?? 0) ?>)</button></form>
          <?php endif; ?>
        </div>
        <?php if (card_on()): ?>
        <div class="card-pay">
          <h4><?= ic('coin') ?> کارت به کارت</h4>
          <div class="bank-card">
            <span class="muted sm"><?= e(setting('card_bank') ?: 'شماره کارت') ?></span>
            <div class="cc" dir="ltr"><?= e(setting('card_number')) ?></div>
            <div class="bank-foot"><span><?= e(setting('card_holder')) ?></span><button type="button" class="btn b-glass b-sm" data-copy="<?= e(preg_replace('/\D/', '', ns_digits(setting('card_number')))) ?>"><?= ic('copy') ?> کپی</button></div>
          </div>
          <p class="hint">دقیقا <?= toman($o['amount']) ?> واریز کنید، بعد تصویرِ رسید را بفرستید.</p>
          <form method="post" enctype="multipart/form-data" class="rcpt"><?= csrf_field() ?><input type="hidden" name="op" value="receipt">
            <label class="file g"><input type="file" name="receipt" accept="image/jpeg,image/png,image/webp" required><?= ic('upload') ?><span data-file>انتخابِ تصویرِ رسید</span></label>
            <input class="inp" type="text" name="rnote" maxlength="120" placeholder="چهار رقمِ آخرِ کارت یا شماره پیگیری (اختیاری)">
            <button class="btn b-gold b-block" type="submit"><?= ic('upload') ?> ارسالِ رسید</button>
          </form>
        </div>
        <?php endif; ?>
        <form method="post" class="cancel-f" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><button class="link" type="submit">لغوِ سفارش</button></form>
      </div>
      <?php elseif ($st === 'review'): ?>
      <div class="deliv g blur"><h3><?= ic('eye') ?> رسید در حالِ بررسی است</h3><p>رسیدِ شما ثبت شد. بعد از تایید، سفارش خودکار وارد صفِ انجام می‌شود — همین صفحه به‌روز می‌شود.</p></div>
      <?php elseif ($st === 'paid'): ?>
      <div class="deliv g blur"><h3><?= ic('clock') ?> در صفِ انجام</h3><p>پرداخت تایید شد و سفارش به‌زودی انجام می‌شود. همین صفحه خودکار به‌روز می‌شود.</p></div>
      <?php endif; ?>
    </div>

    <aside class="sum g blur">
      <h3>جزئیاتِ سفارش</h3>
      <div class="kv">
        <div><span>خدمت</span><b><?= e($o['title']) ?></b></div>
        <?php if ((int)$o['qty'] > 1): ?><div><span>تعداد</span><b><?= fa($o['qty']) ?></b></div><?php endif; ?>
        <?php if ((string)$o['target'] !== ''): ?><div><span><?= e($f['label'] ?? 'مقصد') ?></span><b dir="ltr" class="brk"><?= e($o['target']) ?></b></div><?php endif; ?>
        <div><span>مبلغ</span><b><?= toman($o['amount']) ?></b></div>
        <div><span>تاریخ</span><b><?= jdate($o['created']) ?></b></div>
        <?php if ($paid): ?><div><span>روشِ پرداخت</span><b><?= e($methodLbl[$o['method']] ?? '—') ?></b></div><?php endif; ?>
        <?php if ((string)$o['ref'] !== '' && $o['ref'] !== 'WALLET'): ?><div><span>کدِ پیگیریِ پرداخت</span><b dir="ltr"><?= e($o['ref']) ?></b></div><?php endif; ?>
      </div>
      <?php if ((string)$o['note'] !== ''): ?><p class="muted sm">توضیحات: <?= e($o['note']) ?></p><?php endif; ?>
      <p class="hint">این کد را نگه دارید؛ با کدِ سفارش و شماره موبایل همیشه از «پیگیری سفارش» پیدایش می‌کنید.</p>
    </aside>
  </div>
</section>
