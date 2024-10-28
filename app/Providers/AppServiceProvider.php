<?php

namespace App\Providers;
use Domain\User\Contracts\UserRepositoryContract;
use Domain\User\Repositories\UserRepository;
use Domain\User\Contracts\RegisterUserContract;
use Domain\User\Services\User;

use Domain\Product\Contracts\CreateProductServiceContract;
use Domain\Product\Services\CreateProductService;
use Domain\User\Services\RegisterUser;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
