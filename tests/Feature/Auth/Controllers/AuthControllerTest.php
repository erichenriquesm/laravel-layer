<?php

use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->faker = Faker::create();
});

test('login route without credentials', function () {
    $response = $this->post('/login');

    $response->assertStatus(422);
});

test('login route with invalid credentials', function () {
    $response = $this->post('/login', [
        'email'    => 'example@gmail.com',
        'password' => '123456',
    ]);

    $response->assertStatus(401);
});

test('login route with valid credentials', function () {
    $response = $this->post('/login', [
        'email'    => 'layer@gmail.com',
        'password' => '123Mudar!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'messages' => [
                'token',
            ],
        ]);
});

test('register route without credentials', function () {
    $response = $this->post('/register');

    $response->assertStatus(422);
});

test('register route with invalid email', function () {
    $payload = [
        'name'                  => $this->faker->name,
        'email'                 => 'invalid-email',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $response = $this->post('/register', $payload);

    $response->assertStatus(422);
});

test('register route with password mismatch', function () {
    $payload = [
        'name'                  => $this->faker->name,
        'email'                 => $this->faker->unique()->safeEmail,
        'password'              => 'secret123',
        'password_confirmation' => 'different',
    ];

    $response = $this->post('/register', $payload);

    $response->assertStatus(422);
});

test('register route successfully', function () {
    $payload = [
        'name'                  => $this->faker->name,
        'email'                 => $this->faker->unique()->safeEmail,
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $response = $this->post('/register', $payload);

    $response->assertStatus(200);
});

test('register route with duplicated email', function () {
    $email = $this->faker->unique()->safeEmail;

    DB::table('users')->insert([
        'name'     => 'Existing User',
        'email'    => $email,
        'password' => Hash::make('password'),
    ]);

    $payload = [
        'name'                  => $this->faker->name,
        'email'                 => $email,
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $response = $this->post('/register', $payload);

    $response->assertStatus(422);
});

test('me route without authentication', function () {
    $response = $this->get('/me');

    $response->assertStatus(401);
});

test('me route with authenticated user returns user', function () {
    $user = User::create([
        'name'     => 'Auth Test User',
        'email'    => 'auth-me@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = $this->get('/me');

    $response->assertStatus(200)
        ->assertJson([
            'email' => $user->email,
        ]);
});

