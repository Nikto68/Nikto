<?php

declare(strict_types=1);

namespace App\Bot\Admin\Channels;

use App\Bot\UpdateContext;
use App\Database\Repositories\ChannelRepository;
use Psr\Log\LoggerInterface;

/**
 * Keeps channels.bot_is_admin / bot_can_post in sync automatically whenever
 * Telegram tells us the bot's membership/permissions changed (promoted,
 * demoted, kicked, ...), instead of only finding out the next time a
 * signal fails to send.
 */
final class ChatMemberUpdateHandler
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $channel = $this->channels->findByChatId($context->chatId);
        if ($channel === null) {
            return;
        }

        $newMember = $context->raw['my_chat_member']['new_chat_member'] ?? [];
        $status = (string) ($newMember['status'] ?? '');
        $isAdmin = in_array($status, ['administrator', 'creator'], true);
        $canPost = $isAdmin && (bool) ($newMember['can_post_messages'] ?? true);

        $this->channels->updatePermissions((int) $channel['id'], $isAdmin, $canPost);
        $this->logger->info('Channel bot permissions changed', [
            'channel_id' => $channel['id'],
            'status' => $status,
            'can_post' => $canPost,
        ]);
    }
}
