<?php
declare(strict_types=1);

namespace App\Providers\Bindings;

class Services
{
    public static function get(): array
    {
        return [
            \Domain\Auth\Contracts\RegisterUserContract::class => \Domain\Auth\Services\RegisterUser::class,
            \Domain\Auth\Contracts\LoginContract::class => \Domain\Auth\Services\Login::class
        ];
    }
}
