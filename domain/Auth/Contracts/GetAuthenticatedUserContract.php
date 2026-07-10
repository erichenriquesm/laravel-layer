<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\UserDTO;

interface GetAuthenticatedUserContract
{
    public function handle(): UserDTO;
}
