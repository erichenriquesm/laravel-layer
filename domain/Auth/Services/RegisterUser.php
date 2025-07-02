<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\Contracts\UserRepositoryContract;
use Illuminate\Support\Facades\Hash;

class RegisterUser implements RegisterUserContract
{
    public function __construct(
        private readonly UserRepositoryContract $repository
    ){}

    public function exec(RegisterUserDTO $input) : array
    {
        $this->repository->create([
            'name'      => $input->name,
            'email'     => $input->email->getValue(),
            'password'  => Hash::make($input->password)
        ]);

        return [
            'message' => 'User registered'
        ];
    }
}
