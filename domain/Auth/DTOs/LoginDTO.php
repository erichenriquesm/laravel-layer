<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Spatie\LaravelData\Attributes\Validation\Email as EmailRule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class LoginDTO extends Data
{
    public function __construct(
        #[Required, EmailRule]
        public readonly string $email,

        #[Required, StringType]
        public readonly string $password,
    ) {}
}
