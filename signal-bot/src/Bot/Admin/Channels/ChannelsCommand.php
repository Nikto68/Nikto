<?php

declare(strict_types=1);

namespace App\Bot\Admin\Channels;

use App\Bot\CommandHandlerInterface;
use App\Bot\UpdateContext;

final class ChannelsCommand implements CommandHandlerInterface
{
    public function __construct(
        private readonly ChannelsCallbackHandler $channelsMenu,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function handle(UpdateContext $context): void
    {
        $this->channelsMenu->renderList($context);
    }
}
