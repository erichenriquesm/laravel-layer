<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Auth\Contracts\GetAuthenticatedUserContract;
use Modules\Auth\Contracts\LoginContract;
use Modules\Auth\Contracts\LogoutContract;
use Modules\Auth\Contracts\RefreshTokenContract;
use Modules\Auth\Contracts\RegisterUserContract;
use Modules\Auth\DTOs\LoginDTO;
use Modules\Auth\DTOs\RefreshTokenDTO;
use Modules\Auth\DTOs\RegisterUserDTO;
use Modules\Auth\DTOs\TokenPairDTO;
use Modules\Auth\DTOs\UserDTO;

class AuthController extends Controller
{
    public function __construct(
        protected readonly GetAuthenticatedUserContract $getAuthenticatedUserAction,
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
        $this->logoutAction->handle();

        return response()->noContent();
    }

    public function me(): UserDTO
    {
        return $this->getAuthenticatedUserAction->handle();
    }
}
