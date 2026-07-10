<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\TokenPairDTO;
use Domain\Auth\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

it('returns an access token and a refresh token when the credentials match', function () {
    // Given
    $user = User::create([
        'name'     => 'Jane Doe',
        'email'    => 'login-ok@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $dto = new LoginDTO(email: $user->email, password: 'secret123');

    // When
    $result = port(LoginContract::class)->handle($dto);

    // Then
    expect($result)->toBeInstanceOf(TokenPairDTO::class);
    expect($result->accessToken)->toBeString()->not->toBeEmpty();
    expect($result->refreshToken)->toBeString()->not->toBeEmpty();
    expect($result->tokenType)->toBe('Bearer');
});

it('sets expires_in from the configured access token lifetime', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'login-ttl@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $dto = new LoginDTO(email: 'login-ttl@example.com', password: 'secret123');

    // When
    $result = port(LoginContract::class)->handle($dto);

    // Then
    expect($result->expiresIn)->toBe(config('tokens.access_token_minutes') * 60);
});

it('throws InvalidCredentialsException when the password is wrong', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'login-wrong-password@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $dto = new LoginDTO(email: 'login-wrong-password@example.com', password: 'wrong-password');

    // When
    $act = fn () => port(LoginContract::class)->handle($dto);

    // Then
    expect($act)->toThrow(InvalidCredentialsException::class, 'Verify your credentials');
});

it('throws InvalidCredentialsException when the user does not exist', function () {
    // Given
    $dto = new LoginDTO(email: 'does-not-exist@example.com', password: 'secret123');

    // When
    $act = fn () => port(LoginContract::class)->handle($dto);

    // Then
    expect($act)->toThrow(InvalidCredentialsException::class);
});

it('logs in through the route and responds 200 with the token pair', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'route-login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // When
    $response = $this->postJson('/login', [
        'email'    => 'route-login@example.com',
        'password' => 'secret123',
    ]);

    // Then
    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'token_type']);
    expect($response->json('token_type'))->toBe('Bearer');
});

it('responds 401 through the route when the credentials are invalid', function () {
    // Given
    $payload = ['email' => 'anyone@example.com', 'password' => 'wrong-password'];

    // When
    $response = $this->postJson('/login', $payload);

    // Then
    $response->assertStatus(401)->assertJson(['message' => 'Verify your credentials']);
});

it('responds 422 through the route when the credentials are missing', function () {
    // Given
    $payload = [];

    // When
    $response = $this->postJson('/login', $payload);

    // Then
    $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
});

it('responds 422 as JSON even without the Accept header, since the app only exposes an API', function () {
    // Given
    $payload = [];

    // When
    $response = $this->post('/login', $payload);

    // Then
    $response->assertStatus(422);
    expect($response->headers->get('content-type'))->toContain('application/json');
});
