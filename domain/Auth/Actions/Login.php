<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\TokenPairDTO;
use Domain\Auth\Exceptions\InvalidCredentialsException;
use Domain\Auth\Support\PassportTokenIssuer;
use Laravel\Passport\Exceptions\OAuthServerException;

class Login implements LoginContract
{
    public function __construct(
        private readonly PassportTokenIssuer $tokens,
    ) {}

    public function handle(LoginDTO $input): TokenPairDTO
    {
        try {
            return $this->tokens->issue([
                'grant_type' => 'password',
                'username'   => $input->email,
                'password'   => $input->password,
            ]);
        } catch (OAuthServerException) {
            throw new InvalidCredentialsException();
        }
    }
}
