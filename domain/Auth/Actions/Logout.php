<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Contracts\LogoutContract;
use Laravel\Passport\RefreshTokenRepository;
use Laravel\Passport\TokenRepository;

class Logout implements LogoutContract
{
    public function __construct(
        private readonly TokenRepository $tokens,
        private readonly RefreshTokenRepository $refreshTokens,
    ) {}

    /**
     * Revoking the access token alone would leave its refresh token usable, which would hand a
     * brand new access token to whoever holds it.
     */
    public function handle(string $accessTokenId): void
    {
        $this->tokens->revokeAccessToken($accessTokenId);
        $this->refreshTokens->revokeRefreshTokensByAccessTokenId($accessTokenId);
    }
}
