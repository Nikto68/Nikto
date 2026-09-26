<?php
$st = $o['status'];
$ps = provider_state($o);
$f = $p ? field_meta($p['field']) : null;
$prov = $p ? provider_effective($p) : 'manual';
?>
<div class="ord-admin">
  <div class="panel g">
    <div class="panel-h"><h3 dir="ltr"><?= e($o['code']) ?></h3><span class="badge <?= status_cls($st) ?>"><?= e(status_label($st)) ?></span></div>
    <div class="kv two-col">
      <div><span>خدمت</span><b><?= e($o['title']) ?><?= $o['kind'] === 'topup' ? ' (شارژ)' : '' ?></b></div>
      <div><span>تعداد</span><b><?= fa($o['qty']) ?></b></div>
      <div><span>مبلغ</span><b><?= toman($o['amount']) ?></b></div>
      <div><span><?= e($f['label'] ?? 'مقصد') ?></span><b dir="ltr" class="brk"><?= e($o['target'] ?: '—') ?> <?php if ($o['target']): ?><button type="button" class="icon-btn sm" data-copy="<?= e($o['target']) ?>"><?= ic('copy') ?></button><?php endif; ?></b></div>
      <div><span>خریدار</span><b><?= $u ? '<a href="' . e(aurl('user', ['id' => $u['id']])) . '">' . e($u['name']) . '</a>' : e($o['name'] ?: 'مهمان') ?></b></div>
      <div><span>موبایل</span><b dir="ltr"><?= e($o['contact']) ?></b></div>
      <div><span>روشِ پرداخت</span><b><?= e(['wallet' => 'کیف پول', 'zarinpal' => 'درگاه زرین‌پال', 'card' => 'کارت به کارت'][$o['method']] ?? '—') ?></b></div>
      <div><span>کدِ پیگیری</span><b dir="ltr"><?= e($o['ref'] ?: '—') ?></b></div>
      <div><span>ثبت</span><b><?= jdate($o['created']) ?></b></div>
      <div><span>پرداخت</span><b><?= jdate($o['paid_at']) ?></b></div>
      <div><span>تحویل</span><b><?= e(['manual' => 'دستی', 'smm' => 'پنل SMM', '5sim' => '5sim'][$o['provider'] ?: $prov] ?? 'دستی') ?><?= $o['provider_id'] ? ' — #' . e($o['provider_id']) : '' ?></b></div>
      <?php if (!empty($ps['phone'])): ?><div><span>شماره / کد</span><b dir="ltr"><?= e($ps['phone']) ?> — <?= e($ps['code'] ?: '…') ?></b></div><?php endif; ?>
    </div>
    <?php if ((string)$o['note'] !== ''): ?><div class="note-box"><b>توضیحِ خریدار:</b><p><?= nl2br(e($o['note'])) ?></p></div><?php endif; ?>
  </div>

  <?php if ($o['receipt']): ?>
  <div class="panel g">
    <h3><?= ic('coin') ?> رسیدِ کارت‌به‌کارت</h3>
    <?php if ((string)$o['receipt_note'] !== ''): ?><p class="muted">یادداشتِ خریدار: <?= e($o['receipt_note']) ?></p><?php endif; ?>
    <a href="<?= e(aurl('receipt', ['id' => $o['id']])) ?>" target="_blank" rel="noopener"><img class="rcpt-img" src="<?= e(aurl('receipt', ['id' => $o['id']])) ?>" alt="رسید"></a>
    <?php if ($st === 'review'): ?>
    <div class="acts">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="approve"><button class="btn b-grn" type="submit"><?= ic('check') ?> تاییدِ رسید (<?= toman($o['amount']) ?>)</button></form>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="op" value="reject"><input class="inp" name="why" placeholder="دلیلِ رد (به خریدار نشان داده می‌شود)"><button class="btn b-danger" type="submit"><?= ic('close') ?> رد</button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="panel g">
    <h3><?= ic('bolt') ?> تحویل و یادداشت</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="save">
      <label class="field"><span>متنِ تحویل (به خریدار نشان داده می‌شود)</span><textarea class="inp" name="delivery" rows="3"><?= e($o['delivery']) ?></textarea></label>
      <label class="field"><span>یادداشتِ داخلی (فقط مدیر)</span><textarea class="inp" name="admin_note" rows="3"><?= e($o['admin_note']) ?></textarea></label>
      <button class="btn b-glass" type="submit"><?= ic('check') ?> ذخیره</button>
    </form>
    <div class="acts">
      <?php if (in_array($st, ['paid', 'failed'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="processing"><button class="btn b-glass" type="submit"><?= ic('refresh') ?> در حالِ انجام</button></form><?php endif; ?>
      <?php if (in_array($st, ['paid', 'processing', 'failed'], true)): ?><form method="post" data-confirm="سفارش «تکمیل‌شده» شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="complete"><button class="btn b-grn" type="submit"><?= ic('check') ?> تکمیل شد</button></form><?php endif; ?>
      <?php if ($st === 'paid' && $prov !== 'manual'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="dispatch"><button class="btn b-pri" type="submit"><?= ic('send') ?> ارسالِ خودکار به <?= $prov === 'smm' ? 'پنل SMM' : '5sim' ?></button></form><?php endif; ?>
      <?php if ($st === 'processing' && in_array($o['provider'], ['smm', '5sim'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="refresh"><button class="btn b-glass" type="submit"><?= ic('refresh') ?> پرسیدنِ وضعیت</button></form><?php endif; ?>
      <?php if (in_array($st, ['pending', 'review'], true)): ?><form method="post" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><button class="btn b-danger" type="submit"><?= ic('close') ?> لغو</button></form><?php endif; ?>
    </div>
    <?php if (in_array($st, ['paid', 'processing', 'failed'], true) && (int)$o['paid_at'] > 0 && $o['kind'] !== 'topup'): ?>
    <div class="acts refund">
      <form method="post" class="inline" data-confirm="مبلغ برگشت داده شود؟"><?= csrf_field() ?>
        <input class="inp" name="why" placeholder="دلیل (اختیاری)">
        <?php if ((int)$o['user_id'] > 0): ?><button class="btn b-gold" name="op" value="refund" type="submit"><?= ic('wallet') ?> برگشت به کیف پول</button><?php endif; ?>
        <button class="btn b-glass" name="op" value="manual_refund" type="submit">ثبتِ برگشتِ دستی</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
