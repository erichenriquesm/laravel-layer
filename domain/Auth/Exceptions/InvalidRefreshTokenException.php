<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use Domain\Shared\Contracts\HasErrorCode;
use RuntimeException;

final class InvalidRefreshTokenException extends RuntimeException implements HasErrorCode
{
    public function __construct(string $message = 'The refresh token is invalid, expired or already used')
    {
        parent::__construct($message);
    }

    public function errorCode(): int
    {
        return AuthErrorCode::InvalidRefreshToken->value;
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
