<?php

declare(strict_types=1);

use App\Jobs\ExampleJob;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

/**
 * Covers the native queued job that replaced the custom queue subsystem. The logic lives in a
 * plain Laravel job; these tests exercise it without a broker (a real queue:work run is the
 * integration check). Each branch of handle() is driven through the underlying job's attempts().
 */
it('dispatches onto the queue carrying its payload', function () {
    // Given
    Queue::fake();

    // When
    ExampleJob::dispatch(42);

    // Then
    Queue::assertPushed(ExampleJob::class, fn (ExampleJob $job): bool => $job->payloadId === 42);
});

it('releases itself back to the broker on the first delivery', function () {
    // Given
    $job = new ExampleJob(1);
    $job->setJob(Mockery::mock(QueueJob::class, function (MockInterface $mock): void {
        $mock->shouldReceive('attempts')->andReturn(1);
        $mock->shouldReceive('release')->once()->with(5);
    }));

    // When
    $job->handle();

    // Then
    expect(Cache::get('example_job_done:1'))->toBeNull();
});

it('completes on a later delivery instead of releasing', function () {
    // Given
    $job = new ExampleJob(2);
    $job->setJob(Mockery::mock(QueueJob::class, function (MockInterface $mock): void {
        $mock->shouldReceive('attempts')->andReturn(2);
        $mock->shouldReceive('release')->never();
    }));

    // When
    $job->handle();

    // Then
    expect(Cache::get('example_job_done:2'))->toBeTrue();
});
