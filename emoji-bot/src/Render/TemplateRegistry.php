<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use EmojiBot\Db;
use EmojiBot\Settings;

/**
 * All templates available to users: admin-uploaded ones first, then built-ins.
 */
final class TemplateRegistry
{
    /** @var array<string, Template>|null */
    private ?array $all = null;

    public function __construct(
        private readonly Db $db,
        private readonly Settings $settings,
        private readonly string $dir,
        private readonly bool $videoAvailable,
    ) {
    }

    /** @return string[] ids of disabled built-ins */
    public function disabledBuiltins(): array
    {
        $v = json_decode((string) $this->settings->get('disabled_builtins', '[]'), true);
        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    public function setBuiltinEnabled(string $id, bool $enabled): void
    {
        $list = array_diff($this->disabledBuiltins(), [$id]);
        if (!$enabled) {
            $list[] = $id;
        }
        $this->settings->set('disabled_builtins', json_encode(array_values($list)));
        $this->all = null;
    }

    public function customFromRow(array $row): CustomTemplate
    {
        $config = json_decode((string) $row['config'], true) ?: [];
        return new CustomTemplate(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['category'],
            (string) $row['emoji'],
            (int) $row['version'],
            array_merge(CustomTemplate::defaults(), $config),
            $this->dir,
            dirname($this->dir) . '/cache',
        );
    }

    /** @return array<string, Template> enabled templates keyed by id, in display order */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }
        $out = [];
        $rows = $this->db->all("SELECT * FROM templates WHERE enabled = 1 AND config <> '' ORDER BY sort ASC, id ASC");
        foreach ($rows as $row) {
            $t = $this->customFromRow($row);
            if ($t->animated() && !$this->videoAvailable) {
                continue;
            }
            if (!is_file($this->dir . '/' . basename((string) ($t->config['file'] ?? '')))) {
                continue;
            }
            $out[$t->id] = $t;
        }
        $disabled = array_flip($this->disabledBuiltins());
        foreach (Builtins::all() as $t) {
            if (isset($disabled[$t->id]) || ($t->animated() && !$this->videoAvailable)) {
                continue;
            }
            $out[$t->id] = $t;
        }
        return $this->all = $out;
    }

    public function get(string $id): ?Template
    {
        return $this->all()[$id] ?? null;
    }

    /** Any template including disabled ones (admin preview). */
    public function getAny(string $id): ?Template
    {
        if (preg_match('/^c(\d+)$/', $id, $m)) {
            $row = $this->db->one('SELECT * FROM templates WHERE id = ?', [(int) $m[1]]);
            return $row ? $this->customFromRow($row) : null;
        }
        foreach (Builtins::all() as $t) {
            if ($t->id === $id) {
                return $t;
            }
        }
        return null;
    }

    /**
     * Grouped for the mini app.
     * @return array<array{id: string, title: string, items: array}>
     */
    public function categories(): array
    {
        $groups = [];
        foreach ($this->all() as $t) {
            $key = $t instanceof CustomTemplate ? 'u:' . $t->category : $t->category;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => substr(md5($key), 0, 10),
                    'title' => $t instanceof CustomTemplate ? ($t->category !== '' ? $t->category : 'ویژه') : (Builtins::CATEGORY_TITLES[$t->category] ?? $t->category),
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = [
                'id' => $t->id,
                'title' => $t->title,
                'animated' => $t->animated(),
                'v' => $t->version,
            ];
        }
        return array_values($groups);
    }
}
