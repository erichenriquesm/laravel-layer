<?php

namespace App\Providers;

use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\UserRepositoryContract;
use Domain\Auth\Repositories\UserRepository;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\Services\Login;
use Domain\Auth\Services\User;

use Domain\Product\Contracts\CreateProductServiceContract;
use Domain\Product\Services\CreateProductService;
use Domain\Auth\Services\RegisterUser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryContract::class, UserRepository::class);
        $this->app->bind(RegisterUserContract::class, RegisterUser::class);
        $this->app->bind(LoginContract::class, Login::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
