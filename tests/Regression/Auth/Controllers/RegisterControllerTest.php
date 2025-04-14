<?php

namespace Tests\Regression\RegisterUser\Controllers;

use Tests\TestCase;
use Faker\Factory as Faker;
use Domain\Auth\Contracts\RegisterUserContract;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

final class RegisterControllerTest extends TestCase
{
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        // Mock do contrato
        $mock = \Mockery::mock(RegisterUserContract::class);
        $mock->shouldReceive('exec')->andReturnUsing(function ($dto) {
            return [
                'id' => 1,
                'name' => $dto->name,
                'email' => $dto->email,
            ];
        });

        App::instance(RegisterUserContract::class, $mock);
    }

    public function testRegisterRouteWithoutCredentials(): void
    {
        $response = $this->post('/register');

        $response->assertStatus(422);
    }

    public function testRegisterRouteWithInvalidEmail(): void
    {
        $payload = [
            'name' => $this->faker->name,
            'email' => 'invalid-email',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(422);
    }

    public function testRegisterRouteWithPasswordMismatch(): void
    {
        $payload = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'secret123',
            'password_confirmation' => 'different',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(422);
    }

    public function testRegisterRouteSuccessfully(): void
    {
        $payload = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(200);
    }

    public function testRegisterRouteWithDuplicatedEmail(): void
    {
        $email = $this->faker->unique()->safeEmail;

        // Cria um usuário no banco para testar o email duplicado
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $payload = [
            'name' => $this->faker->name,
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(422);
    }
}
