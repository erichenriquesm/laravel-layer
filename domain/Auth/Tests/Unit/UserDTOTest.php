<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Auth\DTOs\UserDTO;

it('builds from the model without leaking the password', function () {
    // Given
    $user = new User(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $user->id = 7;
    $user->password = 'hashed-secret';

    // When
    $dto = UserDTO::fromModel($user);

    // Then
    expect($dto->id)->toBe(7);
    expect($dto->name)->toBe('Jane Doe');
    expect($dto->email)->toBe('jane@example.com');
    expect($dto->toArray())->toBe(['id' => 7, 'name' => 'Jane Doe', 'email' => 'jane@example.com']);
    expect($dto->toArray())->not->toHaveKey('password');
});
