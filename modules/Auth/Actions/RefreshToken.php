<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Laravel\Passport\Exceptions\OAuthServerException;
use Modules\Auth\Contracts\RefreshTokenContract;
use Modules\Auth\DTOs\RefreshTokenDTO;
use Modules\Auth\DTOs\TokenPairDTO;
use Modules\Auth\Exceptions\InvalidRefreshTokenException;
use Modules\Auth\Support\PassportTokenIssuer;

class RefreshToken implements RefreshTokenContract
{
    public function __construct(
        private readonly PassportTokenIssuer $tokens,
    ) {}

    /**
     * The grant revokes the presented refresh token and issues a new pair, so a refresh token
     * is single use. Replaying one yields InvalidRefreshTokenException.
     */
    public function handle(RefreshTokenDTO $input): TokenPairDTO
    {
        try {
            return $this->tokens->issue([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $input->refreshToken,
            ]);
        } catch (OAuthServerException) {
            throw new InvalidRefreshTokenException();
        }
    }
}
