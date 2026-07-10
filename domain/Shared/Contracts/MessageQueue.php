<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

use Domain\Shared\Queue\DeferredCall;

/**
 * Driven port: the domain owns this interface and calls it to enqueue work; an adapter reaches
 * the actual broker. It is deliberately the one output port in the project — persistence stays
 * Eloquent-direct, but the message queue is worth abstracting so producers never name a broker.
 */
interface MessageQueue
{
    public function publish(string $queue, DeferredCall $call): void;
}
