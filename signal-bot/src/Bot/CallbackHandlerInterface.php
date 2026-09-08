<?php

declare(strict_types=1);

namespace App\Bot;

/**
 * One handler per inline-keyboard callback_data namespace (e.g. everything
 * starting with "channels:"). Bot tries each registered handler's
 * supports() in registration order and dispatches to the first match.
 */
interface CallbackHandlerInterface
{
    public function supports(string $callbackData): bool;

    public function handle(UpdateContext $context): void;

    public function requiresAdmin(): bool;
}
