<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

use Domain\Auth\DTOs\RefreshTokenDTO;
use Domain\Auth\DTOs\TokenPairDTO;

interface RefreshTokenContract
{
    public function handle(RefreshTokenDTO $input): TokenPairDTO;
}
