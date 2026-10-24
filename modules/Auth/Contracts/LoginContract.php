<?php

declare(strict_types=1);

namespace Modules\Auth\Contracts;

use Modules\Auth\DTOs\LoginDTO;
use Modules\Auth\DTOs\TokenPairDTO;

interface LoginContract
{
    public function handle(LoginDTO $input): TokenPairDTO;
}
