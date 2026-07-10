<?php

declare(strict_types=1);

use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Shared\DomainTypes\Email;

it('exposes its fields when built with valid values', function () {
    // Given
    $name = 'Jane Doe';
    $email = new Email('jane@example.com');
    $password = 'secret123';

    // When
    $dto = new RegisterUserDTO(name: $name, email: $email, password: $password);

    // Then
    expect($dto->name)->toBe($name);
    expect($dto->email->getValue())->toBe('jane@example.com');
    expect($dto->password)->toBe($password);
});

it('rejects a name that is not a string', function () {
    // Given
    /** @var mixed $invalidName */
    $invalidName = null;

    // When
    $act = fn () => new RegisterUserDTO(
        name: $invalidName,
        email: new Email('jane@example.com'),
        password: 'secret123',
    );

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('rejects an email that is not the Email value object', function () {
    // Given
    /** @var mixed $invalidEmail */
    $invalidEmail = ['email' => 'jane@example.com'];

    // When
    $act = fn () => new RegisterUserDTO(name: 'Jane Doe', email: $invalidEmail, password: 'secret123');

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type Domain\Shared\DomainTypes\Email');
});

it('rejects a password that is not a string', function () {
    // Given
    /** @var mixed $invalidPassword */
    $invalidPassword = null;

    // When
    $act = fn () => new RegisterUserDTO(
        name: 'Jane Doe',
        email: new Email('jane@example.com'),
        password: $invalidPassword,
    );

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('casts the email string into a value object when hydrated from an array', function () {
    // Given
    $payload = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret123'];

    // When
    $dto = RegisterUserDTO::from($payload);

    // Then
    expect($dto->email)->toBeInstanceOf(Email::class);
    expect($dto->email->getValue())->toBe('jane@example.com');
});
