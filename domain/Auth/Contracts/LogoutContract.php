<?php

declare(strict_types=1);

namespace Domain\Auth\Contracts;

interface LogoutContract
{
    public function handle(string $accessTokenId): void;
}
