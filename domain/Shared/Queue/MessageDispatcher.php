<?php

declare(strict_types=1);

namespace Domain\Shared\Queue;

use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use ReflectionException;
use ReflectionMethod;

/**
 * Resolves and runs the deferred call encoded in a message body. This is the only part of the
 * queue that holds real decision logic, and it never touches the broker — so it is unit tested.
 */
final class MessageDispatcher
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function dispatch(string $body, int $delay = 0): DispatchResult
    {
        $call = DeferredCall::decode($body);

        if ($delay > 0 && $call->publishedAt !== null) {
            $dueAt = $call->publishedAt->copy()->addSeconds($delay);

            if ($dueAt->isAfter(Carbon::now())) {
                return DispatchResult::notDue();
            }
        }

        try {
            $method = new ReflectionMethod($call->class, $call->method);
        } catch (ReflectionException $e) {
            Log::warning('MessageDispatcher: could not resolve the target', [
                'class'  => $call->class,
                'method' => $call->method,
                'error'  => $e->getMessage(),
            ]);

            return DispatchResult::handled(false);
        }

        # Resolve through the container, not `new`, so an instance target may declare
        # constructor dependencies. Static targets need no instance.
        $instance = $method->isStatic() ? null : $this->container->make($call->class);

        return DispatchResult::handled($method->invoke($instance, ...$call->args));
    }
}
