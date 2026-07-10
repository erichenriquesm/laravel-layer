<?php

declare(strict_types=1);

use Domain\Auth\DTOs\AccessTokenDTO;
use Illuminate\Http\Request;

it('serializes to a payload holding only the token', function () {
    // Given
    $dto = new AccessTokenDTO(token: 'abc123');

    // When
    $payload = $dto->toArray();

    // Then
    expect($payload)->toBe(['token' => 'abc123']);
});

it('responds with 200 on login instead of the 201 laravel-data defaults to on POST', function () {
    // Given
    $dto = new AccessTokenDTO(token: 'abc123');
    $request = Request::create('/login', 'POST');

    // When
    $response = $dto->toResponse($request);

    // Then
    expect($response->getStatusCode())->toBe(200);
});
