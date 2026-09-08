<?php

declare(strict_types=1);

namespace App\Bot;

/**
 * One handler per bot command. Register new commands in bootstrap.php via
 * Bot::registerCommand() — Bot itself never grows a new "if" branch per
 * command (Open/Closed).
 */
interface CommandHandlerInterface
{
    public function handle(UpdateContext $context): void;

    /**
     * If true, Bot rejects the update with the `error_permission` text
     * before calling handle() when the sender is not in the admins table.
     */
    public function requiresAdmin(): bool;
}
