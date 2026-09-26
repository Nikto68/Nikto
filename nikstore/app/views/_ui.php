<?php
function ui_amt($n) {
    if ((int)$n <= 0) return '<span class="ask">استعلام قیمت</span>';
    return '<b class="amt">' . fa($n) . '</b> <small class="cur">تومان</small>';
}

function ui_orb($kind) {
    return ['stars' => 'o-gold', 'premium' => 'o-vio', 'gifts' => 'o-pink', 'numbers' => 'o-grn', 'instagram' => 'o-ig', 'telegram' => 'o-tg'][$kind] ?? 'o-tg';
}

function ui_buy($p, $label = 'خرید', $cls = 'b-glass') {
    return '<a class="btn ' . $cls . ' b-block" href="' . e(url('buy', ['id' => $p['id']])) . '">' . e($label) . ' ' . ic('arrow') . '</a>';
}

function ui_card($p, $kind) {
    $badge = $p['badge'] !== '' ? '<span class="tag' . ($kind === 'stars' ? ' gold' : '') . '">' . e($p['badge']) . '</span>' : '';
    if ($kind === 'stars') {
        if ($p['pricing'] === 'unit') return '';
        return '<article class="pk g lift rv"><div class="pk-top"><span class="pk-star">' . ic('star') . '</span>' . $badge . '</div>'
             . '<div class="pk-q">' . e($p['title']) . '</div><div>' . ui_amt($p['price']) . '</div>'
             . '<div class="muted sm">' . e($p['descr']) . '</div>' . ui_buy($p) . '</article>';
    }
    if ($kind === 'premium') {
        return '<article class="plan g lift rv' . ($p['badge'] !== '' && str_contains($p['badge'], 'بهترین') ? ' hot' : '') . '">'
             . ($p['badge'] !== '' && str_contains($p['badge'], 'بهترین') ? '<i class="ring"></i><span class="plan-badge">' . e($p['badge']) . '</span>' : ($badge !== '' ? '<div class="plan-tag">' . $badge . '</div>' : ''))
             . '<span class="orb o-vio">' . ic('crown') . '</span><h3>' . e($p['title']) . '</h3><p class="muted">' . e($p['descr']) . '</p>'
             . '<div class="plan-price">' . ui_amt($p['price']) . '</div>'
             . '<ul><li>' . ic('check') . ' فعال‌سازی با آیدی، بدون رمز</li><li>' . ic('check') . ' قابل خرید برای دوستان</li><li>' . ic('check') . ' همه‌ی امکاناتِ پریمیوم</li></ul>'
             . ui_buy($p, 'خرید اشتراک', str_contains($p['badge'], 'بهترین') ? 'b-pri' : 'b-glass') . '</article>';
    }
    if ($kind === 'gifts') {
        return '<article class="gift g lift rv"><div class="gift-e">' . e($p['emoji'] ?: '🎁') . '</div><h3>' . e($p['title']) . '</h3>'
             . '<span class="st">' . ic('star') . e($p['descr']) . '</span><div class="gp">' . ui_amt($p['price']) . '</div>' . ui_buy($p, 'ارسال گیفت') . '</article>';
    }
    if ($kind === 'numbers') {
        return '<a class="ctry g lift" data-name="' . e(mb_strtolower($p['title'])) . '" href="' . e(url('buy', ['id' => $p['id']])) . '">'
             . '<span class="flag">' . e($p['emoji'] ?: '🌍') . '</span><span class="ctry-t"><b>' . e($p['title']) . '</b><span>' . e($p['descr']) . '</span></span>'
             . '<span class="ctry-p">' . ((int)$p['price'] > 0 ? '<b class="amt">' . fa($p['price']) . '</b>تومان' : '<span class="ask">استعلام</span>') . '</span></a>';
    }
    $orb = ui_orb($kind);
    return '<article class="svc g lift rv"><div class="svc-top"><span class="emo">' . e($p['emoji'] ?: '✨') . '</span>' . $badge . '</div>'
         . '<h3>' . e($p['title']) . '</h3><p class="muted">' . e($p['descr']) . '</p>'
         . '<div class="svc-price">' . ui_amt($p['price']) . ($p['pricing'] === 'unit' ? '<span class="per">هر ' . fa($p['per']) . ' ' . e($p['unit'] ?: 'عدد') . '</span>' : '') . '</div>'
         . ($p['pricing'] === 'unit' ? '<div class="range">' . ic('tag') . ' حداقل ' . fa($p['qmin']) . ((int)$p['qmax'] > (int)$p['qmin'] ? ' · حداکثر ' . fa($p['qmax']) : '') . '</div>' : '')
         . ui_buy($p, 'ثبت سفارش', $kind === 'instagram' ? 'b-ig' : 'b-glass') . '</article>';
}

function ui_star_calc($p, $big = true) {
    if (!$p || $p['pricing'] !== 'unit') return '';
    $v = max((int)$p['qmin'], 500);
    ob_start(); ?>
    <form class="calc g blur rv" method="get" action="index.php" data-calc data-price="<?= (int)$p['price'] ?>" data-per="<?= max(1, (int)$p['per']) ?>" data-min="<?= (int)$p['qmin'] ?>" data-max="<?= (int)$p['qmax'] ?>">
      <i class="ring"></i>
      <input type="hidden" name="p" value="buy"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <h3><?= ic('star') ?> استارز به مقدارِ دلخواه</h3>
      <label class="lbl" for="calcQty">چند استارز می‌خواهید؟</label>
      <div class="qrow">
        <button type="button" data-step="50" aria-label="بیشتر">+</button>
        <input id="calcQty" class="inp num" type="number" name="qty" inputmode="numeric" min="<?= (int)$p['qmin'] ?>" max="<?= (int)$p['qmax'] ?>" value="<?= $v ?>">
        <button type="button" data-step="-50" aria-label="کمتر">−</button>
      </div>
      <input type="range" min="<?= (int)$p['qmin'] ?>" max="10000" step="50" value="<?= $v ?>" data-range aria-label="تعداد">
      <div class="chips"><?php foreach ([50, 100, 250, 500, 1000, 5000] as $q): if ($q < (int)$p['qmin']) continue; ?><button type="button" class="chip" data-q="<?= $q ?>"><?= fa($q) ?></button><?php endforeach; ?></div>
      <div class="calc-total"><span class="muted">مبلغ</span><b><span data-total>—</span> <small>تومان</small></b><span class="muted sm">هر استارز <?= toman($p['price'] / max(1, (int)$p['per'])) ?></span></div>
      <button class="btn b-gold b-lg b-block" type="submit"><?= ic('bolt') ?> ادامه‌ی خرید</button>
    </form>
    <?php return ob_get_clean();
}
