<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\LoginDTO;

interface LoginContract
{
    public function exec(LoginDTO $input);
}