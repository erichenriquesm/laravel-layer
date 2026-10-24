<?php

declare(strict_types=1);

namespace Modules\Auth\Contracts;

use Modules\Auth\DTOs\UserDTO;

interface GetAuthenticatedUserContract
{
    public function handle(): UserDTO;
}
