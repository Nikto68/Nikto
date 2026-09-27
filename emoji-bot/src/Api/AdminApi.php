<?php
declare(strict_types=1);

namespace EmojiBot\Api;

use EmojiBot\App;
use EmojiBot\Render\ArtCatalog;
use EmojiBot\Render\Builtins;
use EmojiBot\Render\CustomTemplate;
use EmojiBot\Render\Params;
use EmojiBot\Render\Template;
use RuntimeException;

/**
 * Template management for admins (upload artwork, draw the text box, pick colors/effects).
 */
final class AdminApi
{
    private const MAX_UPLOAD = 5 * 1024 * 1024;

    public function __construct(private readonly App $app, private readonly Api $api)
    {
    }

    public function dispatch(string $action, array $in): array
    {
        return match ($action) {
            'adm_list' => $this->list(),
            'adm_upload' => $this->upload($in),
            'adm_save' => $this->save($in),
            'adm_delete' => $this->delete($in),
            'adm_builtin' => $this->builtin($in),
            'adm_preview' => $this->preview($in),
            'adm_image' => $this->image($in),
            default => throw new ApiError('unknown action', 404),
        };
    }

    private function row(int $id): array
    {
        $row = $this->app->db()->one('SELECT * FROM templates WHERE id = ?', [$id]);
        if (!$row) {
            throw new ApiError('Template not found.', 404, 'invalid');
        }
        return $row;
    }

