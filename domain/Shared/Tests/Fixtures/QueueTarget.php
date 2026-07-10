<?php

declare(strict_types=1);

namespace Domain\Shared\Tests\Fixtures;

/**
 * Target for Queue::processMessage tests: records how it was called so a test can tell a
 * static invocation from an instance one and inspect the args it received.
 */
class QueueTarget
{
    /** @var array<int, array{type: string, args: array<int, mixed>}> */
    public static array $calls = [];

    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$instances = 0;
    }

    public static function handledStatically(mixed ...$args): string
    {
        self::$calls[] = ['type' => 'static', 'args' => $args];

        return 'static-result';
    }

    public function handledOnInstance(mixed ...$args): string
    {
        self::$calls[] = ['type' => 'instance', 'args' => $args];

        return 'instance-result';
    }
}
