<?php

declare(strict_types=1);

use Domain\Auth\DTOs\LoginDTO;

it('exposes email and password when built with valid values', function () {
    // Given
    $email = 'user@example.com';
    $password = 'secret123';

    // When
    $dto = new LoginDTO(email: $email, password: $password);

    // Then
    expect($dto->email)->toBe($email);
    expect($dto->password)->toBe($password);
});

it('rejects an email that is not a string', function () {
    // Given
    /** @var mixed $invalidEmail */
    $invalidEmail = ['invalid'];

    // When
    $act = fn () => new LoginDTO(email: $invalidEmail, password: 'secret123');

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('rejects a password that is not a string', function () {
    // Given
    /** @var mixed $invalidPassword */
    $invalidPassword = null;

    // When
    $act = fn () => new LoginDTO(email: 'user@example.com', password: $invalidPassword);

    // Then
    expect($act)->toThrow(TypeError::class, 'must be of type string');
});

it('declares the validation rules that replace the form request', function () {
    // Given
    // the rules come from the laravel-data attributes on the DTO itself

    // When
    $rules = LoginDTO::getValidationRules([]);

    // Then
    expect($rules)->toHaveKeys(['email', 'password']);
    expect($rules['email'])->toContain('required', 'string');
    expect(implode('|', $rules['email']))->toContain('email:rfc');
    expect($rules['password'])->toContain('required', 'string');
});
