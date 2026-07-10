<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

/**
 * Codes owned by the Auth domain, all prefixed AUTH_.
 *
 * InvalidCredentials covers a missing user and a wrong password on purpose: telling them
 * apart would let an attacker enumerate which emails are registered.
 */
enum AuthErrorCode: string
{
    case Unauthenticated = 'AUTH_UNAUTHENTICATED';
    case InvalidCredentials = 'AUTH_INVALID_CREDENTIALS';
    case InvalidRefreshToken = 'AUTH_INVALID_REFRESH_TOKEN';

    public function description(): string
    {
        return match ($this) {
            self::Unauthenticated => 'The request carries no valid access token',
            self::InvalidCredentials => 'The email or the password is wrong',
            self::InvalidRefreshToken => 'The refresh token is invalid, expired or already used',
        };
    }
}
