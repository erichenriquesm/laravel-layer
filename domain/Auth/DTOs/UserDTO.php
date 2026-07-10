<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use App\Models\User;
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
