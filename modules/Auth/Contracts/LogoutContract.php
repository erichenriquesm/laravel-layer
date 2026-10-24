<?php

declare(strict_types=1);

namespace Modules\Auth\Contracts;

interface LogoutContract
{
    public function handle(): void;
}
