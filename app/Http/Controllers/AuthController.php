<?php

namespace App\Http\Controllers;

use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\AccessTokenDTO;
use Domain\Auth\DTOs\UserDTO;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected readonly LoginContract $loginAction,
        protected readonly RegisterUserContract $registerUserAction,
    ) {
    }

    public function register(RegisterUserDTO $input): UserDTO
    {
        return $this->registerUserAction->handle($input);
    }

    public function login(LoginDTO $input): AccessTokenDTO
    {
        return $this->loginAction->handle($input);
    }

    public function me(): UserDTO
    {
        return UserDTO::fromModel(Auth::user());
    }
}
