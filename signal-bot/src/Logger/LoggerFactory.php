<?php

declare(strict_types=1);

namespace App\Logger;

use App\Core\Config;
use App\Database\Database;
use Closure;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Produces one Monolog channel per storage/logs/<name>.log file
 * (app, telegram, exchange, scanner, signal, error) as required by the
 * logging spec. Every channel also mirrors ERROR-and-above records into
 * error.log so a single file shows every failure across the system.
 */
final class LoggerFactory
{
    private const CHANNELS = ['app', 'telegram', 'exchange', 'scanner', 'signal', 'error'];

    /** @var array<string, LoggerInterface> */
    private array $cache = [];

    private readonly Level $minLevel;

    private readonly SecretRedactor $redactor;

    /** @var Closure(): Database */
    private readonly Closure $databaseResolver;

    /**
     * @param Closure(): Database $databaseResolver lazy — see DatabaseLogHandler
     */
    public function __construct(
        private readonly Config $config,
        ?Closure $databaseResolver = null,
    ) {
        $this->minLevel = $this->resolveLevel((string) $this->config->get('config.log_level', 'debug'));
        $this->redactor = new SecretRedactor($this->collectSecrets());
        $this->databaseResolver = $databaseResolver ?? static function (): never {
            throw new RuntimeException('LoggerFactory has no database resolver configured.');
        };
    }

    public function channel(string $name): LoggerInterface
    {
        if (!in_array($name, self::CHANNELS, true)) {
            $name = 'app';
        }

        return $this->cache[$name] ??= $this->build($name);
    }

    private function build(string $name): Logger
    {
        $storagePath = (string) $this->config->get('config.storage_path');
        $logsDir = $storagePath . '/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0775, true);
        }

        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($format, 'Y-m-d H:i:s', true, true);
        $formatter->includeStacktraces(true);

        $ownHandler = new StreamHandler($logsDir . "/{$name}.log", $this->minLevel);
        $ownHandler->setFormatter($formatter);

        $logger = new Logger($name);
        $logger->pushHandler($ownHandler);
        $logger->pushProcessor($this->redactor);

        if ($name !== 'error') {
            $errorHandler = new StreamHandler($logsDir . '/error.log', Level::Error);
            $errorHandler->setFormatter($formatter);
            $logger->pushHandler($errorHandler);
        }

        $logger->pushHandler(new DatabaseLogHandler($this->databaseResolver));

        return $logger;
    }

    private function resolveLevel(string $level): Level
    {
        return Level::fromName(ucfirst(strtolower($level)));
    }

    /**
     * @return string[]
     */
    private function collectSecrets(): array
    {
        $secrets = [
            (string) $this->config->get('telegram.bot_token', ''),
            (string) $this->config->get('telegram.webhook_secret', ''),
            (string) $this->config->get('database.password', ''),
        ];

        foreach ((array) $this->config->get('exchanges', []) as $exchange) {
            $secrets[] = (string) ($exchange['api_key'] ?? '');
            $secrets[] = (string) ($exchange['api_secret'] ?? '');
        }

        return $secrets;
    }
}
