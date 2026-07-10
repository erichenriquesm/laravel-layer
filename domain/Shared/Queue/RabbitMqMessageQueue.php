<?php

declare(strict_types=1);

namespace Domain\Shared\Queue;

use Domain\Shared\Contracts\MessageQueue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * RabbitMQ adapter for the MessageQueue port. Instance state (connection, channel) replaces the
 * old static helper, and the connection is lazy so a plain HTTP request never opens one. Bound as
 * a singleton, so the worker reuses a single connection across its lifetime.
 *
 * The consumer-side methods (consume, republish, prefetch, consumerCount, commit) are not on the
 * port: they are RabbitMQ runtime mechanics the worker drives, not something the domain enqueues.
 */
final class RabbitMqMessageQueue implements MessageQueue
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private ?string $declaredQueue = null;

    /** @var array<string, int> */
    private array $consumerCounts = [];

    private bool $dirty = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
    ) {}

    public function publish(string $queue, DeferredCall $call): void
    {
        $message = new AMQPMessage($call->encode(), [
            'content-type'  => 'application/php-serialized',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->republish($queue, $message);
    }

    public function republish(string $queue, AMQPMessage $message): void
    {
        $this->declareQueue($queue);
        $this->channel()->basic_publish($message, '', $queue);
        $this->dirty = true;
    }

    /**
     * @param callable(AMQPMessage): void $handler
     */
    public function consume(string $queue, callable $handler): void
    {
        $this->declareQueue($queue);

        $this->channel()->basic_consume(
            $queue,
            '',     # consumer tag: empty lets the broker generate a unique one
            false,  # no_local
            false,  # no_ack: the handler acknowledges the message itself
            false,  # exclusive: other consumers may read this queue
            false,  # nowait
            static fn (AMQPMessage $message) => $handler($message),
        );

        while ($this->channel()->is_consuming()) {
            $this->channel()->wait();
        }
    }

    public function prefetch(int $count): void
    {
        # prefetchSize 0 = no byte limit; global false = per-consumer.
        $this->channel()->basic_qos(0, $count, false);
    }

    public function consumerCount(string $queue): int
    {
        $this->declareQueue($queue);

        return $this->consumerCounts[$queue] ?? 0;
    }

    public function commit(): void
    {
        if (! $this->dirty) {
            return;
        }

        $this->channel()->publish_batch();
        $this->dirty = false;
    }

    private function declareQueue(string $queue): void
    {
        # Redeclaring on every message is wasteful, so the last declared queue is remembered.
        if ($this->declaredQueue === $queue) {
            return;
        }

        [$name, , $consumers] = $this->channel()->queue_declare(
            $queue,
            false,   # passive: do not fail when the queue is missing
            true,    # durable: survives a broker restart
            false,   # exclusive: other consumers may read this queue
            false,   # auto_delete: keep the queue when the last consumer leaves
        );

        $this->consumerCounts[$name ?? $queue] = $consumers ?? 0;
        $this->declaredQueue = $queue;
    }

    private function channel(): AMQPChannel
    {
        return $this->channel ??= $this->openChannel();
    }

    private function openChannel(): AMQPChannel
    {
        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);

        return $this->connection->channel();
    }
}
