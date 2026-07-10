<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\TokenPairDTO;

interface LoginContract
{
    public function handle(LoginDTO $input): TokenPairDTO;
}
