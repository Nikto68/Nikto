<form class="panel g" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="save">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>عنوان</th><th>زیرعنوان</th><th>ترتیب</th><th>فعال</th><th>محصولات</th></tr></thead>
    <tbody><?php foreach ($cats as $c): ?>
      <tr>
        <td><span class="orb sm <?= ['stars' => 'o-gold', 'premium' => 'o-vio', 'gifts' => 'o-pink', 'numbers' => 'o-grn', 'instagram' => 'o-ig'][$c['kind']] ?? 'o-tg' ?>"><?= ic($c['icon'] ?: 'bag') ?></span></td>
        <td><input class="inp" name="c[<?= (int)$c['id'] ?>][title]" value="<?= e($c['title']) ?>" maxlength="80"></td>
        <td><input class="inp" name="c[<?= (int)$c['id'] ?>][subtitle]" value="<?= e($c['subtitle']) ?>" maxlength="200"></td>
        <td><input class="inp num xs" name="c[<?= (int)$c['id'] ?>][sort]" value="<?= (int)$c['sort'] ?>"></td>
        <td><input type="checkbox" name="c[<?= (int)$c['id'] ?>][active]" value="1"<?= (int)$c['active'] ? ' checked' : '' ?>></td>
        <td><a class="btn b-glass b-sm" href="<?= e(aurl('products', ['cat' => $c['id']])) ?>"><?= fa((int)val('SELECT COUNT(*) FROM products WHERE cat_id = ?', [(int)$c['id']])) ?></a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <button class="btn b-pri" type="submit"><?= ic('check') ?> ذخیره‌ی دسته‌ها</button>
</form>
<form class="panel g form-grid" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="add">
  <h3 class="span2"><?= ic('plus') ?> دسته‌ی تازه</h3>
  <label class="field"><span>عنوان</span><input class="inp" name="title" maxlength="80" required></label>
  <label class="field"><span>نامکِ لاتین (در آدرس)</span><input class="inp ltr" name="slug" maxlength="40" placeholder="youtube" required></label>
  <div class="span2"><button class="btn b-glass" type="submit"><?= ic('plus') ?> افزودن</button></div>
</form>
