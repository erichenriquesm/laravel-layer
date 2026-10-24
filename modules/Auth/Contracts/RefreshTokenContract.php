<?php

declare(strict_types=1);

namespace Modules\Auth\Contracts;

use Modules\Auth\DTOs\RefreshTokenDTO;
use Modules\Auth\DTOs\TokenPairDTO;

interface RefreshTokenContract
{
    public function handle(RefreshTokenDTO $input): TokenPairDTO;
}
