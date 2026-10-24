<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Laravel\Passport\Exceptions\OAuthServerException;
use Modules\Auth\Contracts\LoginContract;
use Modules\Auth\DTOs\LoginDTO;
use Modules\Auth\DTOs\TokenPairDTO;
use Modules\Auth\Exceptions\InvalidCredentialsException;
use Modules\Auth\Support\PassportTokenIssuer;

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
