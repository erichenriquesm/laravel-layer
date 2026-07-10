<?php

declare(strict_types=1);

namespace Domain\Auth\Providers;

use Domain\Auth\Actions\GetAuthenticatedUser;
use Domain\Auth\Actions\Login;
use Domain\Auth\Actions\Logout;
use Domain\Auth\Actions\RefreshToken;
use Domain\Auth\Actions\RegisterUser;
use Domain\Auth\Contracts\GetAuthenticatedUserContract;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\LogoutContract;
use Domain\Auth\Contracts\RefreshTokenContract;
use Domain\Auth\Contracts\RegisterUserContract;
use Illuminate\Support\ServiceProvider;

class AuthDomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GetAuthenticatedUserContract::class => GetAuthenticatedUser::class,
        LoginContract::class               => Login::class,
        LogoutContract::class              => Logout::class,
        RefreshTokenContract::class        => RefreshToken::class,
        RegisterUserContract::class        => RegisterUser::class,
    ];
}
