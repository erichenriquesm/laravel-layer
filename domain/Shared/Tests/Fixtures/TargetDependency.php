<?php

declare(strict_types=1);

namespace Domain\Shared\Tests\Fixtures;

/**
 * A collaborator the container injects into DependentQueueTarget — proof that a queue target
 * may declare constructor dependencies now that the dispatcher resolves through the container.
 */
class TargetDependency
{
    public function label(): string
    {
        return 'resolved-via-container';
    }
}
