<?php

declare(strict_types=1);

namespace Domain\Auth\Providers;

use Domain\Auth\Actions\Login;
use Domain\Auth\Actions\RegisterUser;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\RegisterUserContract;
use Illuminate\Support\ServiceProvider;

class AuthDomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        LoginContract::class        => Login::class,
        RegisterUserContract::class => RegisterUser::class,
    ];
}
