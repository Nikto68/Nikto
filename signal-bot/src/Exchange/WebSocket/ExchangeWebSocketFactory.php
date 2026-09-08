<?php

declare(strict_types=1);

namespace App\Exchange\WebSocket;

use App\Core\Config;
use App\Exchange\ExchangeInterface;
use App\Logger\LoggerFactory;

/**
 * The only place that decides "does this exchange get a real WebSocket
 * client or the REST-polling shim". Adding a real client for MEXC/Wallex
 * later is a one-line change here — MarketWorker never changes.
 */
final class ExchangeWebSocketFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly LoggerFactory $loggerFactory,
    ) {
    }

    public function make(ExchangeInterface $exchange): ExchangeWebSocketInterface
    {
        $logger = $this->loggerFactory->channel('exchange');

        if ($exchange->supportsWebSocket() && $exchange->code() === 'binance') {
            $wsBase = (string) $this->config->get('exchanges.binance.ws_base');

            return new BinanceWebSocketClient($wsBase, $logger);
        }

        return new PollingWebSocketClient($exchange, $logger);
    }
}
