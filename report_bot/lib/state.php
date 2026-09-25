<?php

function stateGet(int $userId): array {
    $stmt = reportDb()->prepare('SELECT state, data FROM user_state WHERE user_id = :u');
    $stmt->bindValue(':u', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return ['state' => null, 'data' => []];
    return ['state' => $row['state'], 'data' => json_decode($row['data'] ?: '[]', true) ?: []];
}

function stateSet(int $userId, ?string $state, array $data = []): void {
    $stmt = reportDb()->prepare('INSERT INTO user_state (user_id, state, data, updated_at) VALUES (:u, :s, :d, :t)
        ON CONFLICT(user_id) DO UPDATE SET state = excluded.state, data = excluded.data, updated_at = excluded.updated_at');
    $stmt->bindValue(':u', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':s', $state, SQLITE3_TEXT);
    $stmt->bindValue(':d', json_encode($data, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function stateClear(int $userId): void {
    stateSet($userId, null, []);
}

function stateTake(int $userId, string $state): bool {
    $db = reportDb();
    $stmt = $db->prepare("UPDATE user_state SET state = NULL, data = '[]', updated_at = :t WHERE user_id = :u AND state = :s");
    $stmt->bindValue(':u', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':s', $state, SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
    return $db->changes() === 1;
}
