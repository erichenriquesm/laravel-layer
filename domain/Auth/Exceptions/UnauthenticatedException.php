<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use Domain\Shared\Contracts\HasErrorCode;
use RuntimeException;

final class UnauthenticatedException extends RuntimeException implements HasErrorCode
{
    public function __construct(string $message = 'Unauthenticated')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return AuthErrorCode::Unauthenticated->value;
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
