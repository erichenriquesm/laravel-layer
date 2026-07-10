<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\LogoutContract;
use Domain\Auth\Exceptions\UnauthenticatedException;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\RefreshTokenRepository;
use Laravel\Passport\Token;
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
    public function handle(): void
    {
        $accessTokenId = (string) $this->currentToken()->getKey();

        $this->tokens->revokeAccessToken($accessTokenId);
        $this->refreshTokens->revokeRefreshTokensByAccessTokenId($accessTokenId);
    }

    /**
     * token() also answers a TransientToken, which carries no id to revoke: the request was
     * authenticated by session rather than by a bearer token, so there is nothing to log out of.
     */
    private function currentToken(): Token
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new UnauthenticatedException();
        }

        $token = $user->token();

        if (! $token instanceof Token) {
            throw new UnauthenticatedException();
        }

        return $token;
    }
}
