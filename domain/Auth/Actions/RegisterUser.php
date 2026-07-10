<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\UserDTO;
use Domain\Auth\Events\UserRegistered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Hash;

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
