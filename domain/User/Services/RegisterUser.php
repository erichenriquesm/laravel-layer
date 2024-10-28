<?php

declare(strict_types=1);

namespace Domain\User\Services;

use Domain\User\Contracts\RegisterUserContract;
use Domain\User\DTOs\RegisterUserDTO;
use Domain\User\Contracts\UserRepositoryContract;
use Illuminate\Support\Facades\Hash;

class RegisterUser implements RegisterUserContract
{
    public function __construct(
        private readonly UserRepositoryContract $repository
    ){}

    public function exec(RegisterUserDTO $input) : array
    {
        return $this->repository->create([
            'name'      => $input->name,
            'email'     => $input->email,
            'password'  => Hash::make($input->password)
        ]);
    }
}
