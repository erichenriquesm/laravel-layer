<?php

declare(strict_types=1);

namespace Domain\Shared\Providers;

use Domain\Shared\Contracts\MessageQueue;
use Domain\Shared\Queue\RabbitMqMessageQueue;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        # Singleton so the worker reuses one connection; config() (not env()) survives config:cache.
        $this->app->singleton(RabbitMqMessageQueue::class, function () {
            $host = config('queue.connections.rabbitmq.hosts.0', []);

            return new RabbitMqMessageQueue(
                (string) ($host['host'] ?? '127.0.0.1'),
                (int) ($host['port'] ?? 5672),
                (string) ($host['user'] ?? 'guest'),
                (string) ($host['password'] ?? 'guest'),
            );
        });

        $this->app->bind(MessageQueue::class, RabbitMqMessageQueue::class);
    }
}
