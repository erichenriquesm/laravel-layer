<?php

declare(strict_types=1);

use App\Models\User;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\DTOs\UserDTO;
use Domain\Auth\Events\UserRegistered;
use Domain\Auth\Listeners\SendWelcomeNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('persists the user and returns the UserDTO of the created resource', function () {
    // Given
    $dto = new RegisterUserDTO(
        name: 'Jane Doe',
        email: 'new-user@example.com',
        password: 'secret123',
    );

    // When
    $result = port(RegisterUserContract::class)->handle($dto);

    // Then
    expect($result)->toBeInstanceOf(UserDTO::class);
    expect($result->name)->toBe('Jane Doe');
    expect($result->email)->toBe('new-user@example.com');
    expect($result->id)->toBeGreaterThan(0);
    $this->assertDatabaseHas('users', ['email' => 'new-user@example.com']);
});

it('announces the UserRegistered fact instead of dispatching background work itself', function () {
    // Given
    Event::fake([UserRegistered::class]);
    $dto = new RegisterUserDTO(name: 'Jane Doe', email: 'event@example.com', password: 'secret123');

    // When
    port(RegisterUserContract::class)->handle($dto);

    // Then
    $userId = User::where('email', 'event@example.com')->value('id');
    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $e): bool => $e->userId === $userId);
});

it('wires the queued welcome listener to the fact in the domain provider', function () {
    // Given
    Event::fake();

    // Then
    Event::assertListening(UserRegistered::class, SendWelcomeNotification::class);
});

it('stores the password hashed, never in plain text', function () {
    // Given
    $dto = new RegisterUserDTO(
        name: 'Jane Doe',
        email: 'hash-check@example.com',
        password: 'secret123',
    );

    // When
    port(RegisterUserContract::class)->handle($dto);

    // Then
    $stored = User::where('email', 'hash-check@example.com')->first();
    expect($stored->password)->not->toBe('secret123');
    expect(Hash::check('secret123', $stored->password))->toBeTrue();
});

it('registers through the route and responds 201 with the created resource', function () {
    // Given
    $payload = [
        'name'                  => 'Jane Doe',
        'email'                 => 'route-register@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    // When
    $response = $this->postJson('/register', $payload);

    // Then
    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'name', 'email'])
        ->assertJson(['email' => 'route-register@example.com']);
    expect($response->json())->not->toHaveKey('password');
});

it('rejects a registration with no fields at all', function () {
    // Given
    $payload = [];

    // When
    $response = $this->postJson('/register', $payload);

    // Then
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects an email with an invalid format', function () {
    // Given
    $payload = [
        'name'                  => 'Jane Doe',
        'email'                 => 'not-an-email',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    // When
    $response = $this->postJson('/register', $payload);

    // Then
    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('rejects a registration when the password confirmation does not match', function () {
    // Given
    $payload = [
        'name'                  => 'Jane Doe',
        'email'                 => 'mismatch@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'something-else',
    ];

    // When
    $response = $this->postJson('/register', $payload);

    // Then
    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('rejects an email that is already taken', function () {
    // Given
    User::create([
        'name'     => 'Already There',
        'email'    => 'duplicated@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $payload = [
        'name'                  => 'Jane Doe',
        'email'                 => 'duplicated@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    // When
    $response = $this->postJson('/register', $payload);

    // Then
    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});
