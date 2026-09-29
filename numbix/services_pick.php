<?php
// گلچینِ محصولاتِ دو مینی‌اپِ خدمات: به‌جای صدها سرویسِ پنل، در هر دسته چند محصولِ روشن
// (کف قیمت / متوسط / ویژه با ضمانت / فیک / ایرانی / روسی / …) که هرکدام خودکار بهترین سرویسِ پنل را برمی‌دارد.
// ادمین در پنلِ وب می‌تواند برای هر محصول سرویسِ دیگری بگذارد، اسم و قیمتش را عوض کند یا خاموشش کند.

function spOn() {
    return !empty(svCfg()['pick'] ?? 1);
}

function spCats($app) {
    if ($app === 'ig') return [
        'followers' => ['فالوور',          'users'],
        'likes'     => ['لایک',            'heart'],
        'comments'  => ['کامنت',           'chat'],
        'views'     => ['ویو و ریلز',       'play'],
        'story'     => ['بازدید استوری',    'ring'],
        'saves'     => ['سیو و اشتراک',     'bookmark'],
    ];
    return [
        'members'   => ['ممبر کانال و گروه', 'users'],
        'story'     => ['سین استوری',        'camera'],
        'views'     => ['بازدید پست',         'eye'],
        'reactions' => ['ری‌اکشن',            'heart'],
        'premium'   => ['پریمیوم و بوست',     'star'],
        'votes'     => ['رای نظرسنجی',        'chart'],
        'comments'  => ['کامنت',              'chat'],
    ];
}

// الگوها روی نامِ انگلیسیِ پنل + دسته‌ی پنل (کوچک‌شده)
function spRx($k) {
    static $rx = [
        'geo'   => '/\b(iran|persian|russia|russian|rus|usa|us|america|arab|arabic|saudi|uae|egypt|turk|turkey|turkish|india|indian|brazil|brazilian|china|chinese|japan|korea|korean|indonesia|vietnam|thai|thailand|pakistan|europe|european|asia|asian|africa|latin|latam|mexico|spain|spanish|italy|italian|germany|german|france|french|uk|british|azer|uzbek|kazakh|ukrain\w*|targeted|country|countries|gender|female|women|male|men|girls?)\b|🇮🇷|🇷🇺|🇺🇸|🇨🇳|🇮🇳|🇧🇷|🇹🇷/u',
        'odd'   => '/custom|mention|\bauto\b|subscription|drip|package|broadcast|\bpoll\b|\bvotes?\b/u',
        'fake'  => '/fake|\bbots?\b|cheap(est)?|low\s*quality|\blq\b|no\s*refill|non[\s\-]*refill|without\s*refill|not\s*real|فیک/u',
        'iran'  => '/\biran(ian)?\b|persian|ایرانی|ایران|🇮🇷/u',
        'ru'    => '/\brussia(n)?\b|\brus\b|\bru\b|روسی|🇷🇺/u',
        'story' => '/stor(y|ies)|استوری/u',
        'multi' => '/\bauto\b|future|next\s*\d+|last\s*\d+|\d+\s*posts|subscription/u',
        'fast'  => '/fast|instant|speed|super|quick|\bhq\b|high\s*quality|real|premium/u',
        'slow'  => '/slow|gradual|drip|cheap(est)?|economy|low\s*speed/u',
        'group' => '/group|گروه/u',
        'chan'  => '/channel|کانال/u',
        'boost' => '/boost|بوست/u',
        'nore'  => '/no\s*refill|non[\s\-]*refill|without\s*refill|refill\s*[:\-]?\s*(no\b|none|❌)/u',
    ];
    return $rx[$k];
}

function spText(array $s) {
    return mb_strtolower((string)$s['pname'] . ' | ' . (string)$s['pcat']);
}

