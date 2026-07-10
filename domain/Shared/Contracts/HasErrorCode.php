<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

/**
 * An exception that the API can render as a stable, machine readable error code.
 */
interface HasErrorCode
{
    public function errorCode(): string;

    public function httpStatus(): int;
}
