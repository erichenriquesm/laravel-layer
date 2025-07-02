<?php

declare(strict_types=1);

namespace Domain\Shared\DomainTypes;

class Email extends BaseType
{
    public function __construct(
        string $email
    )
    {
        $this->validate($email);
        $this->value = $email;
    }

    private function validate(string $email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            throw new \DomainException('Invalid e-mail.');
        }
    }
}