// روزهای ضمانت از روی نامِ سرویس (۰ = ضمانت ندارد، ۳۶۵۰ = همیشگی)
function spRefillDays(array $s) {
    $t = spText($s);
    if (preg_match(spRx('nore'), $t)) return 0;
    if (preg_match('/(?:refill|guarantee(?:d)?)\s*[:\-]?\s*(\d{1,4})\s*(d|days?)\b/u', $t, $m)
        || preg_match('/(\d{1,4})\s*(d|days?)\s*(?:refill|guarantee)/u', $t, $m)
        || preg_match('/\br(\d{2,3})\b/u', $t, $m)) return max(1, (int)$m[1]);
    if (preg_match('/lifetime|life\s*time/u', $t)) return 3650;
    if (!empty($s['refill']) || str_contains($t, '♻') || preg_match('/\brefill\b|guarantee/u', $t)) return 30;
    return 0;
}

function spPoolAll($app) {
    static $memo = [];
    if (isset($memo[$app])) return $memo[$app];
    $out = [];
    foreach (svServices($app, true) as $s) {
        if (!svTypeOk($s['type'])) continue;
        $s['_t'] = spText($s);
        $s['_d'] = spRefillDays($s);
        $s['_p'] = svPrice1k($s);
        if ($s['_p'] <= 0) continue;
        $out[] = $s;
    }
    usort($out, fn($x, $y) => [$x['rate'], $x['_p']] <=> [$y['rate'], $y['_p']]);
    return $memo[$app] = $out;
}

// مجموعه‌ی سرویس‌هایی که یک محصول از میانشان انتخاب می‌کند
function spPool($app, array $d) {
    $src = $d['src'];
    $L = [];
    foreach (spPoolAll($app) as $s) {
        if ($s['cat'] !== $src) continue;
        $t = $s['_t'];
        if (!empty($d['inc']) && !preg_match($d['inc'], $t)) continue;
        if (!empty($d['exc']) && preg_match($d['exc'], $t)) continue;
        if (!empty($d['story']) && !preg_match(spRx('story'), $t)) continue;
        if (!empty($d['nostory']) && preg_match(spRx('story'), $t)) continue;
        if (empty($d['geo']) && preg_match(spRx('geo'), $t)) continue;
        if (!empty($d['geo']) && !preg_match(spRx($d['geo']), $t)) continue;
        if (preg_match(spRx('odd'), $t) && empty($d['odd'])) continue;
        if ($src === 'views' && empty($d['multi']) && preg_match(spRx('multi'), $t)) continue;
        $L[] = $s;
    }
    // ترجیح به سرویس‌هایی که برای خریدِ معمولی مناسب‌اند (حداقل ≤ ۱۰۰۰ و حداکثر ≥ ۱۰۰۰)
    $ok = array_values(array_filter($L, fn($s) => $s['min'] <= 1000 && $s['max'] >= min(1000, max(1, $s['min']))));
    return $ok ?: $L;
}

// فهرستِ گزینه‌ها به ترتیبِ ترجیح: اول نقطه‌ی هدف (مثلا میانه)، بعد نزدیک‌ترین‌ها — تا اگر سرویسی را محصولِ دیگری
// برداشته بود، محصول بعدی را بردارد
function spAround(array $L, $q) {
    $n = count($L);
    if (!$n) return [];
    $c = (int)round(($n - 1) * $q);
    $out = [$L[$c]];
    for ($k = 1; $k < $n; $k++) {
        if ($c + $k < $n) $out[] = $L[$c + $k];
        if ($c - $k >= 0) $out[] = $L[$c - $k];
    }
    return $out;
}

function spCands(array $L, $tier, array $d = []) {
    if (!$L) return [];
    $fake = fn($s) => (bool)preg_match(spRx('fake'), $s['_t']);
    switch ($tier) {
        case 'cheap':
            if (!empty($d['fakefirst'])) {
                $F = array_values(array_filter($L, $fake));
                return array_merge($F, array_values(array_filter($L, fn($s) => !preg_match(spRx('fake'), $s['_t']))));
            }
            return $L;
        case 'mid':  return spAround($L, 0.5);
        case 'high':
            $R = array_values(array_filter($L, fn($s) => $s['_d'] > 0));
            if ($R) return array_merge(spAround($R, 0.7), array_reverse(array_values(array_filter($L, fn($s) => $s['_d'] <= 0))));
            return spAround($L, 0.85);
        case 'fake':
            return array_values(array_filter($L, $fake));
        case 'fast':
            $F = array_values(array_filter($L, fn($s) => preg_match(spRx('fast'), $s['_t']) && !preg_match(spRx('slow'), $s['_t'])));
            return $F ? array_merge(spAround($F, 0.3), array_reverse($L)) : spAround($L, 0.7);
        case 'slow':
            $S = array_values(array_filter($L, fn($s) => preg_match(spRx('slow'), $s['_t'])));
            return array_merge($S, $L);
    }
    return $L;
}

