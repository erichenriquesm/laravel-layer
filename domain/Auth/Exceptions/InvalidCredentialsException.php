<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Verify your credentials')
    {
        parent::__construct($message);
    }
}
