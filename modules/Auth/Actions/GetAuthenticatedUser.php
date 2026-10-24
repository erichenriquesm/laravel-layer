<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Auth;
use Modules\Auth\Contracts\GetAuthenticatedUserContract;
use Modules\Auth\DTOs\UserDTO;
use Modules\Auth\Exceptions\UnauthenticatedException;
use Modules\Auth\Models\User;

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