// تعریفِ محصولاتِ گلچین
function spDefs($app) {
    $x = [];
    $add = function ($id, $dc, $src, $tier, $title, $badge, $tone, array $o = []) use (&$x) {
        $x[$id] = ['id' => $id, 'dc' => $dc, 'src' => $src, 'tier' => $tier, 'title' => $title, 'badge' => $badge, 'tone' => $tone] + $o;
    };
    if ($app === 'ig') {
        $add('ig.fol.cheap', 'followers', 'followers', 'cheap', 'فالوور ارزان',            'کف قیمت',      'cheap');
        $add('ig.fol.mid',   'followers', 'followers', 'mid',   'فالوور متوسط',            'قیمت متوسط',   'mid');
        $add('ig.fol.high',  'followers', 'followers', 'high',  'فالوور ویژه با ضمانت',    'ضمانت‌دار',     'high');
        $add('ig.fol.iran',  'followers', 'followers', 'cheap', 'فالوور ایرانی',           '🇮🇷 ایرانی',    'iran', ['geo' => 'iran']);
        $add('ig.fol.ru',    'followers', 'followers', 'cheap', 'فالوور روسی',             '🇷🇺 روسی',      'ru',   ['geo' => 'ru']);
        $add('ig.fol.fake',  'followers', 'followers', 'fake',  'فالوور فیک ارزان',        'فیک',          'fake');
        $lx = '/comments?\s*likes?|stor(y|ies)|\blive\b/u';
        $add('ig.like.cheap', 'likes', 'likes', 'cheap', 'لایک ارزان (فیک)',        'کف قیمت',    'cheap', ['exc' => $lx, 'fakefirst' => 1]);
        $add('ig.like.mid',   'likes', 'likes', 'mid',   'لایک متوسط',              'قیمت متوسط', 'mid',   ['exc' => $lx]);
        $add('ig.like.high',  'likes', 'likes', 'high',  'لایک ویژه با ضمانت',      'ضمانت‌دار',   'high',  ['exc' => $lx]);
        $cx = '/\blive\b|like|mention/u';
        $add('ig.cm.cheap', 'comments', 'comments', 'cheap', 'کامنت ارزان',          'کف قیمت',    'cheap', ['exc' => $cx, 'fakefirst' => 1, 'odd' => 1]);
        $add('ig.cm.mid',   'comments', 'comments', 'mid',   'کامنت متوسط',          'قیمت متوسط', 'mid',   ['exc' => $cx, 'odd' => 1]);
        $add('ig.cm.high',  'comments', 'comments', 'high',  'کامنت ویژه با ضمانت',  'ضمانت‌دار',   'high',  ['exc' => $cx, 'odd' => 1]);
        $vx = '/impression|reach|visit|profile|\blive\b/u';
        $add('ig.view.cheap', 'views', 'views', 'cheap', 'ویو ارزان',        'کف قیمت',    'cheap', ['exc' => $vx]);
        $add('ig.view.mid',   'views', 'views', 'mid',   'ویو متوسط',        'قیمت متوسط', 'mid',   ['exc' => $vx]);
        $add('ig.view.high',  'views', 'views', 'high',  'ویو ویژه',         'باکیفیت',    'high',  ['exc' => $vx]);
        $add('ig.view.fake',  'views', 'views', 'fake',  'ویو فیک ارزان',    'فیک',        'fake',  ['exc' => $vx]);
        $sx = '/poll|vote|like|link|sticker|comment/u';
        $add('ig.st.cheap', 'story', 'story', 'cheap', 'بازدید استوری ارزان', 'کف قیمت',    'cheap', ['exc' => $sx]);
        $add('ig.st.mid',   'story', 'story', 'mid',   'بازدید استوری متوسط', 'قیمت متوسط', 'mid',   ['exc' => $sx]);
        $add('ig.st.high',  'story', 'story', 'high',  'بازدید استوری ویژه',  'باکیفیت',    'high',  ['exc' => $sx]);
        $add('ig.st.fake',  'story', 'story', 'fake',  'بازدید استوری فیک',   'فیک',        'fake',  ['exc' => $sx]);
        $add('ig.sv.cheap', 'saves', 'saves', 'cheap', 'سیو و اشتراک ارزان',  'کف قیمت',    'cheap');
        $add('ig.sv.mid',   'saves', 'saves', 'mid',   'سیو و اشتراک متوسط',  'قیمت متوسط', 'mid');
        $add('ig.sv.high',  'saves', 'saves', 'high',  'سیو و اشتراک ویژه',   'باکیفیت',    'high');
        return $x;
    }
    $add('tg.mem.fake', 'members', 'members', 'fake', 'ممبر فیک کانال و گروه', 'فیک · کف قیمت', 'fake');
    // ممبرهای ضمانت‌دار به تفکیکِ روز در spResolve ساخته می‌شوند (tg.mem.ref.N)
    $add('tg.mem.iran',   'members', 'members', 'cheap', 'ممبر ایرانی',            '🇮🇷 کف قیمت',   'iran', ['geo' => 'iran']);
    $add('tg.mem.ru.cheap', 'members', 'members', 'cheap', 'ممبر روسی',            '🇷🇺 کف قیمت',   'ru',   ['geo' => 'ru']);
    $add('tg.mem.ru.mid',   'members', 'members', 'mid',   'ممبر روسی',            '🇷🇺 قیمت متوسط', 'ru',  ['geo' => 'ru']);
    $gx = spRx('group');
    $add('tg.ch.cheap', 'members', 'members', 'cheap', 'ممبر کانال فیک',          'کف قیمت',    'cheap', ['exc' => $gx, 'fakefirst' => 1]);
    $add('tg.ch.mid',   'members', 'members', 'mid',   'ممبر کانال',              'قیمت متوسط', 'mid',   ['exc' => $gx]);
    $add('tg.ch.high',  'members', 'members', 'high',  'ممبر کانال ویژه',         'قیمت بالا · ضمانت‌دار', 'high', ['exc' => $gx]);
    $add('tg.gr.cheap', 'members', 'members', 'cheap', 'ممبر گروه',               'کف قیمت',    'cheap', ['inc' => $gx]);
    $add('tg.gr.mid',   'members', 'members', 'mid',   'ممبر گروه',               'قیمت متوسط', 'mid',   ['inc' => $gx]);
    $add('tg.gr.high',  'members', 'members', 'high',  'ممبر گروه ویژه',          'قیمت بالا · ضمانت‌دار', 'high', ['inc' => $gx]);
    $add('tg.st.cheap', 'story', 'views', 'cheap', 'سین استوری ارزان',  'کف قیمت',    'cheap', ['story' => 1]);
    $add('tg.st.mid',   'story', 'views', 'mid',   'سین استوری متوسط',  'قیمت متوسط', 'mid',   ['story' => 1]);
    $add('tg.st.high',  'story', 'views', 'high',  'سین استوری ویژه',   'قیمت بالا',  'high',  ['story' => 1]);
    $add('tg.vw.fast',  'views', 'views', 'fast',  'بازدید پرسرعت و باکیفیت', 'سرعت بالا', 'high',  ['nostory' => 1]);
    $add('tg.vw.slow',  'views', 'views', 'slow',  'بازدید کم‌سرعت',          'کف قیمت',   'cheap', ['nostory' => 1]);
    $add('tg.react',    'reactions', 'reactions', 'emoji', 'ری‌اکشن دلخواه', 'هر ایموجی', 'mid');
    $bx = spRx('boost');
    $add('tg.pr.cheap', 'premium', 'premium', 'cheap', 'ممبر پریمیوم ارزان', 'کف قیمت',    'cheap', ['exc' => $bx]);
    $add('tg.pr.mid',   'premium', 'premium', 'mid',   'ممبر پریمیوم متوسط', 'قیمت متوسط', 'mid',   ['exc' => $bx]);
    $add('tg.pr.high',  'premium', 'premium', 'high',  'ممبر پریمیوم ویژه',  'قیمت بالا',  'high',  ['exc' => $bx]);
    $add('tg.bs.cheap', 'premium', 'premium', 'cheap', 'بوست کانال',         'کف قیمت',    'cheap', ['inc' => $bx]);
    $add('tg.bs.mid',   'premium', 'premium', 'mid',   'بوست کانال',         'قیمت متوسط', 'mid',   ['inc' => $bx]);
    $add('tg.vo.cheap', 'votes', 'votes', 'cheap', 'رای نظرسنجی ارزان',  'کف قیمت',    'cheap', ['odd' => 1]);
    $add('tg.vo.mid',   'votes', 'votes', 'mid',   'رای نظرسنجی متوسط',  'قیمت متوسط', 'mid',   ['odd' => 1]);
    $add('tg.vo.high',  'votes', 'votes', 'high',  'رای نظرسنجی ویژه',   'قیمت بالا',  'high',  ['odd' => 1]);
    $add('tg.cm.cheap', 'comments', 'comments', 'cheap', 'کامنت ارزان',  'کف قیمت',    'cheap', ['odd' => 1]);
    $add('tg.cm.mid',   'comments', 'comments', 'mid',   'کامنت متوسط',  'قیمت متوسط', 'mid',   ['odd' => 1]);
    $add('tg.cm.high',  'comments', 'comments', 'high',  'کامنت ویژه',   'قیمت بالا',  'high',  ['odd' => 1]);
    return $x;
}

