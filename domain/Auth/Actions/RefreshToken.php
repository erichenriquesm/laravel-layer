<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Contracts\RefreshTokenContract;
use Domain\Auth\DTOs\RefreshTokenDTO;
use Domain\Auth\DTOs\TokenPairDTO;
use Domain\Auth\Exceptions\InvalidRefreshTokenException;
use Domain\Auth\Support\PassportTokenIssuer;
use Laravel\Passport\Exceptions\OAuthServerException;

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
