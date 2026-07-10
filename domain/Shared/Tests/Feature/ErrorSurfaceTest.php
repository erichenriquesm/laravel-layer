<?php

declare(strict_types=1);

use App\Exceptions\Handler;
use App\Models\User;
use Domain\Auth\Exceptions\AuthErrorCode;
use Domain\Shared\Exceptions\GeneralErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

it('lets Passport render its own OAuth error, not our envelope', function () {
    // Given
    // an invalid grant on Passport's own endpoint throws OAuthServerException
    $payload = ['grant_type' => 'authorization_code'];

    // When
    $response = $this->postJson('/oauth/token', $payload);
    $body = $response->json();

    // Then
    // the RFC-shaped OAuth error, not our {code, message} — reshaping it would break OAuth clients
    $response->assertStatus(400);
    expect($body)->toHaveKey('error');
    expect($body)->not->toHaveKey('code');
});

it('answers invalid credentials with the auth code', function () {
    // Given
    $payload = ['email' => 'nobody@example.com', 'password' => 'wrong-password'];

    // When
    $response = $this->postJson('/login', $payload);

    // Then
    $response->assertStatus(401)->assertJson(['code' => AuthErrorCode::InvalidCredentials->value]);
});

it('answers an invalid refresh token with the auth code', function () {
    // Given
    $payload = ['refresh_token' => 'not-a-real-refresh-token'];

    // When
    $response = $this->postJson('/refresh', $payload);

    // Then
    $response->assertStatus(401)->assertJson(['code' => AuthErrorCode::InvalidRefreshToken->value]);
});

it('answers a missing access token with the unauthenticated code and a generic message', function () {
    // Given
    // no Authorization header

    // When
    $response = $this->getJson('/me');

    // Then
    $response->assertStatus(401)->assertJson([
        'code'    => AuthErrorCode::Unauthenticated->value,
        'message' => 'Authentication failed',
    ]);
});

it('never spells the failure out on the wire, so an attacker learns nothing from the message', function () {
    // Given
    // an invalid refresh token: its description names the exact state (expired / reused / invalid)
    $payload = ['refresh_token' => 'not-a-real-refresh-token'];

    // When
    $response = $this->postJson('/refresh', $payload);
    $body = $response->getContent();

    // Then
    $response->assertStatus(401)->assertJson([
        'code'    => AuthErrorCode::InvalidRefreshToken->value,
        'message' => 'Authentication failed',
    ]);
    expect($body)->not->toContain('refresh token');
    expect($body)->not->toContain('expired');
});

it('serves the same generic message for every distinct auth failure', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'same-message@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // When
    $wrongPassword = $this->postJson('/login', ['email' => 'same-message@example.com', 'password' => 'nope']);
    $unknownEmail = $this->postJson('/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

    // Then
    // identical message and code for a wrong password and an unknown email: no enumeration
    expect($wrongPassword->json('message'))->toBe($unknownEmail->json('message'));
    expect($wrongPassword->json('code'))->toBe($unknownEmail->json('code'));
});

it('answers a validation failure with the general code and keeps the errors bag', function () {
    // Given
    $payload = [];

    // When
    $response = $this->postJson('/login', $payload);

    // Then
    $response->assertStatus(422)
        ->assertJson(['code' => GeneralErrorCode::ValidationFailed->value])
        ->assertJsonValidationErrors(['email', 'password']);
});

it('answers an unknown route with the not found code', function () {
    // Given
    $route = '/this-route-does-not-exist';

    // When
    $response = $this->getJson($route);

    // Then
    $response->assertStatus(404)->assertJson(['code' => GeneralErrorCode::NotFound->value]);
});

it('answers a wrong http method with the method not allowed code', function () {
    // Given
    $route = '/login';

    // When
    $response = $this->deleteJson($route);

    // Then
    $response->assertStatus(405)->assertJson(['code' => GeneralErrorCode::MethodNotAllowed->value]);
});

it('answers a throttled request with the rate limit code and keeps Retry-After', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'throttle-code@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.1']);
    $attempt = fn () => $this->postJson('/login', ['email' => 'throttle-code@example.com', 'password' => 'wrong']);
    collect(range(1, 5))->each(fn () => $attempt());

    // When
    $response = $attempt();

    // Then
    $response->assertStatus(429)->assertJson(['code' => GeneralErrorCode::RateLimitExceeded->value]);
    expect($response->headers->get('Retry-After'))->not->toBeNull();
    expect($response->headers->get('X-RateLimit-Limit'))->not->toBeNull();
});

it('never echoes the message of an unexpected exception, which may carry a secret', function () {
    // Given
    $leaky = new RuntimeException('SELECT * FROM users WHERE api_key = "sk_live_supersecret"');

    // When
    $response = app(Handler::class)->render(Request::create('/anything'), $leaky);
    $body = $response->getContent();

    // Then
    expect($response->getStatusCode())->toBe(500);
    expect($body)->toContain((string) GeneralErrorCode::InternalError->value);
    expect($body)->not->toContain('sk_live_supersecret');
    expect($body)->not->toContain('SELECT');
});

it('hides the debug block when debug mode is off', function () {
    // Given
    config(['app.debug' => false]);

    // When
    $response = app(Handler::class)->render(Request::create('/anything'), new RuntimeException('boom'));

    // Then
    expect($response->getContent())->not->toContain('debug');
});