function spSlotCfg($id) {
    $c = svCfg()['slots'][$id] ?? null;
    return is_array($c) ? $c : [];
}

// ایموجی: بدونِ variation selector و رنگِ پوست
function spEmojiNorm($e) {
    return preg_replace('/[\x{FE0E}\x{FE0F}\x{1F3FB}-\x{1F3FF}]/u', '', trim((string)$e));
}

function spEmojis($txt) {
    if (!preg_match_all('/(?:[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{203C}\x{2049}\x{3030}\x{303D}\x{3297}\x{3299}\x{2194}-\x{21AA}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{00A9}\x{00AE}\x{2122}\x{2139}\x{231A}-\x{23FF}\x{24C2}\x{25AA}-\x{25FE}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]|\x{200D}[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]\x{FE0F}?)*)/u', (string)$txt, $m)) return [];
    $out = [];
    foreach ($m[0] as $e) {
        $n = spEmojiNorm($e);
        if ($n !== '') $out[$n] = $e;
    }
    return $out;
}

// نقشه‌ی «ایموجی ← ارزان‌ترین سرویسِ همان ایموجی» از سرویس‌های ری‌اکشنی که فقط یک ایموجی در نامشان است
function spEmojiMap() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $deco = ['♻', '⚡', '✅', '❌', '🆕', '💯', '🚀', '⭐', '🌟', '✨', '🔝', '💎', '🇮🇷', '🇷🇺'];
    foreach (spPoolAll('tg') as $s) {
        if ($s['cat'] !== 'reactions') continue;
        $em = spEmojis($s['pname']);
        foreach ($deco as $d) unset($em[$d]);
        foreach (array_keys($em) as $k) if (preg_match('/^[\x{1F1E6}-\x{1F1FF}]/u', $k)) unset($em[$k]);
        if (count($em) !== 1) continue;
        $k = (string)array_key_first($em);
        if (!isset($map[$k]) || $s['_p'] < $map[$k]['_p']) $map[$k] = $s + ['_e' => $em[$k]];
    }
    uasort($map, fn($a, $b) => $a['_p'] <=> $b['_p']);
    return $map;
}

