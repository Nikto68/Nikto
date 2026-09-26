<div class="toolbar g">
  <div class="tabs">
    <?php foreach (['' => 'همه', 'attn' => 'نیازِ اقدام'] + array_map(fn($x) => $x[0], order_statuses()) as $k => $l): ?>
    <a class="tab <?= $st === (string)$k ? 'on' : '' ?>" href="<?= e(aurl('orders', array_filter(['st' => $k, 'q' => $qq]))) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="srch"><input type="hidden" name="a" value="orders"><input type="hidden" name="st" value="<?= e($st) ?>">
    <input class="inp" type="search" name="q" value="<?= e($qq) ?>" placeholder="کد، آیدی، لینک یا موبایل…"><button class="btn b-glass" type="submit"><?= ic('search') ?></button></form>
</div>
<div class="panel g">
  <div class="panel-h"><h3><?= fa($total) ?> سفارش</h3></div>
  <?php require NS_APP . '/views/admin/_orders_table.php'; ?>
  <?php if ($pages > 1): ?>
  <div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $pg ? 'on' : '' ?>" href="<?= e(aurl('orders', array_filter(['st' => $st, 'q' => $qq, 'pg' => $i]))) ?>"><?= fa($i) ?></a><?php endfor; ?></div>
  <?php endif; ?>
</div>
