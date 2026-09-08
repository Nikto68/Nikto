<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\SignalRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Database\Repositories\SymbolRepository;
use App\Queue\JobInterface;
use App\Signal\SignalFormatter;
use App\Telegram\ReplyParameters;
use App\Telegram\TelegramSender;
use RuntimeException;
use Throwable;

/**
 * The only place a signal actually reaches Telegram. Renders the
 * channel's template (falling back to the default template), sends it,
 * and records the per-channel delivery so it can be edited/found again
 * later (spec #15's editMessageText, spec #38's history).
 */
final class SendSignalToChannelJob implements JobInterface
{
    public function __construct(
        private readonly SignalRepository $signals,
        private readonly ChannelRepository $channels,
        private readonly SymbolRepository $symbols,
        private readonly ExchangeRepository $exchanges,
        private readonly SignalTemplateRepository $templates,
        private readonly SignalFormatter $formatter,
        private readonly TelegramSender $sender,
    ) {
    }

    public function handle(array $payload): void
    {
        $signalId = (int) $payload['signal_id'];
        $channelId = (int) $payload['channel_id'];

        $signal = $this->signals->find($signalId);
        $channel = $this->channels->find($channelId);
        if ($signal === null || $channel === null) {
            throw new RuntimeException("Signal [$signalId] or channel [$channelId] no longer exists.");
        }

        $symbol = $this->symbols->find((int) $signal['symbol_id']);
        $exchange = $this->exchanges->find((int) $signal['exchange_id']);

        $template = $this->resolveTemplate($channel);
        $placeholders = $this->formatter->placeholders(
            $signal,
            $symbol['symbol'] ?? '?',
            $exchange['name'] ?? ($exchange['code'] ?? '?'),
        );
        $rendered = $this->formatter->render($template, $placeholders);

        try {
            $message = $this->sender->sendMessage(
                (int) $channel['chat_id'],
                $rendered['text'],
                $rendered['entities'],
                ReplyParameters::fromStored($template),
            );

            $this->signals->recordDelivery($signalId, $channelId, (int) ($message['message_id'] ?? 0) ?: null, 'sent');
        } catch (Throwable $e) {
            $this->signals->recordDelivery($signalId, $channelId, null, 'failed', $e->getMessage());

            throw $e;
        }

        if ($signal['status'] === 'new') {
            $this->signals->setStatus($signalId, 'active', null, 'sent');
        }
    }

    /**
     * @param array<string, mixed> $channel
     * @return array{text: string, entities: array<int, array<string, mixed>>, reply_to_message_id: ?int, quote_text: ?string, quote_entities: array<int, array<string, mixed>>, quote_position: ?int}
     */
    private function resolveTemplate(array $channel): array
    {
        $template = $channel['signal_template_id'] !== null
            ? $this->templates->find((int) $channel['signal_template_id'])
            : null;

        return $template ?? $this->templates->default() ?? [
            'text' => "{direction} {symbol}\nEntry: {entry}\nSL: {stop_loss}\nTP1: {tp1}\nScore: {score}/100",
            'entities' => [],
            'reply_to_message_id' => null,
            'quote_text' => null,
            'quote_entities' => [],
            'quote_position' => null,
        ];
    }
}
