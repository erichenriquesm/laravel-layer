<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use App\Models\User;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\Contracts\UserRepositoryContract;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class Login implements LoginContract
{
    public function __construct(
        private readonly UserRepositoryContract $repository
    ) {}

    public function exec(LoginDTO $input): array
    {
        $credentials = [
            'email'     => $input->email,
            'password'  => $input->password,
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
