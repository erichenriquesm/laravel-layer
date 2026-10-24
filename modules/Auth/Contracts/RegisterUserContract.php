<?php

declare(strict_types=1);

namespace Modules\Auth\Contracts;

use Modules\Auth\DTOs\RegisterUserDTO;
use Modules\Auth\DTOs\UserDTO;

interface RegisterUserContract
{
    public function handle(RegisterUserDTO $input): UserDTO;
}