// همه‌ی تعریف‌ها به ترتیبِ نمایش، با ممبرهای ضمانت‌دارِ تلگرام به تفکیکِ روز (بعد از «ممبر فیک»)
function spAllDefs($app) {
    static $memo = [];
    if (isset($memo[$app])) return $memo[$app];
    $defs = spDefs($app);
    if ($app !== 'tg') return $memo[$app] = $defs;
    $days = [];
    foreach (spPool('tg', ['src' => 'members']) as $s) {
        $d = (int)$s['_d'];
        if ($d <= 0) continue;
        if (!isset($days[$d]) || $s['_p'] < $days[$d]['_p']) $days[$d] = $s;
    }
    ksort($days);
    $ref = [];
    foreach (array_slice($days, 0, 6, true) as $d => $s) {
        $id = 'tg.mem.ref.' . $d;
        $lbl = $d >= 3650 ? 'همیشگی' : svFaDigits($d) . ' روزه';
        $ref[$id] = ['id' => $id, 'dc' => 'members', 'src' => 'members', 'tier' => 'ref', 'title' => 'ممبر ضمانت‌دار ' . $lbl,
                     'badge' => 'ضمانت ' . ($d >= 3650 ? 'همیشگی' : svFaDigits($d) . ' روز'), 'tone' => 'ref', '_fixed' => $s];
    }
    $out = [];
    foreach ($defs as $id => $d) {
        $out[$id] = $d;
        if ($id === 'tg.mem.fake') $out += $ref;
    }
    return $memo[$app] = $out;
}

