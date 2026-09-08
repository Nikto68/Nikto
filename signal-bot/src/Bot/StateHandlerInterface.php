<?php

declare(strict_types=1);

namespace App\Bot;

/**
 * Handles the next message from an admin who is mid-flow (add channel,
 * edit a text, ...). Registered against a state name in Bot; look up the
 * flow that set the state to see what `$stateContext` carries.
 */
interface StateHandlerInterface
{
    /**
     * @param array<string, mixed> $stateContext
     */
    public function handle(UpdateContext $context, array $stateContext): void;
}
