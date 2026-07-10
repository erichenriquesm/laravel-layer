<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns the authenticated user as a UserDTO', function () {
    // Given
    $user = User::create([
        'name'     => 'Jane Doe',
        'email'    => 'me@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $this->actingAs($user);

    // When
    $response = $this->getJson('/me');

    // Then
    $response->assertStatus(200)
        ->assertExactJson([
            'id'    => $user->id,
            'name'  => 'Jane Doe',
            'email' => 'me@example.com',
        ]);
});

it('responds 401 when there is no authenticated user', function () {
    // Given
    // no authenticated user

    // When
    $response = $this->getJson('/me');

    // Then
    $response->assertStatus(401);
});
