<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';

/**
 * ============================================================================
 * bot.php — Telegram bot: API client, admin panel, channel management,
 * text/entity management (Premium emoji-safe), quote/reply, webhook router.
 * ============================================================================
 */

// ============================================================================
// SECTION 1 — TELEGRAM CLIENT
// ============================================================================

final class TelegramClient
{
    private string $apiBase;

    public function __construct(?string $token = null)
    {
        $this->apiBase = 'https://api.telegram.org/bot' . ($token ?? Config::telegramBotToken());
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public function request(string $method, array $params = []): array
    {
        $url = $this->apiBase . '/' . $method;
        $body = json_encode($params, JSON_UNESCAPED_UNICODE);

        $attempt = 0;
        while (true) {
            $attempt++;
            $res = HttpClient::request('POST', $url, ['Content-Type' => 'application/json'], $body ?: '{}', 0);
            $json = $res['json'];

            if ($json !== null && ($json['ok'] ?? false) === true) {
                return $json;
            }

            $errorCode = $json['error_code'] ?? $res['status'];
            if ($errorCode === 429 && $attempt <= Config::maxRetries()) {
                $retryAfter = (int) ($json['parameters']['retry_after'] ?? 1);
                Logger::warning('telegram', 'rate limited, retrying', ['method' => $method, 'retry_after' => $retryAfter]);
                sleep(max(1, $retryAfter));
                continue;
            }

            Logger::error('telegram', "$method failed", ['status' => $res['status'], 'description' => $json['description'] ?? null]);
            return $json ?? ['ok' => false, 'description' => 'no response'];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $entities
     * @param array<string,mixed> $opts extra API params: reply_markup, disable_notification, message_thread_id, reply_parameters, etc.
     */
    public function sendMessage(int|string $chatId, string $text, array $entities = [], array $opts = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'entities' => $entities,
        ], $opts);
        return $this->request('sendMessage', $params);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $entities = [], array $opts = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'entities' => $entities,
        ], $opts);
        return $this->request('editMessageText', $params);
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): array
    {
        return $this->request('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function pinChatMessage(int|string $chatId, int $messageId, bool $disableNotification = true): array
    {
        return $this->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): array
    {
        $params = ['chat_id' => $chatId];
        if ($messageId !== null) {
            $params['message_id'] = $messageId;
        }
        return $this->request('unpinChatMessage', $params);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $params = ['callback_query_id' => $callbackQueryId, 'show_alert' => $showAlert];
        if ($text !== null) {
            $params['text'] = $text;
        }
        return $this->request('answerCallbackQuery', $params);
    }

    public function getChat(int|string $chatId): array
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->request('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    public function getMe(): array
    {
        return $this->request('getMe');
    }

    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member', 'channel_post'],
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }
}

// ============================================================================
// SECTION 2 — TEXT FORMAT MANAGER (Premium Emoji / entity-preserving store)
// ============================================================================

final class TextFormatManager
{
    /**
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function get(string $key): array
    {
        $stmt = Database::pdo()->prepare('SELECT text_value, entities FROM text_formats WHERE text_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return ['text' => '', 'entities' => []];
        }
        $entities = json_decode((string) $row['entities'], true);
        return ['text' => (string) $row['text_value'], 'entities' => is_array($entities) ? $entities : []];
    }

    /**
     * Persists raw Telegram $text + $entities exactly as received from
     * getUpdates/webhook — never re-derived from Markdown, so
     * custom_emoji/bold/italic/underline/strikethrough/spoiler/code/pre/
     * text_link/mention all survive verbatim.
     *
     * @param array<int,array<string,mixed>> $entities
     */
    public function set(string $key, string $text, array $entities, ?int $updatedBy = null): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO text_formats (text_key, text_value, entities, updated_at, updated_by)
             VALUES (:k, :v, :e, :now, :by)
             ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), entities = VALUES(entities),
                updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            ':k' => $key, ':v' => $text, ':e' => json_encode($entities, JSON_UNESCAPED_UNICODE),
            ':now' => date('Y-m-d H:i:s'), ':by' => $updatedBy,
        ]);
    }

    /**
     * Renders a stored text with {placeholder} substitution while keeping
     * every entity's offset/length correct (see TelegramEntityUtils).
     * @param array<string,string> $placeholders
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function render(string $key, array $placeholders = []): array
    {
        $stored = $this->get($key);
        if ($stored['text'] === '') {
            return $stored;
        }
        return TelegramEntityUtils::renderTemplate($stored['text'], $stored['entities'], $placeholders);
    }

    /** @return string[] all known text keys, for the admin panel list */
    public function listKeys(): array
    {
        $stmt = Database::pdo()->query('SELECT text_key FROM text_formats ORDER BY text_key ASC');
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

// ============================================================================
// SECTION 3 — CHANNEL MANAGER
// ============================================================================

final class ChannelManager
{
    public function __construct(private TelegramClient $telegram)
    {
    }

    public function add(int $chatId, ?int $addedBy = null): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO channels (chat_id, title, type, is_active, bot_status, can_post, added_by, created_at, updated_at)
             VALUES (:cid, NULL, "channel", 0, "unknown", 0, :by, :now1, :now2)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)'
        );
        $stmt->execute([':cid' => $chatId, ':by' => $addedBy, ':now1' => $now, ':now2' => $now]);

        $id = (int) Database::pdo()->lastInsertId();
        if ($id === 0) {
            $row = $this->findByChatId($chatId);
            $id = $row !== null ? (int) $row['id'] : 0;
        }
        if ($id > 0) {
            $this->ensureSettings($id);
        }
        $this->refreshAccessStatus($chatId);
        return $id;
    }

    public function edit(int $channelId, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['title', 'username']));
        if (empty($allowed)) {
            return;
        }
        $sets = [];
        $params = [':id' => $channelId];
        foreach ($allowed as $k => $v) {
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $sets[] = 'updated_at = :now';
        $params[':now'] = date('Y-m-d H:i:s');
        $sql = 'UPDATE channels SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }

    public function remove(int $channelId): void
    {
        Database::pdo()->prepare('DELETE FROM channels WHERE id = :id')->execute([':id' => $channelId]);
    }

    public function toggleActive(int $channelId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT is_active FROM channels WHERE id = :id');
        $stmt->execute([':id' => $channelId]);
        $current = (int) $stmt->fetchColumn();
        $new = $current ? 0 : 1;
        $pdo->prepare('UPDATE channels SET is_active = :a, updated_at = :now WHERE id = :id')
            ->execute([':a' => $new, ':now' => date('Y-m-d H:i:s'), ':id' => $channelId]);
        return (bool) $new;
    }

    /**
     * Verifies the bot's own membership/admin status in the target chat
     * before it may be activated. Persists bot_status/can_post.
     * @return array{ok:bool, status:string, can_post:bool, reason?:string}
     */
    public function checkBotAccess(int $chatId): array
    {
        $me = $this->telegram->getMe();
        if (!($me['ok'] ?? false)) {
            return ['ok' => false, 'status' => 'unknown', 'can_post' => false, 'reason' => 'bot token invalid'];
        }
        $botId = (int) ($me['result']['id'] ?? 0);

        $member = $this->telegram->getChatMember($chatId, $botId);
        if (!($member['ok'] ?? false)) {
            $this->persistAccessStatus($chatId, 'unknown', false);
            return ['ok' => false, 'status' => 'unknown', 'can_post' => false, 'reason' => $member['description'] ?? 'chat not accessible'];
        }

        $status = (string) ($member['result']['status'] ?? 'unknown');
        $canPost = $status === 'administrator'
            ? (bool) ($member['result']['can_post_messages'] ?? true)
            : false;

        $this->persistAccessStatus($chatId, $status, $canPost);

        $ok = in_array($status, ['administrator', 'creator'], true) && $canPost;
        return ['ok' => $ok, 'status' => $status, 'can_post' => $canPost];
    }

    private function refreshAccessStatus(int $chatId): void
    {
        try {
            $this->checkBotAccess($chatId);
        } catch (Throwable $e) {
            Logger::warning('channel', 'access check failed', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
        }
    }

    private function persistAccessStatus(int $chatId, string $status, bool $canPost): void
    {
        Database::pdo()->prepare(
            'UPDATE channels SET bot_status = :status, can_post = :can_post, updated_at = :now WHERE chat_id = :cid'
        )->execute([':status' => $status, ':can_post' => $canPost ? 1 : 0, ':now' => date('Y-m-d H:i:s'), ':cid' => $chatId]);
    }

    public function findByChatId(int $chatId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM channels WHERE chat_id = :cid LIMIT 1');
        $stmt->execute([':cid' => $chatId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM channels WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM channels ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<int,array<string,mixed>> active + accessible channels, joined with their settings */
    public function listActiveWithSettings(): array
    {
        $sql = 'SELECT c.*, cs.template_key, cs.min_signal_score, cs.allowed_strategies, cs.allowed_exchanges,
                       cs.enabled AS settings_enabled, cs.pin_signal, cs.quote_enabled
                FROM channels c
                JOIN channel_settings cs ON cs.channel_id = c.id
                WHERE c.is_active = 1 AND c.can_post = 1 AND cs.enabled = 1';
        return Database::pdo()->query($sql)->fetchAll();
    }

    private function ensureSettings(int $channelId): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'INSERT IGNORE INTO channel_settings (channel_id, template_key, min_signal_score, enabled, pin_signal, quote_enabled, created_at, updated_at)
             VALUES (:cid, "signal_template", :minscore, 1, 0, 0, :now1, :now2)'
        )->execute([':cid' => $channelId, ':minscore' => Config::minSignalScore(), ':now1' => $now, ':now2' => $now]);
    }

    public function updateSettings(int $channelId, array $fields): void
    {
        $this->ensureSettings($channelId);
        $allowed = array_intersect_key($fields, array_flip([
            'template_key', 'min_signal_score', 'allowed_strategies', 'allowed_exchanges', 'enabled', 'pin_signal', 'quote_enabled',
        ]));
        if (empty($allowed)) {
            return;
        }
        $sets = [];
        $params = [':cid' => $channelId];
        foreach ($allowed as $k => $v) {
            if (in_array($k, ['allowed_strategies', 'allowed_exchanges'], true) && is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $sets[] = 'updated_at = :now';
        $params[':now'] = date('Y-m-d H:i:s');
        Database::pdo()->prepare('UPDATE channel_settings SET ' . implode(', ', $sets) . ' WHERE channel_id = :cid')->execute($params);
    }

    public function getSettings(int $channelId): ?array
    {
        $this->ensureSettings($channelId);
        $stmt = Database::pdo()->prepare('SELECT * FROM channel_settings WHERE channel_id = :cid');
        $stmt->execute([':cid' => $channelId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

// ============================================================================
// SECTION 4 — QUOTE / REPLY MANAGER
// ============================================================================

final class QuoteManager
{
    /** @param array<int,array<string,mixed>> $entities */
    public function store(string $refType, string $refId, int $chatId, int $messageId, string $quoteText = '', array $entities = []): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO message_refs (ref_type, ref_id, chat_id, message_id, quote_text, quote_entities, created_at)
             VALUES (:t, :r, :cid, :mid, :qt, :qe, :now)'
        );
        $stmt->execute([
            ':t' => $refType, ':r' => $refId, ':cid' => $chatId, ':mid' => $messageId,
            ':qt' => $quoteText, ':qe' => json_encode($entities, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s'),
        ]);
    }

    public function latest(string $refType, string $refId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM message_refs WHERE ref_type = :t AND ref_id = :r ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':t' => $refType, ':r' => $refId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Builds the `reply_parameters` (+ optional quote) payload for
     * sendMessage, from a stored ref — used so a Signal can be delivered
     * as a reply/quote to an earlier bot or admin message.
     */
    public function buildReplyParameters(array $ref): array
    {
        $params = [
            'message_id' => (int) $ref['message_id'],
            'chat_id' => (int) $ref['chat_id'],
        ];
        $quoteText = (string) ($ref['quote_text'] ?? '');
        if ($quoteText !== '') {
            $params['quote'] = $quoteText;
            $entities = json_decode((string) ($ref['quote_entities'] ?? '[]'), true);
            if (is_array($entities) && !empty($entities)) {
                $params['quote_entities'] = $entities;
            }
        }
        return $params;
    }
}

// ============================================================================
// SECTION 5 — ADMIN AUTH + STATE
// ============================================================================

final class AdminAuth
{
    public static function isAdmin(int $telegramUserId): bool
    {
        if (in_array($telegramUserId, Config::adminIds(), true)) {
            return true;
        }
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM admins WHERE telegram_user_id = :id');
        $stmt->execute([':id' => $telegramUserId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

final class AdminStateStore
{
    public function set(int $userId, string $state, array $payload = []): void
    {
        Database::pdo()->prepare(
            'INSERT INTO admin_states (telegram_user_id, state, payload, updated_at) VALUES (:id, :state, :payload, :now)
             ON DUPLICATE KEY UPDATE state = VALUES(state), payload = VALUES(payload), updated_at = VALUES(updated_at)'
        )->execute([':id' => $userId, ':state' => $state, ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s')]);
    }

    public function get(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT state, payload FROM admin_states WHERE telegram_user_id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $payload = json_decode((string) $row['payload'], true);
        return ['state' => $row['state'], 'payload' => is_array($payload) ? $payload : []];
    }

    public function clear(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM admin_states WHERE telegram_user_id = :id')->execute([':id' => $userId]);
    }
}

// ============================================================================
// SECTION 6 — ADMIN PANEL (inline keyboard tree + callback router)
// ============================================================================

final class AdminPanel
{
    private TextFormatManager $texts;
    private ChannelManager $channels;
    private AdminStateStore $states;
    private SignalRepository $signalRepo;

    public function __construct(private TelegramClient $telegram, private ExchangeManager $exchangeManager)
    {
        $this->texts = new TextFormatManager();
        $this->channels = new ChannelManager($telegram);
        $this->states = new AdminStateStore();
        $this->signalRepo = new SignalRepository();
    }

    public function sendMainMenu(int $chatId): void
    {
        $this->telegram->sendMessage($chatId, "⚙️ پنل مدیریت\n\nیکی از گزینه‌های زیر را انتخاب کنید:", [], [
            'reply_markup' => $this->mainMenuKeyboard(),
        ]);
    }

    private function mainMenuKeyboard(): array
    {
        return ['inline_keyboard' => [
            [['text' => '📡 صرافی‌ها', 'callback_data' => 'admin:exchanges'], ['text' => '📺 کانال‌ها', 'callback_data' => 'admin:channels']],
            [['text' => '📊 Scanner', 'callback_data' => 'admin:scanner'], ['text' => '🧠 Strategies', 'callback_data' => 'admin:strategies']],
            [['text' => '📈 Indicators', 'callback_data' => 'admin:indicators'], ['text' => '📝 Signal Template', 'callback_data' => 'admin:template']],
            [['text' => '✏️ مدیریت متن‌ها', 'callback_data' => 'admin:texts'], ['text' => '🎯 تنظیمات Signal', 'callback_data' => 'admin:signal_settings']],
            [['text' => '🧪 Test Signal', 'callback_data' => 'admin:test_signal'], ['text' => '📜 Signal History', 'callback_data' => 'admin:history']],
            [['text' => '❤️ وضعیت سیستم', 'callback_data' => 'admin:health']],
        ]];
    }

    private function backRow(string $to = 'admin:main'): array
    {
        return [['text' => '🔙 بازگشت', 'callback_data' => $to]];
    }

    /**
     * Central callback router. $callbackQuery is the raw Telegram
     * callback_query update payload.
     */
    public function routeCallback(array $callbackQuery): void
    {
        $userId = (int) ($callbackQuery['from']['id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);
        $callbackId = (string) ($callbackQuery['id'] ?? '');

        if (!AdminAuth::isAdmin($userId)) {
            $this->telegram->answerCallbackQuery($callbackId, 'دسترسی غیرمجاز.', true);
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId);
        $parts = explode(':', $data);
        $section = $parts[1] ?? 'main';

        try {
            match ($section) {
                'main' => $this->render($chatId, $messageId, "⚙️ پنل مدیریت", $this->mainMenuKeyboard()),
                'exchanges' => $this->renderExchanges($chatId, $messageId, $parts),
                'channels' => $this->renderChannels($chatId, $messageId, $parts, $userId),
                'scanner' => $this->renderScanner($chatId, $messageId, $parts),
                'strategies' => $this->renderStrategies($chatId, $messageId, $parts),
                'indicators' => $this->renderIndicators($chatId, $messageId),
                'template' => $this->renderTemplate($chatId, $messageId, $parts, $userId),
                'texts' => $this->renderTexts($chatId, $messageId, $parts, $userId),
                'signal_settings' => $this->renderSignalSettings($chatId, $messageId, $parts),
                'test_signal' => $this->renderTestSignal($chatId, $messageId, $parts),
                'history' => $this->renderHistory($chatId, $messageId),
                'health' => $this->renderHealth($chatId, $messageId),
                default => null,
            };
        } catch (Throwable $e) {
            Logger::error('admin_panel', 'render failed', ['section' => $section, 'error' => $e->getMessage()]);
        }
    }

    private function render(int $chatId, int $messageId, string $text, array $keyboard): void
    {
        $this->telegram->editMessageText($chatId, $messageId, $text, [], ['reply_markup' => $keyboard]);
    }

    // -- 📡 Exchanges ------------------------------------------------------
    private function renderExchanges(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        $rows = Database::pdo()->query('SELECT * FROM exchanges ORDER BY priority ASC')->fetchAll();

        if ($action === 'toggle' && isset($parts[3])) {
            $id = (int) $parts[3];
            Database::pdo()->prepare('UPDATE exchanges SET is_enabled = 1 - is_enabled WHERE id = :id')->execute([':id' => $id]);
            $rows = Database::pdo()->query('SELECT * FROM exchanges ORDER BY priority ASC')->fetchAll();
        }

        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? '🟢' : '🔴';
            $keyboard[] = [['text' => "$status {$r['display_name']}", 'callback_data' => "admin:exchanges:toggle:{$r['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "📡 صرافی‌ها\n\nبرای فعال/غیرفعال کردن روی هرکدام بزنید.", ['inline_keyboard' => $keyboard]);
    }

    // -- 📺 Channels ---------------------------------------------------------
    private function renderChannels(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'add') {
            $this->states->set($userId, 'awaiting_channel_id');
            $this->render($chatId, $messageId, "➕ افزودن کانال\n\nآیدی عددی کانال را ارسال کنید (مثال: -1001234567890).\nربات باید از قبل به عنوان ادمین به کانال اضافه شده باشد.", ['inline_keyboard' => [$this->backRow('admin:channels')]]);
            return;
        }

        if ($action === 'toggle' && isset($parts[3])) {
            $this->channels->toggleActive((int) $parts[3]);
        }

        if ($action === 'remove' && isset($parts[3])) {
            $this->channels->remove((int) $parts[3]);
        }

        if ($action === 'recheck' && isset($parts[3])) {
            $ch = $this->channels->find((int) $parts[3]);
            if ($ch !== null) {
                $this->channels->checkBotAccess((int) $ch['chat_id']);
            }
        }

        if ($action === 'view' && isset($parts[3])) {
            $this->renderChannelDetail($chatId, $messageId, (int) $parts[3]);
            return;
        }

        $channels = $this->channels->listAll();
        $keyboard = [[['text' => '➕ افزودن کانال', 'callback_data' => 'admin:channels:add']]];
        foreach ($channels as $c) {
            $status = $c['is_active'] ? '🟢' : '⚪️';
            $label = $c['title'] ?: (string) $c['chat_id'];
            $keyboard[] = [['text' => "$status $label", 'callback_data' => "admin:channels:view:{$c['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "📺 مدیریت کانال‌ها\n\nتعداد: " . count($channels), ['inline_keyboard' => $keyboard]);
    }

    private function renderChannelDetail(int $chatId, int $messageId, int $channelId): void
    {
        $c = $this->channels->find($channelId);
        if ($c === null) {
            $this->render($chatId, $messageId, "کانال یافت نشد.", ['inline_keyboard' => [$this->backRow('admin:channels')]]);
            return;
        }
        $settings = $this->channels->getSettings($channelId);
        $accessOk = in_array($c['bot_status'], ['administrator', 'creator'], true) && $c['can_post'];

        $text = sprintf(
            "📺 %s\n\nChat ID: %s\nوضعیت ربات: %s\nمجاز به ارسال: %s\nفعال: %s\nحداقل امتیاز: %s\nقالب: %s",
            $c['title'] ?: '-',
            $c['chat_id'],
            $c['bot_status'],
            $accessOk ? '✅' : '❌',
            $c['is_active'] ? '🟢' : '🔴',
            $settings['min_signal_score'] ?? Config::minSignalScore(),
            $settings['template_key'] ?? 'signal_template'
        );

        $toggleLabel = $c['is_active'] ? '🔴 غیرفعال‌سازی' : '🟢 فعال‌سازی';
        $keyboard = [
            [['text' => $toggleLabel, 'callback_data' => "admin:channels:toggle:$channelId"]],
            [['text' => '🔄 بررسی دسترسی ربات', 'callback_data' => "admin:channels:recheck:$channelId"]],
            [['text' => '🗑 حذف کانال', 'callback_data' => "admin:channels:remove:$channelId"]],
            $this->backRow('admin:channels'),
        ];

        if (!$accessOk) {
            $text .= "\n\n⚠️ ربات دسترسی ارسال پیام در این کانال را ندارد یا هنوز ادمین نشده است.";
        }

        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    // -- 📊 Scanner ------------------------------------------------------
    private function renderScanner(int $chatId, int $messageId, array $parts): void
    {
        $activeSymbols = (new SymbolRepository())->countActive();
        $lastRun = (new ScannerRunRepository())->lastRunAt();
        $text = sprintf(
            "📊 وضعیت Scanner\n\nنمادهای فعال: %d\nحداکثر مجاز (Top N): %d\nحداقل حجم: %s USDT\nحداکثر اسپرد: %s%%\nآخرین اسکن: %s",
            $activeSymbols,
            Config::scannerTopN(),
            number_format(Config::minVolumeUsdt()),
            Config::maxSpreadPercent(),
            $lastRun ?? 'هنوز اجرا نشده'
        );
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [$this->backRow()]]);
    }

    // -- 🧠 Strategies ---------------------------------------------------
    private function renderStrategies(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'toggle' && isset($parts[3])) {
            Database::pdo()->prepare('UPDATE strategies SET is_enabled = 1 - is_enabled WHERE id = :id')->execute([':id' => (int) $parts[3]]);
        }

        // Lazily seed the known strategy names from StrategyEngine into DB so they're manageable.
        $engine = new StrategyEngine();
        foreach ($engine->names() as $name) {
            Database::pdo()->prepare(
                'INSERT IGNORE INTO strategies (name, display_name, is_enabled) VALUES (:n, :n, 1)'
            )->execute([':n' => $name]);
        }

        $rows = Database::pdo()->query('SELECT * FROM strategies ORDER BY id ASC')->fetchAll();
        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? '🟢' : '🔴';
            $keyboard[] = [['text' => "$status {$r['display_name']}", 'callback_data' => "admin:strategies:toggle:{$r['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "🧠 استراتژی‌ها\n\nاستراتژی‌های غیرفعال در تولید سیگنال استفاده نمی‌شوند.", ['inline_keyboard' => $keyboard]);
    }

    // -- 📈 Indicators ---------------------------------------------------
    private function renderIndicators(int $chatId, int $messageId): void
    {
        $engine = new IndicatorEngine();
        $names = $engine->registeredNames();
        $text = "📈 Indicator Engine\n\n";
        $text .= empty($names)
            ? "هیچ اندیکاتوری هنوز ثبت نشده است.\nمعماری آماده است — پس از دریافت قوانین دقیق شما، اندیکاتورها به IndicatorEngine::register() اضافه می‌شوند بدون تغییر در بقیه سیستم."
            : ("اندیکاتورهای فعال:\n• " . implode("\n• ", $names));
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [$this->backRow()]]);
    }

    // -- 📝 Signal Template ------------------------------------------------
    private function renderTemplate(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'edit') {
            $this->states->set($userId, 'awaiting_text_input', ['key' => 'signal_template']);
            $this->render($chatId, $messageId, "📝 قالب سیگنال\n\nپیام جدید قالب را ارسال کنید (می‌توانید از Bold/Italic/Emoji اختصاصی/Spoiler و placeholder های {symbol} {exchange} {direction} {entry} {sl} {tp1} {tp2} {tp3} {score} {timeframe} {strategy} {reasons} استفاده کنید).", ['inline_keyboard' => [$this->backRow('admin:template')]]);
            return;
        }

        $stored = $this->texts->get('signal_template');
        $preview = mb_strimwidth($stored['text'], 0, 500, '…');
        $this->render($chatId, $messageId, "📝 قالب فعلی سیگنال:\n\n$preview", [
            'inline_keyboard' => [[['text' => '✏️ ویرایش قالب', 'callback_data' => 'admin:template:edit']], $this->backRow()],
        ]);
    }

    // -- ✏️ Text Management ------------------------------------------------
    private function renderTexts(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'edit' && isset($parts[3])) {
            $key = $parts[3];
            $this->states->set($userId, 'awaiting_text_input', ['key' => $key]);
            $this->render($chatId, $messageId, "✏️ ویرایش متن «$key»\n\nپیام جدید را (با فرمت/ایموجی اختصاصی دلخواه) ارسال کنید.", ['inline_keyboard' => [$this->backRow('admin:texts')]]);
            return;
        }

        $keys = $this->texts->listKeys();
        $keyboard = [];
        foreach ($keys as $key) {
            $keyboard[] = [['text' => $key, 'callback_data' => "admin:texts:edit:$key"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "✏️ مدیریت متن‌ها\n\nیکی از متن‌ها را برای ویرایش انتخاب کنید:", ['inline_keyboard' => $keyboard]);
    }

    // -- 🎯 Signal Settings -----------------------------------------------
    private function renderSignalSettings(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'mode') {
            $current = $this->getSetting('run_mode', Config::runMode()->value);
            $new = $current === 'LIVE' ? 'DRY_RUN' : 'LIVE';
            $this->setSetting('run_mode', $new);
        }

        $mode = $this->getSetting('run_mode', Config::runMode()->value);
        $text = sprintf(
            "🎯 تنظیمات Signal\n\nحالت اجرا: %s\nحداقل امتیاز: %.1f\nCooldown: %d ثانیه\nحداقل RR: %.2f",
            $mode,
            Config::minSignalScore(),
            Config::cooldownSeconds(),
            Config::minRiskReward()
        );
        $keyboard = [
            [['text' => $mode === 'LIVE' ? '🔁 سوییچ به DRY_RUN' : '🔁 سوییچ به LIVE', 'callback_data' => 'admin:signal_settings:mode']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function getSetting(string $key, string $default): string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : (string) $v;
    }

    private function setSetting(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :now)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        )->execute([':k' => $key, ':v' => $value, ':now' => date('Y-m-d H:i:s')]);
    }

    // -- 🧪 Test Signal ---------------------------------------------------
    private function renderTestSignal(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'run') {
            $this->runTestSignal($chatId);
            return;
        }
        $this->render($chatId, $messageId, "🧪 Test Signal\n\nیک سیگنال آزمایشی (بدون ارسال به کانال‌های واقعی مگر در LIVE) تولید و برای شما نمایش داده می‌شود.", [
            'inline_keyboard' => [[['text' => '▶️ اجرای تست', 'callback_data' => 'admin:test_signal:run']], $this->backRow()],
        ]);
    }

    private function runTestSignal(int $chatId): void
    {
        $symbols = (new SymbolRepository())->listActive(null, 1);
        if (empty($symbols)) {
            $this->telegram->sendMessage($chatId, "هیچ نماد فعالی برای تست وجود ندارد. ابتدا Scanner را اجرا کنید.");
            return;
        }
        $target = $symbols[0];
        $adapter = $this->exchangeManager->get($target['exchange']);
        if ($adapter === null) {
            $this->telegram->sendMessage($chatId, "صرافی «{$target['exchange']}» در دسترس نیست.");
            return;
        }

        $store = new MarketDataStore();
        $timeframes = Config::timeframes();
        foreach ($timeframes as $tf) {
            $candles = $this->exchangeManager->withIsolation($target['exchange'], fn($a) => $a->fetchCandles($target['symbol'], $tf, 200));
            if (is_array($candles) && !empty($candles)) {
                $store->candleManager()->upsertMany($target['exchange'], $target['symbol'], $tf, $candles);
            }
        }
        $ticker = $this->exchangeManager->withIsolation($target['exchange'], fn($a) => $a->fetchTicker24h());
        if (is_array($ticker) && isset($ticker[$target['symbol']])) {
            $t = $ticker[$target['symbol']];
            $store->tickerManager()->upsert($target['exchange'], $target['symbol'], $t['lastPrice'], $t['bid'], $t['ask'], $t['volume']);
        }

        $snapshot = $store->buildSnapshot($target['exchange'], $target['symbol'], $timeframes);
        $generator = new SignalGenerator(new IndicatorEngine());

        $mainTf = $timeframes[array_key_last($timeframes)] ?? '1h';
        $signal = $generator->generate($snapshot, $mainTf);

        if ($signal === null) {
            $this->telegram->sendMessage($chatId, "با شرایط فعلی بازار برای {$target['symbol']} سیگنالی معتبر تولید نشد (این طبیعی است — به معنای خرابی نیست).");
            return;
        }

        $signal->status = 'test';
        (new SignalRepository())->updateStatus($signal->id, 'test');

        $formatter = new SignalFormatter();
        $template = $this->texts->get('signal_template');
        $rendered = $formatter->format($signal, $template['text'], $template['entities']);
        $this->telegram->sendMessage($chatId, $rendered['text'], $rendered['entities']);
    }

    // -- 📜 Signal History --------------------------------------------------
    private function renderHistory(int $chatId, int $messageId): void
    {
        $rows = $this->signalRepo->recent(10);
        if (empty($rows)) {
            $this->render($chatId, $messageId, "📜 هنوز سیگنالی ثبت نشده است.", ['inline_keyboard' => [$this->backRow()]]);
            return;
        }
        $lines = ["📜 ۱۰ سیگنال اخیر:\n"];
        foreach ($rows as $r) {
            $lines[] = sprintf('#%d %s %s [%s] امتیاز:%.1f وضعیت:%s', $r['id'], $r['symbol'], $r['direction'], $r['timeframe'], (float) $r['score'], $r['status']);
        }
        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [$this->backRow()]]);
    }

    // -- ❤️ System Health --------------------------------------------------
    private function renderHealth(int $chatId, int $messageId): void
    {
        $exHealth = $this->exchangeManager->healthSnapshot();
        $exLines = [];
        foreach (['binance', 'mexc', 'wallex'] as $ex) {
            $exLines[] = ($exHealth[$ex] ?? '⚪️') . ' ' . ucfirst($ex);
        }

        $telegramOk = ($this->telegram->getMe()['ok'] ?? false) ? '🟢' : '🔴';
        $symbolCount = (new SymbolRepository())->countActive();
        $signalsToday = $this->signalRepo->countToday();
        $activeSignals = $this->signalRepo->countActive();
        $lastScan = (new ScannerRunRepository())->lastRunAt() ?? '-';
        $errCountStmt = Database::pdo()->query("SELECT COUNT(*) FROM logs WHERE level IN ('error','critical') AND created_at >= NOW() - INTERVAL 24 HOUR");
        $errCount = (int) $errCountStmt->fetchColumn();

        $text = "❤️ وضعیت سیستم\n\n"
            . "Worker: " . $this->workerHeartbeatStatus() . "\n"
            . "Telegram: $telegramOk\n"
            . implode("\n", $exLines) . "\n\n"
            . "نمادها: $symbolCount\n"
            . "سیگنال‌های امروز: $signalsToday\n"
            . "سیگنال‌های فعال: $activeSignals\n"
            . "آخرین اسکن: $lastScan\n"
            . "خطاها (۲۴ ساعت اخیر): $errCount";

        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [$this->backRow()]]);
    }

    private function workerHeartbeatStatus(): string
    {
        $v = $this->getSetting('worker_heartbeat', '');
        if ($v === '') {
            return '⚪️ نامشخص';
        }
        return (time() - (int) $v) < 120 ? '🟢' : '🔴';
    }

    /**
     * Handles free-text input while an admin is mid-flow (e.g. entering a
     * channel ID or editing a text/template). Returns true if the message
     * was consumed as state input.
     */
    public function handleStateInput(int $userId, int $chatId, array $message): bool
    {
        $state = $this->states->get($userId);
        if ($state === null) {
            return false;
        }

        $text = (string) ($message['text'] ?? '');
        $entities = $message['entities'] ?? [];

        if ($state['state'] === 'awaiting_channel_id') {
            $this->states->clear($userId);
            if (!preg_match('/^-?\d+$/', trim($text))) {
                $this->telegram->sendMessage($chatId, "آیدی نامعتبر است. لطفاً یک عدد صحیح ارسال کنید.");
                return true;
            }
            $targetChatId = (int) trim($text);
            $access = $this->channels->checkBotAccess($targetChatId);
            $id = $this->channels->add($targetChatId, $userId);
            $chatInfo = $this->telegram->getChat($targetChatId);
            if ($chatInfo['ok'] ?? false) {
                $this->channels->edit($id, [
                    'title' => $chatInfo['result']['title'] ?? null,
                    'username' => $chatInfo['result']['username'] ?? null,
                ]);
            }
            $msg = $access['ok']
                ? "✅ کانال با موفقیت اضافه شد و ربات دسترسی ارسال پیام دارد."
                : "⚠️ کانال ثبت شد اما ربات هنوز ادمین کانال نیست یا اجازه ارسال پیام ندارد (وضعیت: {$access['status']}). پس از افزودن ربات به‌عنوان ادمین، از «بررسی دسترسی ربات» استفاده کنید.";
            $this->telegram->sendMessage($chatId, $msg);
            return true;
        }

        if ($state['state'] === 'awaiting_text_input') {
            $this->states->clear($userId);
            $key = (string) ($state['payload']['key'] ?? '');
            if ($key === '') {
                return true;
            }
            $this->texts->set($key, $text, is_array($entities) ? $entities : [], $userId);
            $this->telegram->sendMessage($chatId, "✅ متن «$key» با حفظ کامل فرمت و ایموجی‌های اختصاصی ذخیره شد.");
            return true;
        }

        return false;
    }
}

// ============================================================================
// SECTION 7 — BOT APPLICATION (update router)
// ============================================================================

final class BotApplication
{
    private ChannelManager $channels;
    private AdminPanel $adminPanel;
    private QuoteManager $quotes;

    public function __construct(private TelegramClient $telegram, private ExchangeManager $exchangeManager)
    {
        $this->channels = new ChannelManager($telegram);
        $this->adminPanel = new AdminPanel($telegram, $exchangeManager);
        $this->quotes = new QuoteManager();
    }

    public function handleUpdate(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->adminPanel->routeCallback($update['callback_query']);
            } elseif (isset($update['my_chat_member'])) {
                $this->handleMyChatMember($update['my_chat_member']);
            }
        } catch (Throwable $e) {
            Logger::error('bot', 'update handling failed', ['error' => $e->getMessage()]);
        }
    }

    private function handleMessage(array $message): void
    {
        $userId = (int) ($message['from']['id'] ?? 0);
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = (string) ($message['text'] ?? '');
        $this->touchUser($message['from'] ?? []);

        if (AdminAuth::isAdmin($userId) && $this->adminPanel->handleStateInput($userId, $chatId, $message)) {
            return;
        }

        if ($text === '/start') {
            $this->replyWithText($chatId, 'welcome');
            return;
        }

        if ($text === '/help') {
            $this->replyWithText($chatId, 'help');
            return;
        }

        if ($text === '/panel' || $text === '/admin') {
            if (!AdminAuth::isAdmin($userId)) {
                $this->replyWithText($chatId, 'error');
                return;
            }
            $this->adminPanel->sendMainMenu($chatId);
            return;
        }
    }

    private function replyWithText(int $chatId, string $key): void
    {
        $texts = new TextFormatManager();
        $rendered = $texts->render($key);
        if ($rendered['text'] === '') {
            return;
        }
        $this->telegram->sendMessage($chatId, $rendered['text'], $rendered['entities']);
    }

    private function handleMyChatMember(array $update): void
    {
        $chat = $update['chat'] ?? [];
        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId === 0) {
            return;
        }
        $existing = $this->channels->findByChatId($chatId);
        if ($existing === null) {
            return; // Only track chats an admin explicitly added.
        }
        try {
            $this->channels->checkBotAccess($chatId);
        } catch (Throwable $e) {
            Logger::warning('bot', 'chat member update handling failed', ['error' => $e->getMessage()]);
        }
    }

    private function touchUser(array $from): void
    {
        $tgId = (int) ($from['id'] ?? 0);
        if ($tgId === 0 || ($from['is_bot'] ?? false)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'INSERT INTO users (telegram_user_id, username, first_name, last_name, language_code, is_bot, first_seen_at, last_seen_at)
             VALUES (:id, :u, :f, :l, :lang, 0, :now1, :now2)
             ON DUPLICATE KEY UPDATE username = VALUES(username), first_name = VALUES(first_name),
                last_name = VALUES(last_name), language_code = VALUES(language_code), last_seen_at = VALUES(last_seen_at)'
        )->execute([
            ':id' => $tgId, ':u' => $from['username'] ?? null, ':f' => $from['first_name'] ?? null,
            ':l' => $from['last_name'] ?? null, ':lang' => $from['language_code'] ?? null, ':now1' => $now, ':now2' => $now,
        ]);
    }
}

// ============================================================================
// SECTION 8 — WEBHOOK ENTRYPOINT
// (Only active when this file is hit directly by Telegram's webhook, i.e.
//  served by php-fpm/nginx. When required from worker.php this section is
//  inert — worker.php drives its own loop and never receives webhooks.)
// ============================================================================

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'bot.php') {
    try {
        Database::migrate();

        $secret = Config::telegramWebhookSecret();
        if ($secret !== '') {
            $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
            if (!hash_equals($secret, (string) $incoming)) {
                http_response_code(403);
                exit;
            }
        }

        $raw = file_get_contents('php://input') ?: '';
        $update = json_decode($raw, true);
        if (!is_array($update)) {
            http_response_code(400);
            exit;
        }

        $telegram = new TelegramClient();
        $exchangeManager = new ExchangeManager();
        $app = new BotApplication($telegram, $exchangeManager);
        $app->handleUpdate($update);

        http_response_code(200);
        echo 'OK';
    } catch (Throwable $e) {
        Logger::critical('bot', 'webhook fatal error', ['error' => $e->getMessage()]);
        http_response_code(200); // Ack to Telegram regardless, to avoid retry storms; error is logged.
        echo 'OK';
    }
}
