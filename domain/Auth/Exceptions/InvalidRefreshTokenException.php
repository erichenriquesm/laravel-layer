<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use RuntimeException;

final class InvalidRefreshTokenException extends RuntimeException
{
    public function __construct(string $message = 'The refresh token is invalid, expired or already used')
    {
        parent::__construct($message);
    }
}
