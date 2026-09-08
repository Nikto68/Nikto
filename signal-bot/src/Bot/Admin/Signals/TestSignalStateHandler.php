<?php

declare(strict_types=1);

namespace App\Bot\Admin\Signals;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeManager;
use App\Indicators\IndicatorManager;
use App\Market\CandleManager;
use App\Signal\SignalFormatter;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\StrategyEngine;
use App\Strategy\SupportResistance;
use App\Support\Timeframe;
use App\Telegram\TelegramSender;
use Throwable;

use function React\Async\await;

/**
 * Runs the real pipeline — candle fetch, indicators, zones, order
 * blocks, FVGs, every active strategy — synchronously for one symbol and
 * renders the result without persisting a signal or dispatching to any
 * channel (spec #39: "بدون ارسال واقعی ... Strategy Conditions, Score,
 * Final Signal, Rendered Telegram Message, Entities"). Zones/OB/FVG ARE
 * written to their tables (same as a real scan cycle would) so
 * StrategyEngine reads current data — only SignalEngine::process()
 * (persist-a-signal-and-maybe-dispatch) is skipped.
 */
final class TestSignalStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly SymbolRepository $symbols,
        private readonly ExchangeManager $exchangeManager,
        private readonly CandleManager $candleManager,
        private readonly IndicatorManager $indicators,
        private readonly SupportResistance $supportResistance,
        private readonly OrderBlock $orderBlock,
        private readonly OrderBlockRepository $orderBlockRepository,
        private readonly FVG $fvg,
        private readonly FvgRepository $fvgRepository,
        private readonly StrategyEngine $strategyEngine,
        private readonly SignalFormatter $formatter,
        private readonly SettingsRepository $settings,
        private readonly TelegramSender $sender,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function handle(UpdateContext $context, array $stateContext): void
    {
        if ($context->chatId === null || $context->userId === null) {
            return;
        }

        $this->states->clear($context->userId);

        [$exchangeCode, $symbolCode] = $this->parseInput(trim($context->text));
        $matches = $this->symbols->findAcrossExchanges($symbolCode);

        if ($exchangeCode !== null) {
            $matches = array_values(array_filter($matches, static fn (array $m): bool => $m['exchange_code'] === $exchangeCode));
        }

        if ($matches === []) {
            $this->sender->sendMessage($context->chatId, "نماد «{$symbolCode}» در هیچ صرافی فعالی پیدا نشد.");

            return;
        }

        $symbolRow = $matches[0];
        $this->sender->sendMessage($context->chatId, "در حال اجرای پایپ‌لاین برای {$symbolRow['exchange_code']}:{$symbolRow['symbol']} ...");

        try {
            $this->runPipeline($context, $symbolRow);
        } catch (Throwable $e) {
            $this->sender->sendMessage($context->chatId, "خطا در اجرای تست: {$e->getMessage()}");
        }
    }

    /**
     * @param array<string, mixed> $symbolRow
     */
    private function runPipeline(UpdateContext $context, array $symbolRow): void
    {
        $symbolId = (int) $symbolRow['id'];
        $exchangeCode = (string) $symbolRow['exchange_code'];
        $exchangeSymbol = (string) $symbolRow['symbol'];
        $exchange = $this->exchangeManager->get($exchangeCode);

        $timeframes = $this->defaultTimeframes();
        $htfTimeframe = Timeframe::largest($timeframes);

        foreach ($timeframes as $tf) {
            await($this->candleManager->ensureFresh($exchange, $symbolId, $exchangeSymbol, $tf, 300));
        }

        $indicatorLines = [];
        $zoneCount = 0;
        $obCount = 0;
        $fvgCount = 0;

        $htfCandles = $htfTimeframe !== null ? $this->candleManager->recent($symbolId, $htfTimeframe, 300) : [];

        foreach ($timeframes as $tf) {
            $candles = $this->candleManager->recent($symbolId, $tf, 300);
            if ($candles === []) {
                continue;
            }

            $rsi = $this->indicators->latest('RSI', $candles, ['period' => 14]);
            $ema20 = $this->indicators->latest('EMA', $candles, ['period' => 20]);
            $atr = $this->indicators->latest('ATR', $candles, ['period' => 14]);
            $indicatorLines[] = sprintf(
                '%s: RSI14=%s EMA20=%s ATR14=%s',
                $tf,
                $this->fmt($rsi['value'] ?? null),
                $this->fmt($ema20['value'] ?? null),
                $this->fmt($atr['value'] ?? null),
            );

            $zones = $this->supportResistance->detectAndStore($symbolId, $candles, $tf, $tf === $htfTimeframe ? [] : $htfCandles);
            $zoneCount += count($zones);

            $blocks = $this->orderBlock->detect($candles, $tf);
            $this->orderBlockRepository->upsertBatch($symbolId, $blocks);
            $obCount += count($blocks);

            $gaps = $this->fvg->detect($candles, $tf);
            $this->fvgRepository->upsertBatch($symbolId, $gaps);
            $fvgCount += count($gaps);
        }

        $priceCandles = $htfCandles !== [] ? $htfCandles : $this->candleManager->recent($symbolId, $timeframes[0], 1);
        $lastCandle = end($priceCandles);
        $currentPrice = $lastCandle !== false ? (float) $lastCandle['close'] : 0.0;
        $candidates = $this->strategyEngine->evaluateSymbol($exchangeCode, $symbolId, $exchangeSymbol, $currentPrice);

        $lines = [
            "🧪 نتیجه تست — {$exchangeCode}:{$exchangeSymbol}",
            '',
            '📈 اندیکاتورها:',
            ...$indicatorLines,
            '',
            "🗺 نواحی S/R شناسایی‌شده: {$zoneCount}",
            "🧱 Order Blockهای فعال: {$obCount}",
            "📊 FVGهای شناسایی‌شده: {$fvgCount}",
            '',
        ];

        if ($candidates === []) {
            $lines[] = 'هیچ استراتژی فعالی برای این نماد سیگنالی تولید نکرد (یا هنوز هیچ استراتژی‌ای فعال/ثبت نشده است).';
            $this->sender->sendMessage($context->chatId, implode("\n", $lines));

            return;
        }

        $lines[] = '✅ ' . count($candidates) . ' Signal Candidate تولید شد:';
        $this->sender->sendMessage($context->chatId, implode("\n", $lines));

        foreach ($candidates as $candidate) {
            $preview = [
                'direction' => $candidate->direction,
                'timeframe' => $candidate->timeframe,
                'entry' => $candidate->entry,
                'stop_loss' => $candidate->stopLoss,
                'take_profit_1' => $candidate->takeProfits[0] ?? null,
                'take_profit_2' => $candidate->takeProfits[1] ?? null,
                'take_profit_3' => $candidate->takeProfits[2] ?? null,
                'risk_reward' => $candidate->riskReward(),
                'score' => $candidate->score,
                'confidence' => null,
                'reasons' => $candidate->reasons,
            ];

            $placeholders = $this->formatter->placeholders($preview, $exchangeSymbol, $exchangeCode);
            $rendered = $this->formatter->render(['text' => "{direction} {symbol}\nEntry: {entry}\nSL: {stop_loss}\nTP1: {tp1} | TP2: {tp2} | TP3: {tp3}\nR:R {risk_reward} | Score: {score}/100\n\n{reasons}", 'entities' => []], $placeholders);

            $this->sender->sendMessage($context->chatId, "پیام نمونه (Rendered Preview):\n\n" . $rendered['text'], $rendered['entities']);
        }
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function parseInput(string $input): array
    {
        if (str_contains($input, ':')) {
            [$exchange, $symbol] = explode(':', $input, 2);

            return [strtolower(trim($exchange)), strtoupper(trim($symbol))];
        }

        return [null, strtoupper($input)];
    }

    /**
     * @return string[]
     */
    private function defaultTimeframes(): array
    {
        $default = $this->settings->get('default_timeframes', ['15m', '1h', '4h']);

        return is_array($default) && $default !== [] ? $default : ['15m', '1h', '4h'];
    }

    private function fmt(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 4);
    }
}
