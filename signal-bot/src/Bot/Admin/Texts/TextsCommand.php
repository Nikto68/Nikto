<?php

declare(strict_types=1);

namespace App\Bot\Admin\Texts;

use App\Bot\CommandHandlerInterface;
use App\Bot\UpdateContext;

final class TextsCommand implements CommandHandlerInterface
{
    public function __construct(
        private readonly TextsCallbackHandler $textsMenu,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function handle(UpdateContext $context): void
    {
        $this->textsMenu->renderList($context);
    }
}
