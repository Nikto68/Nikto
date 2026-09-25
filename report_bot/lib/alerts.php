<?php

function reportLog(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    @file_put_contents(REPORT_DATA_DIR . '/report.log', $line, FILE_APPEND | LOCK_EX);
}

function reportAdminAlertOnce(string $key, string $text, int $throttleSeconds = 1800): void {
    $db = reportDb();
    $stmt = $db->prepare('SELECT last_sent FROM alert_throttle WHERE k = :k');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row && (time() - (int)$row['last_sent']) < $throttleSeconds) return;

    foreach (reportAdminIds() as $aid) {
        tgSendMessage($aid, $text);
    }

    $stmt = $db->prepare('INSERT INTO alert_throttle (k, last_sent) VALUES (:k, :t)
        ON CONFLICT(k) DO UPDATE SET last_sent = excluded.last_sent');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
}
