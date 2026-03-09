<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use App\Models\User;
use Domain\Auth\DTOs\LoginDTO;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class Login
{
    public function exec(LoginDTO $input): array
    {
        $credentials = [
            'email'    => $input->email,
            'password' => $input->password,
        ];

        if (Auth::guard('web')->attempt($credentials)) {
            $user = User::where('email', '=', $input->email)->first();
            $token = $user->createToken('main')->accessToken;

            return [
                'token' => $token,
            ];
        }

        throw new InvalidArgumentException('invalid_credentials');
    }
}

