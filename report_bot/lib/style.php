<?php

const REPORT_STYLE_CHOICES = [
    ''         => 'بدون رنگ',
    'primary'  => 'آبی',
    'success'  => 'سبز',
    'danger'   => 'قرمز',
];

function styleGet(string $slot): ?string {
    $stmt = reportDb()->prepare('SELECT style FROM button_style WHERE slot = :s');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['style'] : null;
}

function styleSet(string $slot, string $style): void {
    $stmt = reportDb()->prepare('INSERT INTO button_style (slot, style, updated_at) VALUES (:s, :v, :t)
        ON CONFLICT(slot) DO UPDATE SET style = excluded.style, updated_at = excluded.updated_at');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $stmt->bindValue(':v', $style, SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function styleClear(string $slot): void {
    $stmt = reportDb()->prepare('DELETE FROM button_style WHERE slot = :s');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $stmt->execute();
}
