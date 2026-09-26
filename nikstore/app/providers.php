<?php
function provider_effective($p) {
    $prov = (string)($p['provider'] ?? 'manual');
    $ref  = trim((string)($p['provider_ref'] ?? ''));
    if ($prov === 'smm' && $ref !== '' && trim((string)setting('smm_url')) !== '' && trim((string)setting('smm_key')) !== '') return 'smm';
    if ($prov === '5sim' && $ref !== '' && trim((string)setting('fivesim_key')) !== '') return '5sim';
    return 'manual';
}

function provider_state($o) {
    $s = json_decode((string)($o['provider_state'] ?? ''), true);
    return is_array($s) ? $s : [];
}

function provider_dispatch($o) {
    if ($o['kind'] !== 'product' || $o['status'] !== 'paid') return;
    $p = product($o['product_id'], false);
    if (!$p) return;
    $mode = provider_effective($p);
    if ($mode === 'smm') {
        [$ok, $res] = smm_call(['action' => 'add', 'service' => $p['provider_ref'], 'link' => $o['target'], 'quantity' => (int)$o['qty']]);
        if ($ok && !empty($res['order'])) {
            order_set($o['id'], ['status' => 'processing', 'provider' => 'smm', 'provider_id' => (string)$res['order'],
                                 'provider_state' => json_encode(['at' => time()]), 'delivery' => 'سفارش ثبت شد و در حال انجام است.']);
        } else {
            order_set($o['id'], ['provider' => 'smm', 'admin_note' => trim($o['admin_note'] . "\nخطای ثبتِ خودکار: " . (is_string($res) ? $res : ($res['error'] ?? 'نامشخص')))]);
        }
        return;
    }
    if ($mode === '5sim') {
        [$ok, $res] = fivesim_call('/user/buy/activation/' . implode('/', array_map('rawurlencode', explode('/', $p['provider_ref']))));
        if ($ok && !empty($res['id']) && !empty($res['phone'])) {
            order_set($o['id'], ['status' => 'processing', 'provider' => '5sim', 'provider_id' => (string)$res['id'],
                                 'provider_state' => json_encode(['phone' => $res['phone'], 'status' => $res['status'] ?? 'PENDING', 'code' => '', 'at' => time(), 'bought' => time()]),
                                 'delivery' => 'شماره: ' . $res['phone']]);
        } else {
            $why = is_string($res) ? $res : 'شماره‌ای در دسترس نبود';
            $o = order_set($o['id'], ['status' => 'failed', 'provider' => '5sim', 'admin_note' => trim($o['admin_note'] . "\nخرید شماره ناموفق: " . $why),
                                      'delivery' => 'در حالِ حاضر شماره‌ای برای این کشور موجود نیست.']);
            if (order_refund_wallet($o, 'شماره موجود نبود')) order_set($o['id'], ['delivery' => 'شماره‌ای موجود نبود؛ مبلغ به کیف پول شما برگشت.']);
        }
        return;
    }
    order_set($o['id'], ['provider' => 'manual']);
}

