<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class RefreshTokenDTO extends Data
{
    public function __construct(
        #[MapInputName('refresh_token')]
        #[Required, StringType]
        public readonly string $refreshToken,
    ) {}
}