// همه‌ی محصولاتِ گلچین برای یک مینی‌اپ: [defId => [def + service]]
function spResolve($app) {
    static $memo = [];
    if (isset($memo[$app])) return $memo[$app];
    $defs = spAllDefs($app);
    $order = array_keys($defs);
    $all = [];
    foreach (spPoolAll($app) as $s) $all[(string)$s['id']] = $s;
    $used = [];
    $out = [];
    foreach ($order as $id) {
        $d = $defs[$id];
        $cfg = spSlotCfg($id);
        if (!empty($cfg['off'])) continue;
        if ($d['tier'] === 'emoji') {
            $em = spEmojiMap();
            if (!$em) continue;
            $out[$id] = $d + ['svc' => null, 'emap' => $em];
            continue;
        }
        $s = null;
        $force = trim((string)($cfg['sid'] ?? ''));
        if ($force !== '' && isset($all[$force])) $s = $all[$force];
        else {
            // همان سرویس برای دو محصولِ یک دسته نشان داده نمی‌شود؛ گزینه‌ی بعدی برداشته می‌شود
            $cands = isset($d['_fixed']) ? [$d['_fixed']] : spCands(spPool($app, $d), $d['tier'], $d);
            foreach ($cands as $c) if (!isset($used[$d['dc'] . '|' . $c['id']])) { $s = $c; break; }
        }
        if (!$s) continue;
        $used[$d['dc'] . '|' . $s['id']] = 1;
        $out[$id] = $d + ['svc' => $s];
    }
    // در هر گروه (مثلا لایک یا ممبر کانال): کف قیمت ≤ متوسط ≤ ویژه
    $rank = ['cheap' => 0, 'mid' => 1, 'high' => 2];
    $fam = [];
    foreach ($out as $id => $d) {
        if (!isset($rank[$d['tier']]) || trim((string)(spSlotCfg($id)['sid'] ?? '')) !== '') continue;
        $fam[substr($id, 0, strrpos($id, '.')) . '|' . ($d['geo'] ?? '')][] = $id;
    }
    foreach ($fam as $ids) {
        if (count($ids) < 2) continue;
        usort($ids, fn($a, $b) => $rank[$out[$a]['tier']] <=> $rank[$out[$b]['tier']]);
        $svcs = array_map(fn($k) => $out[$k]['svc'], $ids);
        usort($svcs, fn($x, $y) => [$x['rate'], $x['_p']] <=> [$y['rate'], $y['_p']]);
        foreach ($ids as $k => $sid) $out[$sid]['svc'] = $svcs[$k];
    }
    return $memo[$app] = $out;
}

function spFeatures($s) {
    $n = svFaName($s['pname'], $s['pcat'], $s['app'], $s['cat'], $s['refill']);
    $k = mb_strpos($n, ' — ');
    return $k === false ? [] : array_values(array_filter(explode(' · ', mb_substr($n, $k + 3))));
}

