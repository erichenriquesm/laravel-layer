<?php

declare(strict_types=1);

namespace Domain\Shared\Queue;

/**
 * The outcome of dispatching a message, replacing the old '__delay__' magic string.
 *
 * A message is either not due yet (the worker should requeue it) or it was handled, in
 * which case $value holds whatever the target returned — the worker uses it in assure mode.
 */
final class DispatchResult
{
    private function __construct(
        public readonly bool $notDue,
        public readonly mixed $value,
    ) {}

    public static function notDue(): self
    {
        return new self(true, null);
    }

    public static function handled(mixed $value): self
    {
        return new self(false, $value);
    }
}
