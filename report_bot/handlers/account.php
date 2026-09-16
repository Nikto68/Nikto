<?php

function handleAccountView(array $cq): void {
    $uid = (int)$cq['from']['id'];
    userTouch($cq['from']);
    $u = userGet($uid);
    $c = submissionCountsFor($uid);

    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '') $name = '—';
    $username = !empty($u['username']) ? '@' . $u['username'] : '—';
    $joined = !empty($u['joined_at']) ? date('Y-m-d', (int)$u['joined_at']) : '—';

    $text = "👤 حساب کاربری\n\n" .
        "نام: $name\n" .
        "یوزرنیم: $username\n" .
        "شناسه‌ی عددی: $uid\n" .
        "تاریخ عضویت: $joined\n\n" .
        "گزارش‌ها — کل: {$c['total']} · تایید‌شده: {$c['approved']} · رد‌شده: {$c['rejected']} · در انتظار: {$c['pending']}";

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $text, [], kbBack());
    tgAnswerCallback($cq['id']);
}
