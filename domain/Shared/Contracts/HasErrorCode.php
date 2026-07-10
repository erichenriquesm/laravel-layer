<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

/**
 * An exception that the API can render as a stable, machine readable error code.
 *
 * The code is an opaque number: it identifies the failure to a client that holds the
 * catalogue, without spelling the failure out to anyone reading the raw response.
 */
interface HasErrorCode
{
    public function errorCode(): int;

    public function httpStatus(): int;
}
