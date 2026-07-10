<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Reference native queued job. Producers dispatch it with ExampleJob::dispatch($payloadId); it
 * lands on the default queue connection (rabbitmq, see QUEUE_CONNECTION) and is processed by
 * `php artisan queue:work rabbitmq`. The framework serializes, delivers and retries the job —
 * there is no custom queue layer to go through. Copy this shape for real jobs.
 */
class ExampleJob implements ShouldQueue
{
    use Queueable;

    /** Stop retrying after this many attempts; the final failure is recorded in failed_jobs. */
    public int $tries = 3;

    /** Seconds the broker waits before redelivering a released attempt. */
    public int $backoff = 5;

    public function __construct(public readonly int $payloadId) {}

    public function handle(): void
    {
        // Demonstrates broker-level retry: on the first delivery the work is treated as not yet
        // runnable, so the job hands itself back with release() and RabbitMQ redelivers it after
        // $backoff seconds. attempts() counts deliveries, so $tries bounds the retries.
        if ($this->attempts() < 2) {
            $this->release($this->backoff);

            return;
        }

        // Real work goes here; this stand-in records that the job ran to completion.
        Cache::put("example_job_done:{$this->payloadId}", true);
    }
}