function provider_refresh($o, $force = false) {
    if ($o['status'] !== 'processing') return $o;
    $st = provider_state($o);
    $gap = $o['provider'] === '5sim' ? 5 : 60;
    if (!$force && time() - (int)($st['at'] ?? 0) < $gap) return $o;
    $st['at'] = time();

    if ($o['provider'] === 'smm' && $o['provider_id'] !== '') {
        [$ok, $res] = smm_call(['action' => 'status', 'order' => $o['provider_id']]);
        if (!$ok) return order_set($o['id'], ['provider_state' => json_encode($st)]);
        $s = strtolower((string)($res['status'] ?? ''));
        $st['remains'] = $res['remains'] ?? null;
        if ($s === 'completed') return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st), 'delivery' => 'سفارش کامل انجام شد.']);
        if ($s === 'partial') return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st),
            'delivery' => 'سفارش بخشی انجام شد' . ($st['remains'] ? ' (' . fa((int)$st['remains']) . ' عدد باقی ماند)' : '') . '.',
            'admin_note' => trim($o['admin_note'] . "\nانجامِ ناقص — باقی‌مانده: " . (int)$st['remains'])]);
        if ($s === 'canceled' || $s === 'cancelled') {
            $o = order_set($o['id'], ['status' => 'failed', 'provider_state' => json_encode($st), 'delivery' => 'سفارش توسطِ سرویس‌دهنده لغو شد.']);
            if (order_refund_wallet($o, 'لغو توسط سرویس‌دهنده')) $o = order_set($o['id'], ['delivery' => 'سفارش انجام نشد؛ مبلغ به کیف پول شما برگشت.']);
            return $o;
        }
        return order_set($o['id'], ['provider_state' => json_encode($st)]);
    }

    if ($o['provider'] === '5sim' && $o['provider_id'] !== '') {
        [$ok, $res] = fivesim_call('/user/check/' . rawurlencode($o['provider_id']));
        if (!$ok) return order_set($o['id'], ['provider_state' => json_encode($st)]);
        $s = strtoupper((string)($res['status'] ?? ''));
        $st['status'] = $s;
        $code = '';
        foreach ((array)($res['sms'] ?? []) as $sms) if (!empty($sms['code'])) $code = (string)$sms['code'];
        if ($code !== '') {
            $st['code'] = $code;
            fivesim_call('/user/finish/' . rawurlencode($o['provider_id']));
            return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st),
                                        'delivery' => 'شماره: ' . ($st['phone'] ?? '') . "\nکد تایید: " . $code]);
        }
        if (in_array($s, ['CANCELED', 'TIMEOUT', 'BANNED'], true)) {
            $o = order_set($o['id'], ['status' => 'failed', 'provider_state' => json_encode($st), 'delivery' => 'کدی دریافت نشد و شماره منقضی شد.']);
            if (order_refund_wallet($o, 'کد دریافت نشد')) $o = order_set($o['id'], ['delivery' => 'کدی دریافت نشد؛ مبلغ به کیف پول شما برگشت.']);
            return $o;
        }
        return order_set($o['id'], ['provider_state' => json_encode($st)]);
    }
    return $o;
}

function number_cancel($o) {
    if ($o['provider'] !== '5sim' || $o['status'] !== 'processing') return [false, 'این شماره قابلِ لغو نیست.'];
    $st = provider_state($o);
    if (!empty($st['code'])) return [false, 'کد دریافت شده؛ لغو ممکن نیست.'];
    if (time() - (int)($st['bought'] ?? 0) < 120) return [false, 'دو دقیقه بعد از خرید می‌توانید لغو کنید.'];
    [$ok, $res] = fivesim_call('/user/cancel/' . rawurlencode($o['provider_id']));
    if (!$ok) return [false, 'لغو ممکن نشد: ' . (is_string($res) ? $res : 'خطای سرویس‌دهنده')];
    $o = order_set($o['id'], ['status' => 'failed', 'delivery' => 'شماره لغو شد.']);
    if (order_refund_wallet($o, 'لغو شماره')) order_set($o['id'], ['delivery' => 'شماره لغو شد؛ مبلغ به کیف پول شما برگشت.']);
    return [true, ''];
}

function smm_call(array $params) {
    $url = trim((string)setting('smm_url'));
    [$code, $res, $err] = http_req('POST', $url, ['key' => trim((string)setting('smm_key'))] + $params);
    if ($err !== '') return [false, $err];
    $j = json_decode($res, true);
    if (!is_array($j)) return [false, 'پاسخِ نامعتبر از پنل'];
    if (!empty($j['error'])) return [false, (string)$j['error']];
    return [true, $j];
}

function fivesim_call($path) {
    [$code, $res, $err] = http_req('GET', 'https://5sim.net/v1' . $path, null,
                                   ['Authorization: Bearer ' . trim((string)setting('fivesim_key')), 'Accept: application/json']);
    if ($err !== '') return [false, $err];
    $j = json_decode($res, true);
    if ($code >= 400 || !is_array($j)) return [false, trim(mb_substr(strip_tags($res), 0, 120)) ?: ('کد ' . $code)];
    return [true, $j];
}
