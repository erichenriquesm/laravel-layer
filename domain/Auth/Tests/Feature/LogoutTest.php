<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Illuminate\Support\Facades\Hash;

function tokenPairFor(string $email): Domain\Auth\DTOs\TokenPairDTO
{
    User::create([
        'name'     => 'Jane Doe',
        'email'    => $email,
        'password' => Hash::make('secret123'),
    ]);

    return port(LoginContract::class)->handle(new LoginDTO(email: $email, password: 'secret123'));
}

/**
 * The guard caches the resolved user, and a test reuses one application across requests.
 * A real client gets a fresh process per request, so drop the cache to match production.
 */
function forgetResolvedUser(): void
{
    app('auth')->forgetGuards();
}

it('revokes the current access token, so it stops authenticating', function () {
    // Given
    $pair = tokenPairFor('logout@example.com');
    $this->withHeader('Authorization', 'Bearer '.$pair->accessToken)->getJson('/me')->assertStatus(200);

    // When
    forgetResolvedUser();
    $response = $this->withHeader('Authorization', 'Bearer '.$pair->accessToken)->postJson('/logout');

    // Then
    $response->assertStatus(204);
    forgetResolvedUser();
    $this->withHeader('Authorization', 'Bearer '.$pair->accessToken)->getJson('/me')->assertStatus(401);
});

it('revokes the refresh token too, so the session cannot be resurrected', function () {
    // Given
    $pair = tokenPairFor('logout-refresh@example.com');

    // When
    $this->withHeader('Authorization', 'Bearer '.$pair->accessToken)->postJson('/logout')->assertStatus(204);

    // Then
    $this->postJson('/refresh', ['refresh_token' => $pair->refreshToken])->assertStatus(401);
});

it('leaves other sessions of the same user signed in', function () {
    // Given
    $first = tokenPairFor('two-devices@example.com');
    $second = port(LoginContract::class)->handle(
        new LoginDTO(email: 'two-devices@example.com', password: 'secret123')
    );

    // When
    $this->withHeader('Authorization', 'Bearer '.$first->accessToken)->postJson('/logout')->assertStatus(204);

    // Then
    forgetResolvedUser();
    $this->withHeader('Authorization', 'Bearer '.$first->accessToken)->getJson('/me')->assertStatus(401);
    forgetResolvedUser();
    $this->withHeader('Authorization', 'Bearer '.$second->accessToken)->getJson('/me')->assertStatus(200);
});

it('requires authentication', function () {
    // Given
    // no Authorization header

    // When
    $response = $this->postJson('/logout');

    // Then
    $response->assertStatus(401);
});
