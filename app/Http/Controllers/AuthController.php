<?php

namespace App\Http\Controllers;

use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\LogoutContract;
use Domain\Auth\Contracts\RefreshTokenContract;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\RefreshTokenDTO;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\TokenPairDTO;
use Domain\Auth\DTOs\UserDTO;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected readonly LoginContract $loginAction,
        protected readonly LogoutContract $logoutAction,
        protected readonly RefreshTokenContract $refreshTokenAction,
        protected readonly RegisterUserContract $registerUserAction,
    ) {
    }

    public function register(RegisterUserDTO $input): UserDTO
    {
        return $this->registerUserAction->handle($input);
    }

    public function login(LoginDTO $input): TokenPairDTO
    {
        return $this->loginAction->handle($input);
    }

    public function refresh(RefreshTokenDTO $input): TokenPairDTO
    {
        return $this->refreshTokenAction->handle($input);
    }

    public function logout(): Response
    {
        $this->logoutAction->handle(Auth::user()->token()->id);

        return response()->noContent();
    }

    public function me(): UserDTO
    {
        return UserDTO::fromModel(Auth::user());
    }
}
