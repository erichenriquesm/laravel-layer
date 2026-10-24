<?php

declare(strict_types=1);

namespace Modules\Auth\Exceptions;

use Modules\Shared\Contracts\HasErrorCode;
use RuntimeException;

final class UnauthenticatedException extends RuntimeException implements HasErrorCode
{
    public function __construct(string $message = 'Unauthenticated')
    {
        parent::__construct($message);
    }

    public function errorCode(): int
    {
        return AuthErrorCode::Unauthenticated->value;
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
