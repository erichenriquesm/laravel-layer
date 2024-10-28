<?php

declare(strict_types=1);

namespace Domain\User\Contracts;

use Domain\User\DTOs\RegisterUserDTO;

interface RegisterUserContract
{
    public function exec(RegisterUserDTO $input);
}
