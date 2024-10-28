<?php

declare(strict_types=1);

namespace Domain\User\Contracts;

use Domain\User\DTOs\StoreUserDTO;

interface StoreUserContract
{
    public function exec(StoreUserDTO $input);
}
