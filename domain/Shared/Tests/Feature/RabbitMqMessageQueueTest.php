<?php

declare(strict_types=1);

use Domain\Shared\Queue\DeferredCall;
use Domain\Shared\Queue\MessageDispatcher;
use Domain\Shared\Queue\RabbitMqMessageQueue;
use Domain\Shared\Tests\Fixtures\QueueTarget;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Integration test against a real RabbitMQ. It skips when the broker is unreachable, so the
 * suite stays green where there is none (CI without a broker) and exercises the adapter where
 * there is one. Each test owns a unique queue and deletes it afterwards.
 */

/** Thrown by a consumer handler to break out of the adapter's blocking consume loop. */
class StopConsuming extends RuntimeException {}

function rabbitConfig(): array
{
    return config('queue.connections.rabbitmq.hosts.0');
}

function freshAdapter(): RabbitMqMessageQueue
{
    $c = rabbitConfig();

    return new RabbitMqMessageQueue((string) $c['host'], (int) $c['port'], (string) $c['user'], (string) $c['password']);
}

/**
 * basic_publish does not wait for the broker to enqueue, so a basic_get on another connection can
 * race ahead of it. Poll until the message lands (or fail fast on a real problem) instead of
 * reading once and flaking.
 */
function getMessageOrFail(object $channel, string $queue, float $timeout = 5.0): AMQPMessage
{
    $deadline = microtime(true) + $timeout;

    do {
        $message = $channel->basic_get($queue);
        if ($message !== null) {
            return $message;
        }
        usleep(50_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("No message on {$queue} within {$timeout}s");
}

beforeEach(function () {
    $c = rabbitConfig();

    try {
        // read_write_timeout guards the verification channel; a broker that is down skips the test.
        $this->connection = new AMQPStreamConnection(
            (string) $c['host'], (int) $c['port'], (string) $c['user'], (string) $c['password'],
            read_write_timeout: 5,
        );
    } catch (\Throwable $e) {
        $this->markTestSkipped('RabbitMQ is not reachable: '.$e->getMessage());
    }

    $this->raw = $this->connection->channel();
    $this->queueName = 'test_'.bin2hex(random_bytes(6));
    QueueTarget::reset();
});

afterEach(function () {
    if (isset($this->raw)) {
        try {
            $this->raw->queue_delete($this->queueName);
        } catch (\Throwable) {
        }
    }

    if (isset($this->connection)) {
        try {
            $this->connection->close();
        } catch (\Throwable) {
        }
    }
});

it('publishes the encoded deferred call onto the queue', function () {
    // Given
    $call = new DeferredCall(QueueTarget::class, 'handledStatically', [1, 'two']);

    // When
    freshAdapter()->publish($this->queueName, $call);

    // Then
    $this->raw->queue_declare($this->queueName, false, true, false, false);
    $message = getMessageOrFail($this->raw, $this->queueName);

    $decoded = DeferredCall::decode($message->getBody());
    expect($decoded->class)->toBe(QueueTarget::class);
    expect($decoded->method)->toBe('handledStatically');
    expect($decoded->args)->toBe([1, 'two']);
    $message->ack();
});

it('persists published messages so they survive a broker restart', function () {
    // Given
    freshAdapter()->publish($this->queueName, new DeferredCall(QueueTarget::class, 'handledStatically'));

    // When
    $this->raw->queue_declare($this->queueName, false, true, false, false);
    $message = getMessageOrFail($this->raw, $this->queueName);

    // Then
    expect((int) $message->get('delivery_mode'))->toBe(AMQPMessage::DELIVERY_MODE_PERSISTENT);
    $message->ack();
});

it('delivers a published message to a consumer and dispatches it to the target', function () {
    // Given
    $adapter = freshAdapter();
    $adapter->publish($this->queueName, new DeferredCall(QueueTarget::class, 'handledStatically', [7]));
    $dispatcher = app(MessageDispatcher::class);

    // When
    // waitTimeout bounds the blocking loop: if no message arrives, the wait times out and the
    // assertion below fails, instead of hanging the suite forever.
    try {
        $adapter->consume($this->queueName, function (AMQPMessage $message) use ($dispatcher) {
            $dispatcher->dispatch($message->getBody());
            $message->ack();
            throw new StopConsuming();
        }, waitTimeout: 5);
    } catch (StopConsuming) {
        // the message was consumed; break the otherwise-blocking loop
    }

    // Then
    expect(QueueTarget::$calls)->toHaveCount(1);
    expect(QueueTarget::$calls[0]['args'])->toBe([7]);
});

it('reports zero consumers on a fresh queue', function () {
    // Given / When
    $count = freshAdapter()->consumerCount($this->queueName);

    // Then
    expect($count)->toBe(0);
});
