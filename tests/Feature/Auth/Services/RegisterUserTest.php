<?php

use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\Services\RegisterUser;
use Domain\Shared\DomainTypes\Email;
use Faker\Factory as Faker;

beforeEach(function () {
    $this->faker = Faker::create();

    $name = $this->faker->name;
    $email = new Email($this->faker->email);
    $password = $this->faker->password;

    $this->registerUserDTO = new RegisterUserDTO(
        name: $name,
        email: $email,
        password: $password
    );

    $this->registerUser = app(RegisterUser::class);
});

test('register user throws exception with invalid dto', function () {
    $this->expectException(\TypeError::class);
    $this->expectExceptionMessage('Argument #1 ($input) must be of type Domain\Auth\DTOs\RegisterUserDTO');

    /** @var mixed $invalid */
    $invalid = null;
    $this->registerUser->exec($invalid);
});

test('valid register user', function () {
    $result = $this->registerUser->exec(
        $this->registerUserDTO
    );

    expect($result)->toBe(['message' => 'User registered']);
});

