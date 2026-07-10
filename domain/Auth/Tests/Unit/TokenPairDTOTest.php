<?php

declare(strict_types=1);

use Domain\Auth\DTOs\TokenPairDTO;
use Illuminate\Http\Request;

it('serializes to the snake_case payload an OAuth client expects', function () {
    // Given
    $dto = new TokenPairDTO(
        accessToken: 'access-abc',
        refreshToken: 'refresh-xyz',
        expiresIn: 900,
        tokenType: 'Bearer',
    );

    // When
    $payload = $dto->toArray();

    // Then
    expect($payload)->toBe([
        'access_token'  => 'access-abc',
        'refresh_token' => 'refresh-xyz',
        'expires_in'    => 900,
        'token_type'    => 'Bearer',
    ]);
});

it('responds with 200 on login instead of the 201 laravel-data defaults to on POST', function () {
    // Given
    $dto = new TokenPairDTO('access-abc', 'refresh-xyz', 900, 'Bearer');
    $request = Request::create('/login', 'POST');

    // When
    $response = $dto->toResponse($request);

    // Then
    expect($response->getStatusCode())->toBe(200);
});
