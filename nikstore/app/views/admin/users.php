<div class="toolbar g">
  <form method="get" class="srch"><input type="hidden" name="a" value="users"><input class="inp" type="search" name="q" value="<?= e($qq) ?>" placeholder="نام یا موبایل…"><button class="btn b-glass" type="submit"><?= ic('search') ?></button></form>
</div>
<div class="panel g">
  <?php if (!$list): ?><p class="muted empty-in">کاربری نیست.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>نام</th><th>موبایل</th><th>موجودی</th><th>سفارش‌ها</th><th>عضویت</th><th></th></tr></thead>
    <tbody><?php foreach ($list as $u): ?>
      <tr class="<?= (int)$u['blocked'] ? 'off' : '' ?>">
        <td><b><?= e($u['name']) ?></b><?= (int)$u['blocked'] ? ' <span class="tag">مسدود</span>' : '' ?></td>
        <td dir="ltr"><?= e($u['mobile']) ?></td>
        <td><?= toman($u['balance']) ?></td>
        <td><?= fa((int)val('SELECT COUNT(*) FROM orders WHERE user_id = ?', [(int)$u['id']])) ?></td>
        <td class="muted sm"><?= jdate($u['created'], false) ?></td>
        <td><a class="btn b-glass b-sm" href="<?= e(aurl('user', ['id' => $u['id']])) ?>">مدیریت</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
