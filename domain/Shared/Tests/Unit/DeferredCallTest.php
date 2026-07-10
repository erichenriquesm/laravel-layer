<?php

declare(strict_types=1);

use Carbon\Carbon;
use Domain\Shared\Queue\DeferredCall;

afterEach(fn () => Carbon::setTestNow());

it('round-trips class, method and args through encode and decode', function () {
    // Given
    $original = new DeferredCall('App\\Foo', 'bar', [1, 'two', ['x' => 3]]);

    // When
    $decoded = DeferredCall::decode($original->encode());

    // Then
    expect($decoded->class)->toBe('App\\Foo');
    expect($decoded->method)->toBe('bar');
    expect($decoded->args)->toBe([1, 'two', ['x' => 3]]);
});

it('stamps publishedAt with the current time when none is given', function () {
    // Given
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

    // When
    $decoded = DeferredCall::decode((new DeferredCall('App\\Foo', 'bar'))->encode());

    // Then
    expect($decoded->publishedAt->toDateTimeString())->toBe('2026-06-01 09:00:00');
});

it('preserves an explicit publishedAt', function () {
    // Given
    $at = Carbon::parse('2026-01-02 03:04:05');

    // When
    $decoded = DeferredCall::decode((new DeferredCall('App\\Foo', 'bar', [], $at))->encode());

    // Then
    expect($decoded->publishedAt->toDateTimeString())->toBe('2026-01-02 03:04:05');
});
