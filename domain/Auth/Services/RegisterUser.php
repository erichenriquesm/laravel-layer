<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use App\Models\User;
use Domain\Auth\DTOs\RegisterUserDTO;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    public function exec(RegisterUserDTO $input): array
    {
        User::create([
            'name'     => $input->name,
            'email'    => $input->email->getValue(),
            'password' => Hash::make($input->password),
        ]);

        return [
            'message' => 'User registered',
        ];
    }
}
