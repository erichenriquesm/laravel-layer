<?php

declare(strict_types=1);

use Carbon\Carbon;
use Domain\Shared\Helpers\Queue;
use Domain\Shared\Tests\Fixtures\QueueTarget;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Only Queue::processMessage is covered here: it takes a ready AMQPMessage and holds the
 * real decision logic (delay sentinel, static vs instance, reflection). Everything that
 * opens an AMQP connection (boot, publish, consume, declareQueue, setQos, commit) needs a
 * live broker and is deliberately left out — see the known-issues note in CLAUDE.md.
 */
function queueMessage(array $overrides = []): AMQPMessage
{
    $body = array_merge([
        '__class'       => QueueTarget::class,
        '__method'      => 'handledStatically',
        '__args'        => [],
        '__publishedAt' => null,
    ], $overrides);

    return new AMQPMessage(serialize($body));
}

beforeEach(fn () => QueueTarget::reset());
afterEach(fn () => Carbon::setTestNow());

it('invokes a static target and returns its result without building an instance', function () {
    // Given
    $message = queueMessage(['__method' => 'handledStatically']);

    // When
    $result = Queue::processMessage($message);

    // Then
    expect($result)->toBe('static-result');
    expect(QueueTarget::$calls)->toHaveCount(1);
    expect(QueueTarget::$instances)->toBe(0); // static path never constructs the class
});

it('builds an instance for a non-static target and invokes it', function () {
    // Given
    $message = queueMessage(['__method' => 'handledOnInstance']);

    // When
    $result = Queue::processMessage($message);

    // Then
    expect($result)->toBe('instance-result');
    expect(QueueTarget::$instances)->toBe(1);
});

it('passes the published args through to the target', function () {
    // Given
    $message = queueMessage(['__method' => 'handledStatically', '__args' => [42, 'foo']]);

    // When
    Queue::processMessage($message);

    // Then
    expect(QueueTarget::$calls[0]['args'])->toBe([42, 'foo']);
});

it('returns the delay sentinel and does not invoke when the message is not due yet', function () {
    // Given
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
    $message = queueMessage(['__publishedAt' => Carbon::now()]); // published now

    // When
    $result = Queue::processMessage($message, delay: 100); // due only at now + 100s

    // Then
    expect($result)->toBe('__delay__');
    expect(QueueTarget::$calls)->toBe([]);
});

it('processes the message once the delay has elapsed', function () {
    // Given
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
    $message = queueMessage(['__publishedAt' => Carbon::now()->subSeconds(200)]); // published 200s ago

    // When
    $result = Queue::processMessage($message, delay: 100); // due at now - 100s, already past

    // Then
    expect($result)->toBe('static-result');
});

it('returns false and does not throw when the target cannot be resolved', function () {
    // Given
    $message = queueMessage(['__class' => 'App\\Does\\NotExist', '__method' => 'nope']);

    // When
    $result = Queue::processMessage($message);

    // Then
    expect($result)->toBeFalse();
});
