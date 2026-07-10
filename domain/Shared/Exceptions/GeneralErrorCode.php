<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

/**
 * Codes not owned by any single domain, so they carry no domain prefix.
 *
 * A code is part of the public contract: rename it and every client breaks. The human
 * message beside it is free to change.
 */
enum GeneralErrorCode: string
{
    case InternalError = 'INTERNAL_ERROR';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';

    public function description(): string
    {
        return match ($this) {
            self::InternalError => 'The server failed to handle the request',
            self::NotFound => 'The requested resource does not exist',
            self::MethodNotAllowed => 'The HTTP method is not allowed for this route',
            self::ValidationFailed => 'The request payload is invalid',
            self::RateLimitExceeded => 'Too many requests, retry later',
        };
    }
}
