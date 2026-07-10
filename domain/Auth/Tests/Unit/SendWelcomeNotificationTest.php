<?php

declare(strict_types=1);

use Domain\Auth\Events\UserRegistered;
use Domain\Auth\Listeners\SendWelcomeNotification;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

/**
 * The queued listener that reacts to UserRegistered. Its logic is exercised without a broker (a
 * real queue:work run is the integration check). Each branch of handle() is driven through the
 * underlying job's attempts().
 */
it('releases itself back to the broker on the first delivery', function () {
    // Given
    $listener = new SendWelcomeNotification();
    $listener->setJob(Mockery::mock(QueueJob::class, function (MockInterface $mock): void {
        $mock->shouldReceive('attempts')->andReturn(1);
        $mock->shouldReceive('release')->once()->with(5);
    }));

    // When
    $listener->handle(new UserRegistered(1));

    // Then
    expect(Cache::get('welcome_sent:1'))->toBeNull();
});

it('completes on a later delivery instead of releasing', function () {
    // Given
    $listener = new SendWelcomeNotification();
    $listener->setJob(Mockery::mock(QueueJob::class, function (MockInterface $mock): void {
        $mock->shouldReceive('attempts')->andReturn(2);
        $mock->shouldReceive('release')->never();
    }));

    // When
    $listener->handle(new UserRegistered(2));

    // Then
    expect(Cache::get('welcome_sent:2'))->toBeTrue();
});
