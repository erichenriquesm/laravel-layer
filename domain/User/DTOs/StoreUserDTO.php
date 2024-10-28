<?php

declare(strict_types=1);

namespace Domain\User\DTOs;

final class StoreUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ){}
}
