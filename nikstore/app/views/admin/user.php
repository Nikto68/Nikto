<div class="stat-grid">
  <div class="stat g"><span class="orb sm o-tg"><?= ic('user') ?></span><div><span><?= e($u['name']) ?></span><b dir="ltr"><?= e($u['mobile']) ?></b><small>عضو از <?= jdate($u['created'], false) ?></small></div></div>
  <div class="stat g"><span class="orb sm o-grn"><?= ic('wallet') ?></span><div><span>موجودی</span><b><?= toman($u['balance']) ?></b></div></div>
</div>
<div class="grid2">
  <form class="panel g" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="balance">
    <h3><?= ic('wallet') ?> تغییرِ موجودی</h3>
    <div class="two">
      <label class="field"><span>مبلغ (تومان)</span><input class="inp num" name="amount" inputmode="numeric" required></label>
      <label class="field"><span>نوع</span><select class="inp" name="dir"><option value="add">افزایش</option><option value="sub">کسر</option></select></label>
    </div>
    <label class="field"><span>دلیل</span><input class="inp" name="why" maxlength="120"></label>
    <button class="btn b-grn" type="submit">اعمال</button>
  </form>
  <div class="panel g">
    <h3><?= ic('lock') ?> حساب</h3>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="op" value="pass"><input class="inp ltr" type="text" name="np" placeholder="رمزِ تازه (حداقل ۶)" minlength="6"><button class="btn b-glass" type="submit">تنظیمِ رمز</button></form>
    <form method="post" data-confirm="<?= (int)$u['blocked'] ? 'رفعِ مسدودی؟' : 'کاربر مسدود شود؟' ?>"><?= csrf_field() ?><input type="hidden" name="op" value="block"><button class="btn <?= (int)$u['blocked'] ? 'b-grn' : 'b-danger' ?>" type="submit"><?= (int)$u['blocked'] ? 'رفعِ مسدودی' : 'مسدود کردن' ?></button></form>
  </div>
</div>
<div class="panel g"><h3><?= ic('list') ?> سفارش‌ها</h3><?php $list = $orders; require NS_APP . '/views/admin/_orders_table.php'; ?></div>
<?php if ($txs): ?>
<div class="panel g"><h3><?= ic('wallet') ?> گردشِ کیف پول</h3>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>شرح</th><th>مبلغ</th><th>تاریخ</th></tr></thead><tbody>
  <?php foreach ($txs as $t): ?><tr><td><?= e($t['reason']) ?></td><td class="<?= (int)$t['amount'] >= 0 ? 'pos' : 'neg' ?>"><?= ((int)$t['amount'] >= 0 ? '+' : '−') . toman(abs((int)$t['amount'])) ?></td><td class="muted sm"><?= jdate($t['created']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
