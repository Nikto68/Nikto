<?php
declare(strict_types=1);

namespace EmojiBot\Api;

use EmojiBot\App;
use EmojiBot\Bot\Membership;
use EmojiBot\Bot\Payments;
use EmojiBot\Packs\PackService;
use EmojiBot\Render\Fonts;
use EmojiBot\Render\Gfx;
use EmojiBot\Render\Params;
use EmojiBot\Render\Renderer;
use EmojiBot\Render\Template;
use EmojiBot\TelegramError;
use EmojiBot\WebAppAuth;
use InvalidArgumentException;
use Throwable;

/**
 * JSON API used by the mini app. Every request is authenticated with Telegram initData.
 */
final class Api
{
    private const PREVIEW_STATIC = 176;
    private const PREVIEW_ANIMATED = 128;

    private array $auth = [];
    private array $user = [];
    private int $uid = 0;
    private ?array $afterResponse = null;

    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new ApiError('method not allowed', 405);
            }
            $in = $this->input();
            $this->authenticate();
            $action = (string) ($in['a'] ?? '');
            $data = $this->dispatch($action, $in);
            $this->respond(['ok' => true] + $data);
        } catch (ApiError $e) {
            $this->respond(['ok' => false, 'error' => $e->getMessage(), 'code' => $e->errorCode], $e->getCode() ?: 400);
        } catch (InvalidArgumentException $e) {
            $this->respond(['ok' => false, 'error' => $e->getMessage(), 'code' => 'invalid'], 400);
        } catch (Throwable $e) {
            $this->app->log('api', 'error', ['err' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $this->respond(['ok' => false, 'error' => 'خطای سرور. دوباره تلاش کنید.', 'code' => 'server'], 500);
        }
    }

    private function input(): array
    {
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_starts_with($type, 'multipart/form-data')) {
            return $_POST;
        }
        $raw = file_get_contents('php://input', false, null, 0, 262144);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new ApiError('bad request', 400);
        }
        return $data;
    }

    private function authenticate(): void
    {
        $initData = (string) ($_SERVER['HTTP_X_INIT_DATA'] ?? '');
        $auth = WebAppAuth::validate($initData, (string) $this->app->config('bot_token'), (int) $this->app->config('init_data_ttl', 86400));
        if ($auth === null) {
            throw new ApiError('نشست نامعتبر است. مینی‌اپ را ببندید و دوباره باز کنید.', 401, 'auth');
        }
        $this->auth = $auth;
        [$this->user] = $this->app->users()->touch($auth['user']);
        $this->uid = (int) $this->user['id'];
        if ((int) $this->user['banned'] === 1) {
            throw new ApiError('🚫 دسترسی شما مسدود شده است.', 403, 'banned');
        }
        if (!$this->app->limiter()->hit('api:' . $this->uid, 300, 60)) {
            throw new ApiError('درخواست‌ها زیاد است؛ کمی صبر کنید.', 429, 'rate');
        }
    }

    private function isAdmin(): bool
    {
        return $this->app->isAdmin($this->uid);
    }

    private function dispatch(string $action, array $in): array
    {
        if (str_starts_with($action, 'adm_')) {
            if (!$this->isAdmin()) {
                throw new ApiError('دسترسی ندارید.', 403, 'forbidden');
            }
            return (new AdminApi($this->app, $this))->dispatch($action, $in);
        }
        if ($action !== 'boot' && !$this->isAdmin() && $this->app->settings()->get('maintenance')) {
            throw new ApiError('🛠 ربات در حال بروزرسانی است.', 503, 'maintenance');
        }
        return match ($action) {
            'boot' => $this->boot(),
            'preview' => $this->preview($in),
            'create' => $this->create($in),
            'job' => $this->job($in),
            'packs' => ['packs' => $this->packsList()],
            'pack_delete' => $this->packDelete($in),
            'gift' => $this->gift(),
            'invoice' => $this->invoice($in),
            'check_join' => ['joined' => Membership::isMember($this->app, $this->uid, true)],
            'me' => ['user' => $this->userInfo()],
            default => throw new ApiError('unknown action', 404),
        };
    }

    // ---- actions ---------------------------------------------------------------------

    private function userInfo(): array
    {
        $u = $this->app->users()->get($this->uid);
        $giftIn = max(0, (int) $u['last_gift_at'] + 86400 - time());
        return [
            'id' => $this->uid,
            'name' => $this->app->users()->displayName($u),
            'coins' => (int) $u['coins'],
            'premium' => (bool) $u['is_premium'],
            'admin' => $this->isAdmin(),
            'gift_in' => $this->app->settings()->int('daily_gift') > 0 ? $giftIn : -1,
        ];
    }

    private function boot(): array
    {
        $app = $this->app;
        $s = $app->settings();
        $packages = [];
        foreach ((new Payments($app))->packages() as $i => $p) {
            $packages[] = ['id' => $i] + $p;
        }
        $fonts = [];
        foreach (Fonts::STYLES as $id => [$title, $family]) {
            $fonts[] = ['id' => $id, 'title' => $title, 'family' => $family];
        }
        $active = $app->jobs()->activeForUser($this->uid);
        return [
            'user' => $this->userInfo(),
            'bot' => ['username' => $app->botUsername(), 'name' => $app->botName()],
            'config' => [
                'text_max' => $s->int('text_max'),
                'latin_only' => !$s->get('allow_persian'),
                'price' => $this->isAdmin() ? 0 : $s->int('price_per_emoji'),
                'max_per_pack' => $s->int('max_per_pack'),
                'daily_gift' => $s->int('daily_gift'),
                'referral_bonus' => $s->int('referral_bonus'),
                'daily_limit' => $s->int('daily_job_limit'),
                'maintenance' => (bool) $s->get('maintenance') && !$this->isAdmin(),
                'max_set' => PackService::MAX_SET_SIZE,
            ],
            'fonts' => $fonts,
            'categories' => $app->templates()->categories(),
            'packs' => $this->packsList(),
            'packages' => $packages,
            'join' => Membership::isMember($app, $this->uid) ? null : ['url' => Membership::url($app)],
            'ref_link' => 'https://t.me/' . $app->botUsername() . '?start=ref_' . $this->uid,
            'today' => $app->jobs()->todayCount($this->uid),
            'job' => $active ? (int) $active['id'] : null,
        ];
    }

    private function params(array $in): Params
    {
        return Params::fromArray(
            (array) ($in['params'] ?? []),
            $this->app->fonts(),
            $this->app->settings()->int('text_max'),
            !$this->app->settings()->get('allow_persian'),
        );
    }

    /** @return array{src: string, frames: int} */
    public function previewOf(Template $t, Params $p, bool $cache = true): array
    {
        $size = $t->animated() ? self::PREVIEW_ANIMATED : self::PREVIEW_STATIC;
        if ($cache) {
            $r = $this->app->renderer()->preview($t, $p, $size);
            return ['src' => 'data:image/png;base64,' . base64_encode($r['png']), 'frames' => $r['frames']];
        }
        $renderer = $this->app->renderer();
        if ($t->animated()) {
            $step = Renderer::FRAMES / Renderer::PREVIEW_FRAMES;
            $frames = $renderer->renderFrames($t, $p, $size, Renderer::FRAMES, array_map(fn ($i) => (int) round($i * $step), range(0, Renderer::PREVIEW_FRAMES - 1)));
            $im = Gfx::canvas($size * count($frames), $size);
            foreach ($frames as $i => $f) {
                imagecopy($im, $f, $i * $size, 0, 0, 0, $size, $size);
            }
            return ['src' => 'data:image/png;base64,' . base64_encode(Gfx::png($im, 6)), 'frames' => count($frames)];
        }
        return ['src' => 'data:image/png;base64,' . base64_encode(Gfx::png($renderer->renderStatic($t, $p, $size), 6)), 'frames' => 1];
    }

    private function preview(array $in): array
    {
        if (!$this->app->limiter()->hit('prev:' . $this->uid, 120, 60)) {
            throw new ApiError('کمی آهسته‌تر 🙂', 429, 'rate');
        }
        $p = $this->params($in);
        $ids = array_slice(array_values(array_unique(array_filter((array) ($in['ids'] ?? []), 'is_string'))), 0, 12);
        $items = [];
        foreach ($ids as $id) {
            $t = $this->app->templates()->get($id);
            if ($t) {
                $items[$id] = $this->previewOf($t, $p);
            }
        }
        return ['items' => $items];
    }

    private function create(array $in): array
    {
        $app = $this->app;
        if (!$app->limiter()->hit('create:' . $this->uid, 8, 60)) {
            throw new ApiError('کمی صبر کنید و دوباره تلاش کنید.', 429, 'rate');
        }
        if (!Membership::isMember($app, $this->uid, true)) {
            throw new ApiError('ابتدا عضو کانال شوید.', 403, 'join');
        }
        $p = $this->params($in);
        $ids = array_values(array_unique(array_filter((array) ($in['ids'] ?? []), 'is_string')));
        $ids = array_values(array_filter($ids, fn ($id) => $app->templates()->get($id) !== null));
        $n = count($ids);
        $maxPer = $app->settings()->int('max_per_pack');
        if ($n === 0) {
            throw new ApiError('حداقل یک قالب انتخاب کنید.', 400, 'invalid');
        }
        if ($n > $maxPer) {
            throw new ApiError("حداکثر $maxPer ایموجی در هر بار ساخت.", 400, 'invalid');
        }
        // Serialize create requests of the same user (double taps, two devices).
        $lock = fopen($app->storage('tmp') . '/user-' . $this->uid . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new ApiError('یک ساخت در حال انجام است؛ کمی صبر کنید.', 409, 'busy');
        }
        try {
            return $this->createLocked($in, $p, $ids, $n);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function createLocked(array $in, Params $p, array $ids, int $n): array
    {
        $app = $this->app;
        if ($app->jobs()->activeForUser($this->uid)) {
            throw new ApiError('یک ساخت در حال انجام است؛ کمی صبر کنید.', 409, 'busy');
        }
        if (!$this->isAdmin() && $app->jobs()->todayCount($this->uid) >= $app->settings()->int('daily_job_limit')) {
            throw new ApiError('سقف ساخت امروز شما پر شده است. فردا دوباره امتحان کنید.', 429, 'daily');
        }

        $packId = (int) ($in['pack_id'] ?? 0);
        $payload = ['params' => $p->toArray(), 'templates' => $ids];
        if ($packId > 0) {
            $pack = $app->db()->one('SELECT * FROM packs WHERE id = ? AND user_id = ? AND deleted = 0', [$packId, $this->uid]);
            if (!$pack) {
                throw new ApiError('پک پیدا نشد.', 404, 'invalid');
            }
            if ((int) $pack['emoji_count'] + $n > PackService::MAX_SET_SIZE) {
                throw new ApiError('ظرفیت این پک کافی نیست (حداکثر ۲۰۰ ایموجی).', 400, 'invalid');
            }
            $payload['pack_id'] = $packId;
            $type = 'add';
        } else {
            $payload['title'] = $app->packs()->title((string) ($in['title'] ?? ''), $p->text);
            $type = 'create';
        }

        $cost = $this->isAdmin() ? 0 : $app->settings()->int('price_per_emoji') * $n;
        $ref = 'job:new';
        if ($cost > 0 && !$app->users()->spendCoins($this->uid, $cost, 'create', $ref)) {
            throw new ApiError('سکه کافی ندارید.', 402, 'coins');
        }
        try {
            $jobId = $app->jobs()->create($this->uid, $type, $payload, $n, $cost);
        } catch (Throwable $e) {
            if ($cost > 0) {
                $app->users()->addCoins($this->uid, $cost, 'refund', $ref);
            }
            throw $e;
        }
        $this->afterResponse = ['job' => $jobId];
        return ['job_id' => $jobId, 'cost' => $cost, 'coins' => (int) $app->users()->get($this->uid)['coins']];
    }

    private function job(array $in): array
    {
        $job = $this->app->jobs()->get((int) ($in['id'] ?? 0), $this->uid);
        if (!$job) {
            throw new ApiError('not found', 404);
        }
        if ($job['status'] === 'pending') {
            $this->afterResponse = ['job' => (int) $job['id']]; // help drain the queue if no worker is running
        }
        $result = json_decode((string) $job['result'], true) ?: [];
        return [
            'status' => $job['status'],
            'progress' => (int) $job['progress'],
            'total' => (int) $job['total'],
            'stage' => $result['stage'] ?? 'queue',
            'result' => $job['status'] === 'done' ? $result : null,
            'error' => $job['error'],
            'coins' => (int) $this->app->users()->get($this->uid)['coins'],
        ];
    }

    private function packsList(): array
    {
        $rows = $this->app->db()->all('SELECT * FROM packs WHERE user_id = ? AND deleted = 0 ORDER BY id DESC LIMIT 60', [$this->uid]);
        $out = [];
        foreach ($rows as $r) {
            $cover = null;
            $c = json_decode((string) $r['cover'], true);
            if (is_array($c) && isset($c['tid'])) {
                try {
                    $t = $this->app->templates()->getAny((string) $c['tid']);
                    if ($t) {
                        $cover = $this->previewOf($t, Params::fromArray($c['params'] ?? [], $this->app->fonts(), 40));
                    }
                } catch (Throwable) {
                    $cover = null;
                }
            }
            $out[] = [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'title' => $r['title'],
                'count' => (int) $r['emoji_count'],
                'link' => PackService::link($r['name']),
                'created_at' => (int) $r['created_at'],
                'cover' => $cover,
            ];
        }
        return $out;
    }

    private function packDelete(array $in): array
    {
        if (!$this->app->limiter()->hit('del:' . $this->uid, 10, 60)) {
            throw new ApiError('کمی صبر کنید.', 429, 'rate');
        }
        $pack = $this->app->db()->one('SELECT * FROM packs WHERE id = ? AND user_id = ? AND deleted = 0', [(int) ($in['id'] ?? 0), $this->uid]);
        if (!$pack) {
            throw new ApiError('پک پیدا نشد.', 404, 'invalid');
        }
        try {
            $this->app->packs()->deleteSet($pack['name']);
        } catch (TelegramError $e) {
            throw new ApiError('حذف ناموفق بود: ' . PackService::friendlyError($e), 502, 'telegram');
        }
        $this->app->db()->exec('UPDATE packs SET deleted = 1, updated_at = ? WHERE id = ?', [time(), $pack['id']]);
        return ['packs' => $this->packsList()];
    }

    private function gift(): array
    {
        $amount = $this->app->settings()->int('daily_gift');
        $granted = $this->app->users()->claimGift($this->uid, $amount);
        return ['granted' => $granted, 'user' => $this->userInfo()];
    }

    private function invoice(array $in): array
    {
        if (!$this->app->limiter()->hit('inv:' . $this->uid, 10, 60)) {
            throw new ApiError('کمی صبر کنید.', 429, 'rate');
        }
        return ['link' => (new Payments($this->app))->invoiceLink($this->uid, (int) ($in['pkg'] ?? -1))];
    }

    // ---- output ------------------------------------------------------------------------

    private function respond(array $data, int $status = 200): void
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        http_response_code($status);
        if ($this->afterResponse === null) {
            echo $body;
            return;
        }
        // Send the response now and keep working on the queue in this process.
        ignore_user_abort(true);
        @set_time_limit(300);
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        try {
            $this->app->jobs()->drain(240, (int) $this->afterResponse['job']);
        } catch (Throwable $e) {
            $this->app->log('api', 'drain failed', ['err' => $e->getMessage()]);
        }
    }
}
