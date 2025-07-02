<?php

namespace App\Providers;

use App\Providers\Bindings\Repositories;
use App\Providers\Bindings\Services;
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
        foreach (Services::get() as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        foreach (Repositories::get() as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
