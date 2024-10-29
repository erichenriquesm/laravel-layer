<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

final class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ){}
}
