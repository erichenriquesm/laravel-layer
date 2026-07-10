<?php

declare(strict_types=1);

use Domain\Auth\DTOs\RegisterUserDTO;

it('exposes its fields when built with valid values', function () {
    // Given
    $name = 'Jane Doe';
    $email = 'jane@example.com';
    $password = 'secret123';

    // When
    $dto = new RegisterUserDTO(name: $name, email: $email, password: $password);

    // Then
    expect($dto->name)->toBe($name);
    expect($dto->email)->toBe($email);
    expect($dto->password)->toBe($password);
});

it('rejects a name that is not a string', function () {
    // Given
    /** @var mixed $invalidName */
    $invalidName = null;

    // When
    $act = fn () => new RegisterUserDTO(
        name: $invalidName,
        email: 'jane@example.com',
        password: 'secret123',
    );

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('rejects an email that is not a string', function () {
    // Given
    /** @var mixed $invalidEmail */
    $invalidEmail = ['email' => 'jane@example.com'];

    // When
    $act = fn () => new RegisterUserDTO(name: 'Jane Doe', email: $invalidEmail, password: 'secret123');

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('rejects a password that is not a string', function () {
    // Given
    /** @var mixed $invalidPassword */
    $invalidPassword = null;

    // When
    $act = fn () => new RegisterUserDTO(
        name: 'Jane Doe',
        email: 'jane@example.com',
        password: $invalidPassword,
    );

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('rejects an invalid email format through the validation rules', function () {
    // Given
    // the #[Email] rule replaces the value object: format is enforced at the boundary
    $rules = RegisterUserDTO::getValidationRules([]);

    // When / Then
    expect($rules)->toHaveKey('email');
    expect(implode('|', $rules['email']))->toContain('email');
});

it('hydrates the email as a plain string from an array', function () {
    // Given
    $payload = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret123'];

    // When
    $dto = RegisterUserDTO::from($payload);

    // Then
    expect($dto->email)->toBe('jane@example.com');
});
