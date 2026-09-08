<?php

declare(strict_types=1);

namespace App\Signal;

use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SignalRepository;
use App\Database\Repositories\SymbolRepository;
use App\Queue\QueueInterface;
use Psr\Log\LoggerInterface;

/**
 * The last stop before Telegram (spec #11's pipeline: "... Signal
 * Candidate -> Validation -> Signal -> Telegram"). Validates, persists,
 * and — LIVE mode only — fans the signal out to every channel whose
 * filters match, as queue jobs (never a direct Telegram call, spec #29).
 * In DRY_RUN (spec #40) everything up to and including persistence still
 * happens, so Test Signal/Signal History reflect real pipeline output —
 * only the channel dispatch step is skipped.
 */
final class SignalEngine
{
    public function __construct(
        private readonly SignalValidator $validator,
        private readonly SignalRepository $signals,
        private readonly SymbolRepository $symbols,
        private readonly MarketDataRepository $marketData,
        private readonly ChannelRepository $channels,
        private readonly QueueInterface $queue,
        private readonly SettingsRepository $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>|null the persisted signal row, or null if rejected
     */
    public function process(SignalCandidate $candidate): ?array
    {
        $symbolRow = $this->symbols->find($candidate->symbolId);
        if ($symbolRow === null) {
            $this->logger->warning('Signal candidate for unknown symbol', ['symbol_id' => $candidate->symbolId]);

            return null;
        }

        $marketDataRow = $this->marketData->find($candidate->symbolId);
        $validation = $this->validator->validate($candidate, $symbolRow, $marketDataRow);

        if (!$validation['valid']) {
            $this->logger->info('Signal candidate rejected by validation', [
                'fingerprint' => $candidate->fingerprint(),
                'failures' => $validation['failures'],
            ]);

            return null;
        }

        $mode = (string) $this->settings->get('mode', 'DRY_RUN');
        $created = $this->signals->create($candidate, $mode);
        $signalId = $created['id'];

        if ($mode !== 'LIVE') {
            $this->logger->info('Signal created in DRY_RUN, not dispatched', ['id' => $signalId, 'fingerprint' => $candidate->fingerprint()]);

            return $this->signals->find($signalId);
        }

        $this->dispatchToChannels($signalId, $candidate);

        return $this->signals->find($signalId);
    }

    private function dispatchToChannels(int $signalId, SignalCandidate $candidate): void
    {
        $matchingChannels = $this->channels->matchingChannels($candidate->score, $candidate->direction, $candidate->strategyId);

        if ($matchingChannels === []) {
            $this->logger->info('Signal created but no channel filter matched it', ['id' => $signalId]);

            return;
        }

        foreach ($matchingChannels as $channel) {
            $this->queue->push('send_signal_to_channel', [
                'signal_id' => $signalId,
                'channel_id' => (int) $channel['id'],
            ]);
        }

        $this->signals->recordEvent($signalId, 'sent', ['channel_count' => count($matchingChannels)]);
    }
}
