<?php

declare(strict_types=1);

namespace Domain\User\Contracts;
use Domain\Shared\Contracts\BaseRepositoryContract;
use Domain\User\DTOs\StoreUserDTO;

interface UserRepositoryContract extends BaseRepositoryContract
{
    function methodName(StoreUserDTO $input);
}
