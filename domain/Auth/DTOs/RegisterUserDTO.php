<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Domain\Shared\Casts\EmailCast;
use Domain\Shared\DomainTypes\Email;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email as EmailRule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class RegisterUserDTO extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $name,

        #[Required, EmailRule, Unique('users', 'email')]
        #[WithCast(EmailCast::class)]
        public readonly Email $email,

        #[Required, StringType, Confirmed]
        public readonly string $password,
    ) {}
}
