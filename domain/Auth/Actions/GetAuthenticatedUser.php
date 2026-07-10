<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use App\Models\User;
use Domain\Auth\Contracts\GetAuthenticatedUserContract;
use Domain\Auth\DTOs\UserDTO;
use Domain\Auth\Exceptions\UnauthenticatedException;
use Illuminate\Support\Facades\Auth;

class GetAuthenticatedUser implements GetAuthenticatedUserContract
{
    public function handle(): UserDTO
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new UnauthenticatedException();
        }

        return UserDTO::fromModel($user);
    }
}
