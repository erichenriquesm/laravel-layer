<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\LogoutContract;
use Domain\Auth\Exceptions\UnauthenticatedException;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;

class Logout implements LogoutContract
{
    /**
     * Revoking the access token alone would leave its refresh token usable, which would hand a
     * brand new access token to whoever holds it.
     *
     * Passport 13 resolves a bearer request to an AccessToken value object (not the Token model)
     * and revokes through it.
     */
    public function handle(): void
    {
        $token = $this->currentAccessToken();

        RefreshToken::where('access_token_id', $token->oauth_access_token_id)
            ->where('revoked', false)
            ->get()
            ->each
            ->revoke();

        $token->revoke();
    }

    /**
     * token() answers a TransientToken for a session-authenticated request, which carries no id to
     * revoke: there is nothing to log out of, so the request is treated as unauthenticated.
     */
    private function currentAccessToken(): AccessToken
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new UnauthenticatedException();
        }

        $token = $user->token();

        if (! $token instanceof AccessToken) {
            throw new UnauthenticatedException();
        }

        return $token;
    }
}
