<?php

declare(strict_types=1);

use Domain\Auth\DTOs\RefreshTokenDTO;

it('hydrates from the snake_case field an OAuth client sends', function () {
    // Given
    $payload = ['refresh_token' => 'refresh-xyz'];

    // When
    $dto = RefreshTokenDTO::from($payload);

    // Then
    expect($dto->refreshToken)->toBe('refresh-xyz');
});

it('requires the refresh token', function () {
    // Given
    // the rules come from the laravel-data attributes on the DTO itself

    // When
    $rules = RefreshTokenDTO::getValidationRules([]);

    // Then
    expect($rules)->toHaveKey('refresh_token');
    expect($rules['refresh_token'])->toContain('required', 'string');
});
