<?php
declare(strict_types=1);

namespace App\Providers\Bindings;

class Repositories
{
    public static function get(): array
    {
        return [
            \Domain\Auth\Contracts\UserRepositoryContract::class => \Domain\Auth\Repositories\UserRepository::class
        ];
    }
}
