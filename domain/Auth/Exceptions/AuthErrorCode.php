<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

/**
 * Codes owned by the Auth domain. Range 1100-1199.
 *
 * description() names the real failure, for logs and developers. publicMessage() is the
 * only text a client sees, and it is deliberately generic: the three cases share one
 * message so a raw response cannot tell an attacker which check failed, nor whether an
 * email exists. A legitimate front maps the numeric code to its own UX copy.
 */
enum AuthErrorCode: int
{
    case Unauthenticated = 1100;
    case InvalidCredentials = 1101;
    case InvalidRefreshToken = 1102;

    public function description(): string
    {
        return match ($this) {
            self::Unauthenticated => 'The request carries no valid access token',
            self::InvalidCredentials => 'The email or the password is wrong',
            self::InvalidRefreshToken => 'The refresh token is invalid, expired or already used',
        };
    }

    public function publicMessage(): string
    {
        return 'Authentication failed';
    }
}
