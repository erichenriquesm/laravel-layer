<?php

namespace Tests\Regression\RegisterUser\Services;

use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Tests\TestCase;
use Faker\Factory as Faker;

final class RegisterUserTest extends TestCase
{
    protected $faker;
    protected $registerUserDTO;
    protected $registerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        $name =  $this->faker->name;
        $email =  $this->faker->email;
        $password =  $this->faker->password;

        $this->registerUserDTO = new RegisterUserDTO(
            name: $name,
            email: $email,
            password: $password
        );

        $this->registerUser = app(RegisterUserContract::class);
    }

    public function testRegisterUserThrowsExceptionWithInvalidDTO()
    {
        try {
            $this->registerUser->exec(null);
    
        } catch(\TypeError $e) {
            $this->assertStringContainsString('Argument #1 ($input) must be of type Domain\Auth\DTOs\RegisterUserDTO', $e->getMessage());
        }
    }

    public function testValidRegisterUser()
    {
        $result = $this->registerUser->exec(
            $this->registerUserDTO
        );

        expect($result)->toBe(['message' => 'User registered']);
    }
}
