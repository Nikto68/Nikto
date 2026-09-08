<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Database;
use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\JobRepository;
use App\Database\Repositories\SignalRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Database\Repositories\SymbolRepository;
use App\Logger\LoggerFactory;
use App\Queue\Jobs\SendSignalToChannelJob;
use App\Queue\Queue;
use App\Queue\QueueInterface;
use App\Queue\QueueWorker;
use App\Signal\SignalFormatter;
use App\Telegram\TelegramSender;

final class QueueServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(JobRepository::class, static fn (Application $app): JobRepository => new JobRepository($app->get(Database::class)));

        $app->singleton(QueueInterface::class, static function (Application $app): Queue {
            return new Queue($app->get(JobRepository::class));
        });
        $app->singleton(Queue::class, static fn (Application $app): Queue => $app->get(QueueInterface::class));

        $app->singleton(SendSignalToChannelJob::class, static function (Application $app): SendSignalToChannelJob {
            return new SendSignalToChannelJob(
                $app->get(SignalRepository::class),
                $app->get(ChannelRepository::class),
                $app->get(SymbolRepository::class),
                $app->get(ExchangeRepository::class),
                $app->get(SignalTemplateRepository::class),
                $app->get(SignalFormatter::class),
                $app->get(TelegramSender::class),
            );
        });

        $app->singleton(QueueWorker::class, static function (Application $app): QueueWorker {
            $worker = new QueueWorker($app->get(JobRepository::class), $app->get(LoggerFactory::class)->channel('signal'));
            $worker->registerJob('send_signal_to_channel', $app->get(SendSignalToChannelJob::class));

            return $worker;
        });
    }
}
