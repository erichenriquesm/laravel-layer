<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\UserDTO;

interface RegisterUserContract
{
    public function handle(RegisterUserDTO $input): UserDTO;
}
