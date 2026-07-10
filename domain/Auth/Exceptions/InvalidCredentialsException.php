<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use Domain\Shared\Contracts\HasErrorCode;
use RuntimeException;

final class InvalidCredentialsException extends RuntimeException implements HasErrorCode
{
    public function __construct(string $message = 'Verify your credentials')
    {
        parent::__construct($message);
    }

    public function errorCode(): int
    {
        return AuthErrorCode::InvalidCredentials->value;
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
