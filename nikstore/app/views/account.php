<section class="page-head">
  <div class="wrap">
    <div class="ph-in">
      <span class="orb big o-tg av-big"><?= e(mb_substr($me['name'], 0, 1)) ?></span>
      <div><h1><?= e($me['name']) ?></h1><p class="muted" dir="ltr"><?= e(fa_digits($me['mobile'])) ?></p></div>
      <form method="post" action="<?= e(url('logout')) ?>" class="ph-act"><?= csrf_field() ?><button class="btn b-glass" type="submit"><?= ic('logout') ?> خروج</button></form>
    </div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap acc-lay">
    <div class="acc-side">
      <div class="bal-card g blur">
        <i class="ring"></i>
        <span class="muted">موجودیِ کیف پول</span>
        <b><?= toman($me['balance']) ?></b>
        <?php if (wallet_on() && $methods): ?>
        <form method="post" class="topup"><?= csrf_field() ?><input type="hidden" name="op" value="topup">
          <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
          <label class="field"><span>مبلغِ شارژ (تومان)</span><input class="inp num" type="number" name="amount" min="<?= (int)setting('topup_min') ?>" step="1000" value="<?= max(100000, (int)setting('topup_min')) ?>" required></label>
          <div class="chips"><?php foreach ([100000, 200000, 500000, 1000000] as $v): ?><button type="button" class="chip" data-set="amount" data-v="<?= $v ?>"><?= fa($v) ?></button><?php endforeach; ?></div>
          <div class="pms compact">
            <?php $first = true; foreach ($methods as $k => $lbl): ?>
            <label class="pm"><input type="radio" name="method" value="<?= e($k) ?>"<?= $first ? ' checked' : '' ?>><span class="pm-in g"><span class="orb sm <?= $k === 'zarinpal' ? 'o-tg' : 'o-gold' ?>"><?= ic($k === 'zarinpal' ? 'card' : 'coin') ?></span><span><b><?= e($lbl) ?></b></span><i class="dot"></i></span></label>
            <?php $first = false; endforeach; ?>
          </div>
          <button class="btn b-grn b-block" type="submit"><?= ic('plus') ?> شارژِ کیف پول</button>
        </form>
        <?php endif; ?>
      </div>
      <details class="g pass-box"><summary><?= ic('lock') ?> تغییرِ رمز</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="password">
          <label class="field"><span>رمزِ فعلی</span><input class="inp ltr" type="password" name="cur" required autocomplete="current-password"></label>
          <label class="field"><span>رمزِ تازه</span><input class="inp ltr" type="password" name="new" minlength="6" required autocomplete="new-password"></label>
          <button class="btn b-glass b-block" type="submit">ذخیره</button>
        </form>
      </details>
    </div>
    <div class="acc-main">
      <div class="panel g">
        <h3><?= ic('list') ?> سفارش‌های من</h3>
        <?php if (!$orders): ?><p class="muted empty-in">هنوز سفارشی ثبت نکرده‌اید. <a href="./#services">شروعِ خرید</a></p>
        <?php else: ?>
        <div class="tbl-wrap"><table class="tbl">
          <thead><tr><th>سفارش</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
          <tbody><?php foreach ($orders as $o): ?>
            <tr><td><b><?= e($o['title']) ?></b><small class="muted" dir="ltr"><?= e($o['code']) ?></small></td><td><?= toman($o['amount']) ?></td>
            <td><span class="badge sm <?= status_cls($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td><td class="muted"><?= jdate($o['created'], false) ?></td>
            <td><a class="btn b-glass b-sm" href="<?= e(url('order', ['code' => $o['code']])) ?>">مشاهده</a></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
      </div>
      <?php if ($txs): ?>
      <div class="panel g">
        <h3><?= ic('wallet') ?> گردشِ کیف پول</h3>
        <div class="tbl-wrap"><table class="tbl">
          <thead><tr><th>شرح</th><th>مبلغ</th><th>تاریخ</th></tr></thead>
          <tbody><?php foreach ($txs as $t): ?><tr><td><?= e($t['reason']) ?></td><td class="<?= (int)$t['amount'] >= 0 ? 'pos' : 'neg' ?>"><?= ((int)$t['amount'] >= 0 ? '+' : '−') . toman(abs((int)$t['amount'])) ?></td><td class="muted"><?= jdate($t['created']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
