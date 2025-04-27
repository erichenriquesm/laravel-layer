<?php

declare(strict_types=1);

namespace Domain\Shared\DomainTypes;

class BaseType
{
    protected string $value;

    public function getValue()
    {
        return $this->value;
    }
}
