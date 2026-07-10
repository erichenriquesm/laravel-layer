<?php

declare(strict_types=1);

namespace Domain\Auth\Listeners;

use Domain\Auth\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Queued reaction to the UserRegistered fact. Implementing ShouldQueue pushes the work onto the
 * default queue connection (rabbitmq, see QUEUE_CONNECTION); `php artisan queue:work rabbitmq`
 * runs it and the framework serializes, delivers and retries. The producer never names this
 * listener or the queue — the only link is the event mapping in the Auth service provider.
 */
class SendWelcomeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /** Stop retrying after this many attempts; the final failure lands in failed_jobs. */
    public int $tries = 3;

    /** Seconds the broker waits before redelivering a released attempt. */
    public int $backoff = 5;

    public function handle(UserRegistered $event): void
    {
        // Demonstrates broker-level retry: on the first delivery a transient dependency (a mail
        // gateway, say) is treated as not ready, so the listener hands itself back with release()
        // and RabbitMQ redelivers it after $backoff seconds. attempts() counts deliveries, so
        // $tries bounds the retries.
        if ($this->attempts() < 2) {
            $this->release($this->backoff);

            return;
        }

        // Real work (send the welcome message) goes here; this stand-in records completion.
        Cache::put("welcome_sent:{$event->userId}", true);
    }
}
