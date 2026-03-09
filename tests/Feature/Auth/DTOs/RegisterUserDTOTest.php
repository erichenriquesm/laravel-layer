<?php

use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Shared\DomainTypes\Email;
use Faker\Factory as Faker;

beforeEach(function () {
    $this->faker = Faker::create();
});

test('register user dto throws exception with invalid name type', function () {
    try {
        /** @var mixed $invalidName */
        $invalidName = null;
        new RegisterUserDTO(
            name: $invalidName,
            email: new Email('teste@email.com'),
            password: 'senha123'
        );

        $this->fail('Esperado TypeError ao passar null para name');
    } catch (\TypeError $e) {
        $this->assertStringContainsString('must be of type string', $e->getMessage());
    }
});

test('register user dto throws exception with invalid email type', function () {
    try {
        /** @var mixed $invalidEmail */
        $invalidEmail = ['email' => 'fulano@email.com'];
        new RegisterUserDTO(
            name: 'Fulano',
            email: $invalidEmail,
            password: 'senha123'
        );

        $this->fail('Esperado TypeError ao passar array para email');
    } catch (\TypeError $e) {
        $this->assertStringContainsString('must be of type Domain\Shared\DomainTypes\Email', $e->getMessage());
    }
});

test('register user dto throws exception with invalid password type', function () {
    try {
        /** @var mixed $invalidPassword */
        $invalidPassword = null;
        new RegisterUserDTO(
            name: 'Fulano',
            email: new Email('teste@email.com'),
            password: $invalidPassword
        );

        $this->fail('Esperado TypeError ao passar array para email');
    } catch (\TypeError $e) {
        $this->assertStringContainsString('must be of type string', $e->getMessage());
    }
});

test('valid register user dto', function () {
    $name = $this->faker->name;
    $email = new Email($this->faker->email);
    $password = $this->faker->password;

    $dto = new RegisterUserDTO(
        name: $name,
        email: $email,
        password: $password
    );

    $this->assertEquals($name, $dto->name);
    $this->assertEquals($email->getValue(), $dto->email->getValue());
    $this->assertEquals($password, $dto->password);
});
