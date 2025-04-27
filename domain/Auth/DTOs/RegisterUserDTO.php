<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Domain\Shared\DomainTypes\Email;

final class RegisterUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly Email $email,
        public readonly string $password
    ){}
}
