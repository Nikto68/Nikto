<?php $rowsList = $recent ?? $list ?? []; ?>
<?php if (!$rowsList): ?><p class="muted empty-in">سفارشی نیست.</p><?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>کد</th><th>خدمت</th><th>مقصد</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت</th><th>زمان</th></tr></thead>
  <tbody>
  <?php foreach ($rowsList as $o): ?>
    <tr class="click" data-href="<?= e(aurl('order', ['id' => $o['id']])) ?>">
      <td dir="ltr"><a href="<?= e(aurl('order', ['id' => $o['id']])) ?>"><b><?= e($o['code']) ?></b></a></td>
      <td><?= e($o['title']) ?><?= (int)$o['qty'] > 1 ? ' <small class="muted">× ' . fa($o['qty']) . '</small>' : '' ?></td>
      <td dir="ltr" class="brk sm"><?= e($o['target'] ?: $o['contact']) ?></td>
      <td><?= toman($o['amount']) ?></td>
      <td class="muted sm"><?= e(['wallet' => 'کیف پول', 'zarinpal' => 'درگاه', 'card' => 'کارت'][$o['method']] ?? '—') ?></td>
      <td><span class="badge sm <?= status_cls($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td class="muted sm"><?= e(ago($o['created'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
