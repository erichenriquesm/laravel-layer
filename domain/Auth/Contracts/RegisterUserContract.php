<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\RegisterUserDTO;

interface RegisterUserContract
{
    public function exec(RegisterUserDTO $input);
}
