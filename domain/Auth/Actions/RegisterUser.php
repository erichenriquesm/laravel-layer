<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\UserDTO;
use Illuminate\Support\Facades\Hash;

class RegisterUser implements RegisterUserContract
{
    public function handle(RegisterUserDTO $input): UserDTO
    {
        $user = User::create([
            'name'     => $input->name,
            'email'    => $input->email->getValue(),
            'password' => Hash::make($input->password),
        ]);

        return UserDTO::fromModel($user);
    }
}