function spPublic($app) {
    $cats = spCats($app);
    $items = [];
    $n = $from = [];
    foreach (spResolve($app) as $id => $d) {
        $cfg = spSlotCfg($id);
        $title = trim((string)($cfg['title'] ?? '')) ?: $d['title'];
        if ($d['tier'] === 'emoji') {
            $em = [];
            $min = 0;
            foreach ($d['emap'] as $k => $s) {
                $em[] = ['e' => $s['_e'], 'k' => $k, 'p' => $s['_p'], 'mn' => max(1, $s['min']), 'mx' => max(max(1, $s['min']), $s['max'])];
                if (!$min || $s['_p'] < $min) $min = $s['_p'];
            }
            $it = ['i' => 'k:' . $id, 'c' => $d['dc'], 'n' => $title, 'b' => $d['badge'], 't' => $d['tone'], 'p' => $min,
                   'mn' => min(array_column($em, 'mn')), 'mx' => max(array_column($em, 'mx')), 'r' => 0, 'y' => 'emoji', 'em' => $em,
                   'f' => [implode(' ', array_slice(array_column($em, 'e'), 0, 12)) . (count($em) > 12 ? ' …' : ''),
                           svFaDigits(count($em)) . ' ایموجی — از کیبورد بزنید']];
        } else {
            $s = $d['svc'];
            $p = (float)($cfg['price'] ?? 0) > 0 ? svRound((float)$cfg['price']) : $s['_p'];
            $it = ['i' => 'k:' . $id, 'c' => $d['dc'], 'n' => $title, 'b' => $d['badge'], 't' => $d['tone'], 'p' => $p,
                   'mn' => max(1, $s['min']), 'mx' => max(max(1, $s['min']), $s['max']), 'r' => $s['_d'] > 0 ? 1 : 0,
                   'f' => array_slice(spFeatures($s), 0, 4)];
            $k = svKind($s['type']);
            if ($k === 'poll') $it['y'] = 'poll';
            if ($k === 'cc')   $it['y'] = 'cc';
        }
        if ($it['p'] <= 0) continue;
        $c = $it['c'];
        $n[$c] = ($n[$c] ?? 0) + 1;
        if (!isset($from[$c]) || $it['p'] < $from[$c]) $from[$c] = $it['p'];
        $items[] = $it;
    }
    // قیمت‌ها از کف شروع می‌شوند و بالا می‌روند
    usort($items, fn($a, $b) => $a['p'] <=> $b['p']);
    $outC = [];
    foreach ($cats as $id => [$name, $ic]) {
        if (empty($n[$id])) continue;
        $outC[] = ['id' => $id, 'n' => $name, 'ic' => $ic, 'c' => $n[$id], 'f' => $from[$id]];
    }
    return ['cats' => $outC, 'items' => $items];
}

// برای خرید: محصولِ گلچین ← سرویسِ واقعیِ پنل
function spServiceFor($app, $key, $emoji = '') {
    $id = substr((string)$key, 2);
    $r = spResolve($app);
    if (!isset($r[$id])) return [null, 'این محصول دیگر فعال نیست — صفحه را دوباره باز کنید.', null];
    $d = $r[$id];
    $cfg = spSlotCfg($id);
    $title = trim((string)($cfg['title'] ?? '')) ?: $d['title'];
    if ($d['tier'] === 'emoji') {
        $k = spEmojiNorm($emoji);
        if ($k === '' || !isset($d['emap'][$k]))
            return [null, 'این ایموجی در فهرستِ ری‌اکشن‌ها نیست — یکی از ایموجی‌های موجود را بزنید.', null];
        $s = $d['emap'][$k];
        return [$s, '', ['title' => $title . ' ' . $s['_e'], 'price' => 0]];
    }
    return [$d['svc'], '', ['title' => $title, 'price' => (float)($cfg['price'] ?? 0)]];
}

// برای پنلِ وب: هر محصول، سرویسِ انتخاب‌شده و گزینه‌های جایگزین
function spAdminRows($app) {
    $defs = spAllDefs($app);
    $res = spResolve($app);
    $rows = [];
    foreach ($defs as $id => $d) {
        $cfg = spSlotCfg($id);
        $cand = $d['tier'] === 'emoji' ? [] : (isset($d['_fixed']) ? spPool($app, ['src' => 'members']) : spPool($app, $d));
        $rows[] = ['id' => $id, 'def' => $d, 'cfg' => $cfg, 'pick' => $res[$id] ?? null,
                   'cand' => array_slice($cand, 0, 60)];
    }
    return $rows;
}
