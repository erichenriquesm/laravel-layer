<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Contracts\RegisterUserContract;
use Modules\Auth\DTOs\RegisterUserDTO;
use Modules\Auth\DTOs\UserDTO;
use Modules\Auth\Events\UserRegistered;
use Modules\Auth\Models\User;

class RegisterUser implements RegisterUserContract
{
    public function __construct(private readonly Dispatcher $events) {}

    public function handle(RegisterUserDTO $input): UserDTO
    {
        $user = User::create([
            'name'     => $input->name,
            'email'    => $input->email,
            'password' => Hash::make($input->password),
        ]);

        // Announce the fact and move on. The rule does not know a listener or a queue exists;
        // Dispatcher is a framework contract resolved by DI, not a domain-owned driven port.
        $this->events->dispatch(new UserRegistered($user->id));

        return UserDTO::fromModel($user);
    }
}
