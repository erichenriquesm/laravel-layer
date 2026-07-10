<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function registerPayload(string $email): array
{
    return [
        'name'                  => 'Jane Doe',
        'email'                 => $email,
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];
}

it('throttles register after 3 attempts from the same ip', function () {
    // Given
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

    // When
    $accepted = collect(range(1, 3))
        ->map(fn (int $i) => $this->postJson('/register', registerPayload("register-{$i}@example.com"))->status());
    $fourth = $this->postJson('/register', registerPayload('register-4@example.com'));

    // Then
    expect($accepted->all())->toBe([201, 201, 201]);
    $fourth->assertStatus(429);
});

it('throttles login after 5 attempts from the same ip', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'victim@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
    $attempt = fn () => $this->postJson('/login', ['email' => 'victim@example.com', 'password' => 'wrong-password']);

    // When
    $rejected = collect(range(1, 5))->map(fn () => $attempt()->status());
    $sixth = $attempt();

    // Then
    expect($rejected->all())->toBe([401, 401, 401, 401, 401]);
    $sixth->assertStatus(429);
});

it('throttles login from a single ip even when the attacker rotates emails', function () {
    // Given
    $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.1']);
    $attemptOn = fn (string $email) => $this->postJson('/login', ['email' => $email, 'password' => 'wrong-password']);

    // When
    $rejected = collect(range(1, 5))->map(fn (int $i) => $attemptOn("target-{$i}@example.com")->status());
    $sixth = $attemptOn('target-6@example.com');

    // Then
    expect($rejected->unique()->all())->toBe([401]);
    $sixth->assertStatus(429);
});

it('throttles a single account after 10 attempts even when the attacker rotates ip addresses', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'victim@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $attemptFrom = function (string $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->postJson('/login', ['email' => 'victim@example.com', 'password' => 'wrong-password']);
    };

    // When
    $rejected = collect(range(1, 10))->map(fn (int $i) => $attemptFrom("10.1.0.{$i}")->status());
    $eleventh = $attemptFrom('10.1.0.11');

    // Then
    expect($rejected->unique()->all())->toBe([401]);
    $eleventh->assertStatus(429);
});

it('keys the account limit on a normalised email, so casing and spacing cannot bypass it', function () {
    // Given
    User::create([
        'name'     => 'Jane Doe',
        'email'    => 'victim@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $variants = ['victim@example.com', 'VICTIM@example.com', ' Victim@Example.com ', 'vIcTiM@ExAmPlE.cOm'];
    $attemptFrom = function (string $ip, string $email) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->postJson('/login', ['email' => $email, 'password' => 'wrong-password']);
    };

    // When
    $rejected = collect(range(1, 10))
        ->map(fn (int $i) => $attemptFrom("10.6.0.{$i}", $variants[$i % 4])->status());
    $eleventh = $attemptFrom('10.6.0.11', 'VICTIM@EXAMPLE.COM');

    // Then
    expect($rejected->unique()->all())->toBe([401]);
    $eleventh->assertStatus(429);
});

it('does not bucket malformed requests that carry no email together', function () {
    // Given
    // without the guard, every request missing an email would share one account bucket.
    $attemptFrom = function (string $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->postJson('/login', ['password' => 'wrong-password']);
    };

    // When
    $statuses = collect(range(1, 15))->map(fn (int $i) => $attemptFrom("10.7.0.{$i}")->status());

    // Then
    expect($statuses->unique()->all())->toBe([422]);
});

it('answers a throttled request with 429 and a Retry-After header', function () {
    // Given
    $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.1']);
    collect(range(1, 3))->each(fn (int $i) => $this->postJson('/register', registerPayload("burst-{$i}@example.com")));

    // When
    $response = $this->postJson('/register', registerPayload('burst-4@example.com'));

    // Then
    $response->assertStatus(429);
    expect($response->headers->get('Retry-After'))->not->toBeNull();
    expect($response->headers->get('X-RateLimit-Limit'))->toBe('3');
    expect($response->headers->get('X-RateLimit-Remaining'))->toBe('0');
});
