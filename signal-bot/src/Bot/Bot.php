<?php

declare(strict_types=1);

namespace App\Bot;

use App\Database\Repositories\AdminRepository;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Telegram\TelegramSender;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Pure router: turns a raw Telegram update into an UpdateContext, enforces
 * the admin gate, and dispatches to whichever CommandHandler/CallbackHandler
 * was registered for it. Contains no Telegram/Database/Exchange/Strategy
 * business logic itself (spec #34 — no God class).
 */
final class Bot
{
    /** @var array<string, CommandHandlerInterface> */
    private array $commands = [];

    /** @var CallbackHandlerInterface[] */
    private array $callbackHandlers = [];

    /** @var array<int, callable(UpdateContext): void> */
    private array $myChatMemberHandlers = [];

    /** @var array<string, StateHandlerInterface> */
    private array $stateHandlers = [];

    public function __construct(
        private readonly AdminRepository $admins,
        private readonly TextFormatRepository $texts,
        private readonly TelegramSender $sender,
        private readonly LoggerInterface $logger,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function registerCommand(string $command, CommandHandlerInterface $handler): void
    {
        $this->commands[$command] = $handler;
    }

    public function registerCallbackHandler(CallbackHandlerInterface $handler): void
    {
        $this->callbackHandlers[] = $handler;
    }

    public function registerStateHandler(string $state, StateHandlerInterface $handler): void
    {
        $this->stateHandlers[$state] = $handler;
    }

    /**
     * @param callable(UpdateContext): void $handler
     */
    public function registerMyChatMemberHandler(callable $handler): void
    {
        $this->myChatMemberHandlers[] = $handler;
    }

    /**
     * @param array<string, mixed> $rawUpdate
     */
    public function handle(array $rawUpdate): void
    {
        $context = UpdateContext::fromUpdate($rawUpdate);

        try {
            match ($context->kind) {
                'message', 'edited_message' => $this->handleMessage($context),
                'callback_query' => $this->handleCallbackQuery($context),
                'my_chat_member' => $this->handleMyChatMember($context),
                default => null,
            };
        } catch (Throwable $e) {
            $this->logger->error('Unhandled exception while processing update', [
                'kind' => $context->kind,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function handleMessage(UpdateContext $context): void
    {
        if (!$context->isPrivateChat() || $context->text === '') {
            return;
        }

        if (!str_starts_with($context->text, '/') && $context->userId !== null) {
            $pending = $this->states->get($context->userId);
            if ($pending !== null && isset($this->stateHandlers[$pending['state']])) {
                if ($this->isAdmin($context)) {
                    $this->stateHandlers[$pending['state']]->handle($context, $pending['context']);
                } else {
                    $this->states->clear($context->userId);
                }

                return;
            }
        }

        $command = strtok($context->text, ' ');
        $handler = $this->commands[$command] ?? null;

        if ($handler === null) {
            return;
        }

        if ($handler->requiresAdmin() && !$this->isAdmin($context)) {
            $this->replyWithPermissionError($context);

            return;
        }

        $handler->handle($context);
    }

    private function handleCallbackQuery(UpdateContext $context): void
    {
        if ($context->callbackData === null) {
            return;
        }

        foreach ($this->callbackHandlers as $handler) {
            if (!$handler->supports($context->callbackData)) {
                continue;
            }

            if ($handler->requiresAdmin() && !$this->isAdmin($context)) {
                $this->replyWithPermissionError($context);
                if ($context->callbackQueryId !== null) {
                    $this->sender->answerCallbackQuery($context->callbackQueryId);
                }

                return;
            }

            $handler->handle($context);

            return;
        }

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function handleMyChatMember(UpdateContext $context): void
    {
        foreach ($this->myChatMemberHandlers as $handler) {
            $handler($context);
        }
    }

    private function isAdmin(UpdateContext $context): bool
    {
        return $context->userId !== null && $this->admins->isAdmin($context->userId);
    }

    private function replyWithPermissionError(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $text = $this->texts->find('error_permission');
        $this->sender->sendMessage(
            $context->chatId,
            $text['text'] ?? 'شما اجازه دسترسی به این بخش را ندارید.',
            $text['entities'] ?? [],
        );
    }
}
