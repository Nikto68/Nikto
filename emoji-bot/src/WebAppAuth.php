<?php
declare(strict_types=1);

namespace EmojiBot;

/**
 * Validates Telegram Mini App initData.
 *
 * secret_key = HMAC_SHA256(key="WebAppData", msg=bot_token)
 * hash       = hex(HMAC_SHA256(key=secret_key, msg=data_check_string))
 * data_check_string = every received field except "hash", sorted by key, "key=value" joined by "\n".
 */
final class WebAppAuth
{
    /**
     * @return array{user: array, auth_date: int, start_param: ?string, query_id: ?string}|null
     */
    public static function validate(string $initData, string $botToken, int $maxAge = 86400): ?array
    {
        if ($initData === '' || strlen($initData) > 8192) {
            return null;
        }
        $fields = [];
        foreach (explode('&', $initData) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2) {
                return null;
            }
            $key = urldecode($parts[0]);
            if (isset($fields[$key])) {
                return null; // duplicate keys are never produced by Telegram
            }
            $fields[$key] = urldecode($parts[1]);
        }
        $hash = $fields['hash'] ?? '';
        unset($fields['hash']);
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        ksort($fields, SORT_STRING);
        $lines = [];
        foreach ($fields as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', implode("\n", $lines), $secret);
        if (!hash_equals($expected, $hash)) {
            return null;
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);
        $now = time();
        if ($authDate <= 0 || $authDate > $now + 300 || ($maxAge > 0 && $now - $authDate > $maxAge)) {
            return null;
        }
        $user = json_decode($fields['user'] ?? '', true);
        if (!is_array($user) || !isset($user['id']) || !is_int($user['id']) || $user['id'] <= 0) {
            return null;
        }
        return [
            'user' => $user,
            'auth_date' => $authDate,
            'start_param' => $fields['start_param'] ?? null,
            'query_id' => $fields['query_id'] ?? null,
        ];
    }

    /** Builds a signed initData string. Used only by tests and local tooling. */
    public static function sign(array $fields, string $botToken): string
    {
        ksort($fields, SORT_STRING);
        $lines = [];
        foreach ($fields as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', implode("\n", $lines), $secret);
        return http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }
}
