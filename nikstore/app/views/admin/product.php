<form class="panel g form-grid" method="post" data-pform>
  <?= csrf_field() ?>
  <?php if ($err !== ''): ?><div class="alert a-err span2"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
  <label class="field"><span>دسته</span><select class="inp" name="cat_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$d['cat_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>عنوان</span><input class="inp" name="title" value="<?= e($d['title']) ?>" required maxlength="120"></label>
  <label class="field span2"><span>توضیح</span><input class="inp" name="descr" value="<?= e($d['descr']) ?>" maxlength="400"></label>
  <label class="field"><span>ایموجی / پرچم</span><input class="inp" name="emoji" value="<?= e($d['emoji']) ?>" maxlength="8"></label>
  <label class="field"><span>برچسب (مثلا پرفروش)</span><input class="inp" name="badge" value="<?= e($d['badge']) ?>" maxlength="30"></label>

  <label class="field"><span>نوعِ قیمت</span><select class="inp" name="pricing" data-pricing>
    <option value="fixed"<?= $d['pricing'] === 'fixed' ? ' selected' : '' ?>>بسته‌ی ثابت</option>
    <option value="unit"<?= $d['pricing'] === 'unit' ? ' selected' : '' ?>>تعدادی (قیمت × تعداد)</option></select></label>
  <label class="field"><span>قیمت (تومان)</span><input class="inp num" name="price" value="<?= (int)$d['price'] ?>" inputmode="numeric" required></label>
  <div class="unit-only span2 form-grid inner">
    <label class="field"><span>قیمت برای هر چند عدد</span><input class="inp num" name="per" value="<?= (int)$d['per'] ?>" inputmode="numeric"></label>
    <label class="field"><span>واحد (مثلا فالوور)</span><input class="inp" name="unit" value="<?= e($d['unit']) ?>" maxlength="30"></label>
    <label class="field"><span>حداقل تعداد</span><input class="inp num" name="qmin" value="<?= (int)$d['qmin'] ?>" inputmode="numeric"></label>
    <label class="field"><span>حداکثر تعداد</span><input class="inp num" name="qmax" value="<?= (int)$d['qmax'] ?>" inputmode="numeric"></label>
    <label class="field"><span>گامِ تعداد</span><input class="inp num" name="qstep" value="<?= (int)$d['qstep'] ?>" inputmode="numeric"></label>
  </div>

  <label class="field"><span>از خریدار بپرس</span><select class="inp" name="field"><?php foreach (field_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $d['field'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>روشِ تحویل</span><select class="inp" name="provider">
    <option value="manual"<?= $d['provider'] === 'manual' ? ' selected' : '' ?>>دستی (مدیر انجام می‌دهد)</option>
    <option value="smm"<?= $d['provider'] === 'smm' ? ' selected' : '' ?>>خودکار — پنل SMM</option>
    <option value="5sim"<?= $d['provider'] === '5sim' ? ' selected' : '' ?>>خودکار — 5sim (شماره مجازی)</option></select></label>
  <label class="field span2"><span>شناسه‌ی سرویس (SMM: شماره‌ی سرویس — 5sim: کشور/اپراتور/سرویس مثل russia/any/telegram)</span><input class="inp ltr" name="provider_ref" value="<?= e($d['provider_ref']) ?>" maxlength="120"></label>
  <label class="field"><span>ترتیب</span><input class="inp num" name="sort" value="<?= (int)$d['sort'] ?>" inputmode="numeric"></label>
  <label class="field chk"><input type="checkbox" name="active" value="1"<?= (int)$d['active'] ? ' checked' : '' ?>><span>فعال — در سایت نمایش داده شود</span></label>
  <div class="span2 form-foot"><button class="btn b-pri b-lg" type="submit"><?= ic('check') ?> ذخیره</button><a class="btn b-glass b-lg" href="<?= e(aurl('products', ['cat' => $d['cat_id']])) ?>">انصراف</a></div>
</form>
