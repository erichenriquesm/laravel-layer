<?php

declare(strict_types=1);

namespace Modules\Auth\DTOs;

use Modules\Auth\Models\User;
use Spatie\LaravelData\Data;

final class UserDTO extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
