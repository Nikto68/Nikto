<?php
function reportDb(): SQLite3 {
    static $db = null;
    if ($db !== null) return $db;

    if (!is_dir(REPORT_DATA_DIR)) mkdir(REPORT_DATA_DIR, 0775, true);
    $db = new SQLite3(REPORT_DATA_DIR . '/report.sqlite3');
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    reportEnsureSchema($db);
    return $db;
}

function reportEnsureSchema(SQLite3 $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        k TEXT PRIMARY KEY,
        v TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        text TEXT NOT NULL,
        entities TEXT NOT NULL DEFAULT '[]',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS premium_emoji (
        slot TEXT PRIMARY KEY,
        custom_emoji_id TEXT NOT NULL,
        placeholder TEXT NOT NULL,
        updated_at INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY,
        username TEXT,
        first_name TEXT,
        last_name TEXT,
        joined_at INTEGER NOT NULL,
        last_seen_at INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        username TEXT,
        first_name TEXT,
        src_chat_id INTEGER NOT NULL,
        src_message_id INTEGER NOT NULL,
        media_type TEXT NOT NULL,
        orig_caption TEXT,
        orig_caption_entities TEXT NOT NULL DEFAULT '[]',
        tag_id INTEGER,
        status TEXT NOT NULL DEFAULT 'pending',
        group_chat_id INTEGER,
        group_message_id INTEGER,
        channel_chat_id INTEGER,
        channel_message_id INTEGER,
        notify_chat_id INTEGER,
        notify_message_id INTEGER,
        notify_has_photo INTEGER NOT NULL DEFAULT 0,
        decided_by INTEGER,
        decided_at INTEGER,
        created_at INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_state (
        user_id INTEGER PRIMARY KEY,
        state TEXT,
        data TEXT,
        updated_at INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS support_threads (
        admin_chat_id INTEGER NOT NULL,
        admin_message_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY (admin_chat_id, admin_message_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS alert_throttle (
        k TEXT PRIMARY KEY,
        last_sent INTEGER NOT NULL
    )");
}

function settingGet(string $key, $default = null) {
    $stmt = reportDb()->prepare('SELECT v FROM settings WHERE k = :k');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['v'] : $default;
}

function settingSet(string $key, $value): void {
    $stmt = reportDb()->prepare('INSERT INTO settings (k, v) VALUES (:k, :v)
        ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', (string)$value, SQLITE3_TEXT);
    $stmt->execute();
}

function settingDel(string $key): void {
    $stmt = reportDb()->prepare('DELETE FROM settings WHERE k = :k');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->execute();
}

function tagGetAll(): array {
    $res = reportDb()->query('SELECT id, text, entities FROM tags ORDER BY sort_order ASC, id ASC');
    $out = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $row['entities'] = json_decode($row['entities'], true) ?: [];
        $out[] = $row;
    }
    return $out;
}

function tagGet(int $id): ?array {
    $stmt = reportDb()->prepare('SELECT id, text, entities FROM tags WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $row['entities'] = json_decode($row['entities'], true) ?: [];
    return $row;
}

function submissionGet(int $id): ?array {
    $stmt = reportDb()->prepare('SELECT * FROM submissions WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function submissionFindByGroupMessage(int $groupChatId, int $groupMessageId): ?array {
    $stmt = reportDb()->prepare('SELECT * FROM submissions WHERE group_chat_id = :c AND group_message_id = :m');
    $stmt->bindValue(':c', $groupChatId, SQLITE3_INTEGER);
    $stmt->bindValue(':m', $groupMessageId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function submissionUpdate(int $id, array $fields): void {
    if (!$fields) return;
    $sets = [];
    foreach (array_keys($fields) as $k) $sets[] = "$k = :$k";

    $stmt = reportDb()->prepare('UPDATE submissions SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    foreach ($fields as $k => $v) {
        $type = is_int($v) ? SQLITE3_INTEGER : (is_null($v) ? SQLITE3_NULL : SQLITE3_TEXT);
        $stmt->bindValue(":$k", $v, $type);
    }
    $stmt->execute();
}

function userTouch(array $from): void {
    $uid = (int)($from['id'] ?? 0);
    if ($uid <= 0) return;
    $stmt = reportDb()->prepare('INSERT INTO users (user_id, username, first_name, last_name, joined_at, last_seen_at)
        VALUES (:u, :un, :fn, :ln, :t, :t)
        ON CONFLICT(user_id) DO UPDATE SET username=excluded.username, first_name=excluded.first_name, last_name=excluded.last_name, last_seen_at=excluded.last_seen_at');
    $stmt->bindValue(':u', $uid, SQLITE3_INTEGER);
    $stmt->bindValue(':un', $from['username'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':fn', $from['first_name'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':ln', $from['last_name'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function userGet(int $uid): ?array {
    $stmt = reportDb()->prepare('SELECT * FROM users WHERE user_id = :u');
    $stmt->bindValue(':u', $uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function submissionCountsFor(int $uid): array {
    $stmt = reportDb()->prepare('SELECT status, COUNT(*) c FROM submissions WHERE user_id = :u GROUP BY status');
    $stmt->bindValue(':u', $uid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $out = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $out[$row['status']] = (int)$row['c'];
        $out['total'] += (int)$row['c'];
    }
    return $out;
}
