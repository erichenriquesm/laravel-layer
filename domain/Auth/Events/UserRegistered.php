<?php

declare(strict_types=1);

namespace Domain\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain fact: a user finished registering. The business rule (RegisterUser) raises this and moves
 * on — it does not know that anything runs in the background, nor which listener or queue handles
 * it. Queued listeners react; the wiring lives in the Auth service provider.
 */
class UserRegistered
{
    use Dispatchable;

    public function __construct(public readonly int $userId) {}
}
