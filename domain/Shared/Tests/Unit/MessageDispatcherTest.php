<?php

declare(strict_types=1);

use Carbon\Carbon;
use Domain\Shared\Queue\DeferredCall;
use Domain\Shared\Queue\MessageDispatcher;
use Domain\Shared\Tests\Fixtures\DependentQueueTarget;
use Domain\Shared\Tests\Fixtures\QueueTarget;

function dispatcher(): MessageDispatcher
{
    return app(MessageDispatcher::class);
}

function encodedCall(string $method = 'handledStatically', array $args = [], ?Carbon $publishedAt = null): string
{
    return (new DeferredCall(QueueTarget::class, $method, $args, $publishedAt))->encode();
}

beforeEach(fn () => QueueTarget::reset());
afterEach(fn () => Carbon::setTestNow());

it('invokes a static target and returns its result without building an instance', function () {
    // Given
    $body = encodedCall('handledStatically');

    // When
    $result = dispatcher()->dispatch($body);

    // Then
    expect($result->value)->toBe('static-result');
    expect(QueueTarget::$instances)->toBe(0);
});

it('builds an instance for a non-static target and invokes it', function () {
    // Given
    $body = encodedCall('handledOnInstance');

    // When
    $result = dispatcher()->dispatch($body);

    // Then
    expect($result->value)->toBe('instance-result');
    expect(QueueTarget::$instances)->toBe(1);
});

it('passes the published args through to the target', function () {
    // Given
    $body = encodedCall('handledStatically', [42, 'foo']);

    // When
    dispatcher()->dispatch($body);

    // Then
    expect(QueueTarget::$calls[0]['args'])->toBe([42, 'foo']);
});

it('reports notDue and does not invoke when the message is not due yet', function () {
    // Given
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
    $body = encodedCall(publishedAt: Carbon::now()); // published now

    // When
    $result = dispatcher()->dispatch($body, delay: 100); // due only at now + 100s

    // Then
    expect($result->notDue)->toBeTrue();
    expect(QueueTarget::$calls)->toBe([]);
});

it('handles the message once the delay has elapsed', function () {
    // Given
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
    $body = encodedCall(publishedAt: Carbon::now()->subSeconds(200)); // published 200s ago

    // When
    $result = dispatcher()->dispatch($body, delay: 100); // due at now - 100s, already past

    // Then
    expect($result->notDue)->toBeFalse();
    expect($result->value)->toBe('static-result');
});

it('reports handled(false) without throwing when the target cannot be resolved', function () {
    // Given
    $body = (new DeferredCall('App\\Does\\NotExist', 'nope'))->encode();

    // When
    $result = dispatcher()->dispatch($body);

    // Then
    expect($result->notDue)->toBeFalse();
    expect($result->value)->toBeFalse();
});

it('resolves an instance target that declares constructor dependencies', function () {
    // Given
    // DependentQueueTarget needs a TargetDependency: `new $class` would fatal, the container injects it
    $body = (new DeferredCall(DependentQueueTarget::class, 'run'))->encode();

    // When
    $result = dispatcher()->dispatch($body);

    // Then
    expect($result->value)->toBe('resolved-via-container');
});
