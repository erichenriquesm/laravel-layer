<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

/**
 * Codes not owned by any single domain. Range 1000-1099.
 *
 * description() is the canonical meaning, for logs and developers. publicMessage() is what
 * the client receives — kept vague so a raw response never spells the failure out.
 */
enum GeneralErrorCode: int
{
    case InternalError = 1000;
    case NotFound = 1001;
    case MethodNotAllowed = 1002;
    case ValidationFailed = 1003;
    case RateLimitExceeded = 1004;

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

    public function publicMessage(): string
    {
        return match ($this) {
            self::InternalError => 'The request could not be completed',
            self::NotFound => 'Not found',
            self::MethodNotAllowed => 'Method not allowed',
            self::ValidationFailed => 'The given data was invalid',
            self::RateLimitExceeded => 'Too many requests',
        };
    }
}
