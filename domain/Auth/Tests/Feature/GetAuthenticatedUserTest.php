<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Auth\Contracts\GetAuthenticatedUserContract;
use Domain\Auth\DTOs\UserDTO;
use Domain\Auth\Exceptions\UnauthenticatedException;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;

it('maps the authenticated user to a UserDTO', function () {
    // Given
    $user = User::create([
        'name'     => 'Jane Doe',
        'email'    => 'authenticated@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $this->actingAs($user);

    // When
    $result = port(GetAuthenticatedUserContract::class)->handle();

    // Then
    expect($result)->toBeInstanceOf(UserDTO::class);
    expect($result->id)->toBe($user->id);
    expect($result->email)->toBe('authenticated@example.com');
});

it('throws UnauthenticatedException when nobody is authenticated', function () {
    // Given
    // no authenticated user

    // When
    $act = fn () => port(GetAuthenticatedUserContract::class)->handle();

    // Then
    expect($act)->toThrow(UnauthenticatedException::class);
});

it('lets the controller answer /me from a swapped implementation', function () {
    // Given
    // the controller depends on the contract, so /me never touches Auth or the model
    $this->mock(GetAuthenticatedUserContract::class, function (MockInterface $mock) {
        $mock->shouldReceive('handle')->once()->andReturn(
            new UserDTO(id: 99, name: 'Swapped', email: 'swapped@example.com')
        );
    });

    $user = User::create([
        'name'     => 'Real User',
        'email'    => 'real@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $this->actingAs($user);

    // When
    $response = $this->getJson('/me');

    // Then
    $response->assertStatus(200)->assertExactJson([
        'id'    => 99,
        'name'  => 'Swapped',
        'email' => 'swapped@example.com',
    ]);
});
