<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Database\Repositories\ChannelRepository;
use App\Telegram\Exceptions\TelegramApiException;

/**
 * The "➕ افزودن کانال" flow (spec #16): resolve whatever the admin typed
 * (numeric chat id or @username) to a real chat, confirm the bot is an
 * admin there with permission to post, then persist it. Nothing here talks
 * to the strategy/signal engine — TelegramSender::sendMessage(channel.chat_id, ...)
 * is all a channel needs to receive signals.
 */
final class ChannelManager
{
    public function __construct(
        private readonly TelegramSender $sender,
        private readonly ChannelRepository $channels,
    ) {
    }

    /**
     * @return array{success: bool, message: string, channel?: array<string, mixed>}
     */
    public function addChannel(string $identifier, int $addedByAdminId): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'شناسه کانال نمی‌تواند خالی باشد.'];
        }

        try {
            $chat = $this->sender->getChat($this->normalizeIdentifier($identifier));
        } catch (TelegramApiException) {
            return ['success' => false, 'message' => 'کانال یافت نشد. ربات باید حداقل یک بار در کانال عضو شده باشد.'];
        }

        $chatId = (int) ($chat['id'] ?? 0);
        $type = (string) ($chat['type'] ?? '');

        if ($chatId === 0 || !in_array($type, ['channel', 'group', 'supergroup'], true)) {
            return ['success' => false, 'message' => 'این شناسه به یک کانال یا گروه معتبر اشاره نمی‌کند.'];
        }

        [$botIsAdmin, $botCanPost] = $this->checkBotPermissions($chatId);

        if (!$botIsAdmin) {
            return [
                'success' => false,
                'message' => 'ربات باید ابتدا در این کانال به عنوان ادمین اضافه شود (با دسترسی ارسال پیام).',
            ];
        }

        $existing = $this->channels->findByChatId($chatId);
        if ($existing !== null) {
            $this->channels->updatePermissions((int) $existing['id'], $botIsAdmin, $botCanPost);

            return ['success' => true, 'message' => 'این کانال قبلاً اضافه شده بود؛ اطلاعات بروزرسانی شد.', 'channel' => $this->channels->find((int) $existing['id'])];
        }

        $id = $this->channels->create(
            chatId: $chatId,
            username: isset($chat['username']) ? '@' . $chat['username'] : null,
            title: $chat['title'] ?? null,
            type: $type,
            botIsAdmin: $botIsAdmin,
            botCanPost: $botCanPost,
            addedByAdminId: $addedByAdminId,
        );

        return ['success' => true, 'message' => 'کانال با موفقیت اضافه شد.', 'channel' => $this->channels->find((int) $id)];
    }

    /**
     * @return array{0: bool, 1: bool} [botIsAdmin, botCanPost]
     */
    public function checkBotPermissions(int $chatId): array
    {
        $me = $this->sender->getMe();
        $botUserId = (int) ($me['id'] ?? 0);

        try {
            $member = $this->sender->getChatMember($chatId, $botUserId);
        } catch (TelegramApiException) {
            return [false, false];
        }

        $status = (string) ($member['status'] ?? '');
        $isAdmin = in_array($status, ['administrator', 'creator'], true);
        $canPost = $isAdmin && (bool) ($member['can_post_messages'] ?? true);

        return [$isAdmin, $canPost];
    }

    public function refreshPermissions(int $channelId): bool
    {
        $channel = $this->channels->find($channelId);
        if ($channel === null) {
            return false;
        }

        [$isAdmin, $canPost] = $this->checkBotPermissions((int) $channel['chat_id']);
        $this->channels->updatePermissions($channelId, $isAdmin, $canPost);

        return $isAdmin && $canPost;
    }

    private function normalizeIdentifier(string $identifier): int|string
    {
        if (preg_match('/^-?\d+$/', $identifier) === 1) {
            return (int) $identifier;
        }

        return str_starts_with($identifier, '@') ? $identifier : '@' . $identifier;
    }
}
