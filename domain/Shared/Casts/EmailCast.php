<?php

declare(strict_types=1);

namespace Domain\Shared\Casts;

use Domain\Shared\DomainTypes\Email;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class EmailCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Email
    {
        if ($value instanceof Email) {
            return $value;
        }

        return new Email((string) $value);
    }
}
