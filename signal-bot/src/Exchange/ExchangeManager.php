<?php

declare(strict_types=1);

namespace App\Exchange;

use App\Core\Config;
use App\Database\Repositories\ExchangeRepository;
use App\Exchange\Exceptions\ExchangeException;
use App\Logger\LoggerFactory;
use React\Http\Browser;

/**
 * Builds and hands out one ExchangeInterface instance per entry in
 * config/exchanges.php. This is the ONLY place that reads that config file
 * and instantiates a concrete adapter class — MarketScanner and everything
 * downstream only ever ask ExchangeManager for "all enabled exchanges" or
 * "the exchange called X" (spec #4/#30: a dead exchange never touches the
 * others, because nothing outside this class knows adapters exist).
 *
 * config/exchanges.php's `enabled` is the deploy-time default (from
 * .env); the `exchanges` DB row's `enabled` column is the live,
 * admin-panel-toggleable switch (spec #22 "Database-driven") and wins
 * once a row exists — which migration 008 seeds for all three on install.
 */
final class ExchangeManager
{
    /** @var array<string, ExchangeInterface> */
    private array $instances = [];

    public function __construct(
        private readonly Config $config,
        private readonly LoggerFactory $loggerFactory,
        private readonly Browser $http,
        private readonly ExchangeRepository $exchangeRepository,
    ) {
    }

    /**
     * @return ExchangeInterface[]
     */
    public function all(): array
    {
        $exchanges = [];
        foreach (array_keys((array) $this->config->get('exchanges', [])) as $code) {
            if ($this->isEnabled($code)) {
                $exchanges[$code] = $this->get($code);
            }
        }

        return $exchanges;
    }

    public function get(string $code): ExchangeInterface
    {
        if (isset($this->instances[$code])) {
            return $this->instances[$code];
        }

        $exchangeConfig = (array) $this->config->get("exchanges.{$code}");
        if ($exchangeConfig === []) {
            throw new ExchangeException("Unknown exchange [{$code}].", $code);
        }

        $adapterClass = (string) ($exchangeConfig['adapter'] ?? '');
        if ($adapterClass === '' || !is_subclass_of($adapterClass, ExchangeInterface::class)) {
            throw new ExchangeException("Exchange [{$code}] has no valid adapter configured.", $code);
        }

        $rateLimiter = new RateLimiter(
            (int) ($exchangeConfig['rate_limit']['max_requests'] ?? 100),
            (int) ($exchangeConfig['rate_limit']['per_seconds'] ?? 60),
        );

        /** @var ExchangeInterface $instance */
        $instance = new $adapterClass(
            $this->http,
            $rateLimiter,
            $this->loggerFactory->channel('exchange'),
            $exchangeConfig,
        );

        return $this->instances[$code] = $instance;
    }

    public function isEnabled(string $code): bool
    {
        $row = $this->exchangeRepository->findByCode($code);
        if ($row !== null) {
            return (int) $row['enabled'] === 1;
        }

        return (bool) $this->config->get("exchanges.{$code}.enabled", false);
    }
}
