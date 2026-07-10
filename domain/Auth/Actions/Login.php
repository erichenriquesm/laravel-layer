<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\AccessTokenDTO;
use Domain\Auth\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Auth;

class Login implements LoginContract
{
    public function handle(LoginDTO $input): AccessTokenDTO
    {
        $credentials = [
            'email'    => $input->email,
            'password' => $input->password,
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw new InvalidCredentialsException();
        }

        $user = User::where('email', '=', $input->email)->first();

        return new AccessTokenDTO(
            token: $user->createToken('main')->accessToken,
        );
    }
}
