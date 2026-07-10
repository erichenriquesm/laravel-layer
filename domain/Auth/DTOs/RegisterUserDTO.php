<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email as EmailRule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

final class RegisterUserDTO extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $name,

        #[Required, EmailRule, Unique('users', 'email')]
        public readonly string $email,

        #[Required, StringType, Confirmed]
        public readonly string $password,
    ) {}
}
