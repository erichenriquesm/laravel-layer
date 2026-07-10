<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterval;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\Contracts\RefreshTokenContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\RefreshTokenDTO;
use Domain\Auth\DTOs\TokenPairDTO;
use Domain\Auth\Exceptions\AuthErrorCode;
use Domain\Auth\Exceptions\InvalidRefreshTokenException;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

function signedInUser(string $email = 'refresh@example.com'): TokenPairDTO
{
    User::create([
        'name'     => 'Jane Doe',
        'email'    => $email,
        'password' => Hash::make('secret123'),
    ]);

    return port(LoginContract::class)->handle(new LoginDTO(email: $email, password: 'secret123'));
}

it('exchanges a refresh token for a brand new pair', function () {
    // Given
    $original = signedInUser();

    // When
    $rotated = port(RefreshTokenContract::class)->handle(new RefreshTokenDTO($original->refreshToken));

    // Then
    expect($rotated)->toBeInstanceOf(TokenPairDTO::class);
    expect($rotated->accessToken)->not->toBe($original->accessToken);
    expect($rotated->refreshToken)->not->toBe($original->refreshToken);
});

it('revokes the previous access token when rotating, so the old one dies immediately', function () {
    // Given
    $original = signedInUser('rotation-kills-old@example.com');
    $this->withHeader('Authorization', 'Bearer '.$original->accessToken)->getJson('/me')->assertStatus(200);

    // When
    port(RefreshTokenContract::class)->handle(new RefreshTokenDTO($original->refreshToken));

    // Then
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$original->accessToken)->getJson('/me')->assertStatus(401);
});

it('keeps already issued tokens valid when the configured lifetime changes', function () {
    // Given
    $issued = signedInUser('ttl-change@example.com');
    expect($issued->expiresIn)->toBe(config('tokens.access_token_minutes') * 60);

    // When
    Passport::tokensExpireIn(CarbonInterval::seconds(1));

    // Then
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)->getJson('/me')->assertStatus(200);
});

it('rejects a refresh token that was already used, since rotation revokes it', function () {
    // Given
    $original = signedInUser();
    port(RefreshTokenContract::class)->handle(new RefreshTokenDTO($original->refreshToken));

    // When
    $replay = fn () => port(RefreshTokenContract::class)->handle(new RefreshTokenDTO($original->refreshToken));

    // Then
    expect($replay)->toThrow(InvalidRefreshTokenException::class);
});

it('rejects a refresh token that was never issued', function () {
    // Given
    $garbage = new RefreshTokenDTO('not-a-real-refresh-token');

    // When
    $act = fn () => port(RefreshTokenContract::class)->handle($garbage);

    // Then
    expect($act)->toThrow(InvalidRefreshTokenException::class);
});

it('rotates a new access token even after the current one has expired', function () {
    // Given
    Passport::tokensExpireIn(CarbonInterval::seconds(0));
    $expired = signedInUser('expired@example.com');
    expect($expired->expiresIn)->toBe(0);

    // the expired access token no longer authenticates
    $this->withHeader('Authorization', 'Bearer '.$expired->accessToken)
        ->getJson('/me')
        ->assertStatus(401);

    // When
    $rotated = port(RefreshTokenContract::class)->handle(new RefreshTokenDTO($expired->refreshToken));

    // Then
    expect($rotated->accessToken)->not->toBe($expired->accessToken);
    expect($rotated->refreshToken)->not->toBe($expired->refreshToken);
});

it('refreshes through the route and responds 200 with the rotated pair', function () {
    // Given
    $original = signedInUser('route-refresh@example.com');

    // When
    $response = $this->postJson('/refresh', ['refresh_token' => $original->refreshToken]);

    // Then
    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'token_type']);
    expect($response->json('refresh_token'))->not->toBe($original->refreshToken);
});

it('responds 401 through the route for an invalid refresh token', function () {
    // Given
    $payload = ['refresh_token' => 'not-a-real-refresh-token'];

    // When
    $response = $this->postJson('/refresh', $payload);

    // Then
    $response->assertStatus(401)
        ->assertJson([
            'code'    => AuthErrorCode::InvalidRefreshToken->value,
            'message' => 'Authentication failed',
        ]);
});

it('responds 422 through the route when the refresh token is missing', function () {
    // Given
    $payload = [];

    // When
    $response = $this->postJson('/refresh', $payload);

    // Then
    $response->assertStatus(422)->assertJsonValidationErrors(['refresh_token']);
});
