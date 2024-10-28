<?php

declare(strict_types=1);

namespace Domain\User\Services;

use Domain\User\Contracts\StoreUserContract;
use Domain\User\DTOs\StoreUserDTO;
use Domain\User\Contracts\UserRepositoryContract;

class StoreUser implements StoreUserContract
{
    public function __construct(
        private readonly UserRepositoryContract $repository
    ){}

    public function exec(StoreUserDTO $input) : array
    {
        $user = $this->repository->create([
            'name' => $input->name,
            'email' => $input->email,
            'password' => $input->password
        ]);

        return $user;
    }
}
