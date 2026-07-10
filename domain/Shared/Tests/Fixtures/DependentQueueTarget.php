<?php

declare(strict_types=1);

namespace Domain\Shared\Tests\Fixtures;

/**
 * A queue target whose only constructor argument is another service. Under the old `new $class`
 * this would fatal (the argument is missing); under container resolution it is injected.
 */
class DependentQueueTarget
{
    public function __construct(private readonly TargetDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->label();
    }
}
