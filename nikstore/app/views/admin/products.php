<div class="toolbar g">
  <div class="tabs">
    <a class="tab <?= !$cf ? 'on' : '' ?>" href="<?= e(aurl('products')) ?>">همه</a>
    <?php foreach ($cats as $c): ?><a class="tab <?= $cf === (int)$c['id'] ? 'on' : '' ?>" href="<?= e(aurl('products', ['cat' => $c['id']])) ?>"><?= e($c['title']) ?></a><?php endforeach; ?>
  </div>
  <a class="btn b-pri" href="<?= e(aurl('product', $cf ? ['cat' => $cf] : [])) ?>"><?= ic('plus') ?> محصولِ تازه</a>
</div>
<?php foreach ($list as $c): ?>
<div class="panel g">
  <div class="panel-h"><h3><?= ic($c['icon'] ?: 'bag') ?> <?= e($c['title']) ?> <small class="muted">(<?= fa(count($c['items'])) ?>)</small></h3><a class="btn b-glass b-sm" href="<?= e(aurl('product', ['cat' => $c['id']])) ?>"><?= ic('plus') ?> افزودن</a></div>
  <?php if (!$c['items']): ?><p class="muted empty-in">محصولی نیست.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>عنوان</th><th>قیمت</th><th>ورودی</th><th>تحویل</th><th>وضعیت</th><th></th></tr></thead>
    <tbody><?php foreach ($c['items'] as $p): ?>
      <tr class="<?= (int)$p['active'] ? '' : 'off' ?>">
        <td class="emo-cell"><?= e($p['emoji']) ?></td>
        <td><b><?= e($p['title']) ?></b><?= $p['badge'] !== '' ? ' <span class="tag">' . e($p['badge']) . '</span>' : '' ?></td>
        <td><?= e(product_price_label($p)) ?></td>
        <td class="muted sm"><?= e(field_types()[$p['field']] ?? $p['field']) ?></td>
        <td class="muted sm"><?= e(['manual' => 'دستی', 'smm' => 'SMM', '5sim' => '5sim'][$p['provider']] ?? '') ?><?= provider_effective($p) !== $p['provider'] ? ' <span class="tag">تنظیم‌نشده</span>' : '' ?></td>
        <td><form method="post" action="<?= e(aurl('products', ['cat' => $cf])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="pill-t <?= (int)$p['active'] ? 'on' : '' ?>" type="submit"><?= (int)$p['active'] ? 'فعال' : 'غیرفعال' ?></button></form></td>
        <td class="row-acts"><a class="icon-btn sm" href="<?= e(aurl('product', ['id' => $p['id']])) ?>" aria-label="ویرایش"><?= ic('edit') ?></a>
          <form method="post" action="<?= e(aurl('products', ['cat' => $cf])) ?>" data-confirm="حذف شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="icon-btn sm" type="submit" aria-label="حذف"><?= ic('trash') ?></button></form></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
