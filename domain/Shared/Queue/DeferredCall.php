<?php

declare(strict_types=1);

namespace Domain\Shared\Queue;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A call to run later on a worker: $class::$method(...$args).
 *
 * It owns the wire envelope, so the encode and decode formats live in one place instead of
 * being split between a producer and a consumer that must silently agree.
 */
final class DeferredCall
{
    /**
     * @param array<int, mixed> $args
     */
    public function __construct(
        public readonly string $class,
        public readonly string $method,
        public readonly array $args = [],
        public readonly ?CarbonInterface $publishedAt = null,
    ) {}

    public function encode(): string
    {
        return serialize([
            '__RID'         => Hash::make(Str::random(10)),
            '__class'       => $this->class,
            '__method'      => $this->method,
            '__args'        => $this->args,
            '__publishedAt' => $this->publishedAt ?? Carbon::now(),
            '__messageID'   => Str::uuid(),
        ]);
    }

    public static function decode(string $body): self
    {
        $data = unserialize($body);

        return new self(
            class: (string) Arr::get($data, '__class'),
            method: (string) Arr::get($data, '__method'),
            args: Arr::get($data, '__args') ?? [],
            publishedAt: Arr::get($data, '__publishedAt'),
        );
    }
}