    private function export(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'tid' => 'c' . $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'emoji' => $row['emoji'],
            'sort' => (int) $row['sort'],
            'enabled' => (bool) $row['enabled'],
            'version' => (int) $row['version'],
            'config' => array_merge(CustomTemplate::defaults(), json_decode((string) $row['config'], true) ?: []),
        ];
    }

    private function list(): array
    {
        $rows = $this->app->db()->all('SELECT * FROM templates ORDER BY sort ASC, id ASC');
        $disabled = array_flip($this->app->templates()->disabledBuiltins());
        $builtins = [];
        foreach ([...ArtCatalog::all('', ''), ...Builtins::all()] as $t) {
            $builtins[] = [
                'id' => $t->id,
                'title' => $t->title,
                'category' => ArtCatalog::CATEGORY_TITLES[$t->category] ?? Builtins::CATEGORY_TITLES[$t->category] ?? $t->category,
                'animated' => $t->animated(),
                'enabled' => !isset($disabled[$t->id]),
            ];
        }
        return [
            'custom' => array_map(fn ($r) => $this->export($r), $rows),
            'builtins' => $builtins,
            'effects' => Template::EFFECTS,
            'recolor' => CustomTemplate::RECOLOR,
            'categories' => array_values(array_filter(array_unique(array_column($rows, 'category')))),
            'video' => $this->app->video()->available(),
        ];
    }

    private function upload(array $in): array
    {
        $f = $_FILES['image'] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new ApiError('Upload failed.', 400, 'invalid');
        }
        if ((int) $f['size'] > self::MAX_UPLOAD) {
            throw new ApiError('The image must be 5 MB or smaller.', 400, 'invalid');
        }
        $kind = ($in['kind'] ?? 'base') === 'overlay' ? 'overlay' : 'base';
        $db = $this->app->db();
        $dir = $this->app->storage('templates');
        $id = (int) ($in['id'] ?? 0);

        if ($id > 0) {
            $row = $this->row($id);
        } else {
            if ($kind === 'overlay') {
                throw new ApiError('Upload the main image first.', 400, 'invalid');
            }
            $id = $db->insert('templates', [
                'title' => 'New template',
                'category' => 'Special',
                'emoji' => '✨',
                'config' => '',
                'sort' => (int) $db->val('SELECT COALESCE(MAX(sort), 0) + 1 FROM templates'),
                'enabled' => 0,
                'version' => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $row = $this->row($id);
        }
        try {
            $file = CustomTemplate::storeUpload($f['tmp_name'], $dir, 't' . $id . ($kind === 'overlay' ? 'o' : '') . '_' . bin2hex(random_bytes(4)));
        } catch (RuntimeException $e) {
            if ((string) $row['config'] === '') {
                $db->exec('DELETE FROM templates WHERE id = ?', [$id]);
            }
            throw new ApiError($e->getMessage(), 400, 'invalid');
        }
        $config = array_merge(CustomTemplate::defaults(), json_decode((string) $row['config'], true) ?: []);
        $old = $kind === 'overlay' ? ($config['overlay'] ?? null) : ($config['file'] ?? '');
        if ($kind === 'overlay') {
            $config['overlay'] = $file;
        } else {
            $config['file'] = $file;
        }
        $db->exec('UPDATE templates SET config = ?, version = version + 1, updated_at = ? WHERE id = ?', [json_encode($config), time(), $id]);
        if ($old) {
            @unlink($dir . '/' . basename((string) $old));
        }
        return ['template' => $this->export($this->row($id))];
    }

    private function save(array $in): array
    {
        $id = (int) ($in['id'] ?? 0);
        $row = $this->row($id);
        $current = json_decode((string) $row['config'], true) ?: [];
        $config = CustomTemplate::sanitize((array) ($in['config'] ?? []), $current);
        if (!empty($in['remove_overlay']) && !empty($config['overlay'])) {
            @unlink($this->app->storage('templates') . '/' . basename((string) $config['overlay']));
            $config['overlay'] = null;
        }
        $title = mb_substr(trim((string) ($in['title'] ?? $row['title'])), 0, 64) ?: 'Template';
        $category = mb_substr(trim((string) ($in['category'] ?? $row['category'])), 0, 64);
        $emoji = trim((string) ($in['emoji'] ?? $row['emoji']));
        if ($emoji === '' || mb_strlen($emoji) > 8 || preg_match('/[\p{L}\p{N}\s]/u', $emoji)) {
            throw new ApiError('Invalid emoji (use a single emoji).', 400, 'invalid');
        }
        if ($config['effect'] !== 'none' && !$this->app->video()->available()) {
            throw new ApiError('ffmpeg is not available on the server, so animations are disabled.', 400, 'invalid');
        }
        $this->app->db()->exec(
            'UPDATE templates SET title = ?, category = ?, emoji = ?, sort = ?, enabled = ?, config = ?, version = version + 1, updated_at = ? WHERE id = ?',
            [$title, $category, $emoji, (int) ($in['sort'] ?? $row['sort']), !empty($in['enabled']) ? 1 : 0, json_encode($config), time(), $id],
        );
        return ['template' => $this->export($this->row($id))];
    }

    private function delete(array $in): array
    {
        $row = $this->row((int) ($in['id'] ?? 0));
        $config = json_decode((string) $row['config'], true) ?: [];
        foreach (['file', 'overlay'] as $k) {
            if (!empty($config[$k])) {
                @unlink($this->app->storage('templates') . '/' . basename((string) $config[$k]));
            }
        }
        $this->app->db()->exec('DELETE FROM templates WHERE id = ?', [$row['id']]);
        return $this->list();
    }

    private function builtin(array $in): array
    {
        $id = (string) ($in['id'] ?? '');
        $valid = array_map(fn ($t) => $t->id, [...ArtCatalog::all('', ''), ...Builtins::all()]);
        if (!in_array($id, $valid, true)) {
            throw new ApiError('Invalid template.', 400, 'invalid');
        }
        $this->app->templates()->setBuiltinEnabled($id, !empty($in['enabled']));
        return ['ok' => true];
    }

    private function preview(array $in): array
    {
        $row = $this->row((int) ($in['id'] ?? 0));
        $current = json_decode((string) $row['config'], true) ?: [];
        $config = CustomTemplate::sanitize((array) ($in['config'] ?? []), $current);
        if ($config['effect'] !== 'none' && !$this->app->video()->available()) {
            $config['effect'] = 'none';
        }
        $row['config'] = json_encode($config);
        $t = $this->app->templates()->customFromRow($row);
        $p = Params::fromArray((array) ($in['params'] ?? []), $this->app->fonts(), 40);
        return $this->api->previewOf($t, $p, false);
    }

    private function image(array $in): array
    {
        $row = $this->row((int) ($in['id'] ?? 0));
        $config = json_decode((string) $row['config'], true) ?: [];
        $file = ($in['kind'] ?? 'base') === 'overlay' ? ($config['overlay'] ?? '') : ($config['file'] ?? '');
        $path = $this->app->storage('templates') . '/' . basename((string) $file);
        if (!$file || !is_file($path)) {
            throw new ApiError('Image not found.', 404, 'invalid');
        }
        return ['src' => 'data:image/png;base64,' . base64_encode((string) file_get_contents($path))];
    }
}
