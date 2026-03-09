<?php

use Domain\Auth\DTOs\LoginDTO;

test('login dto throws exception with invalid email type', function () {
    try {
        /** @var mixed $invalidEmail */
        $invalidEmail = ['invalid'];

        new LoginDTO(
            email: $invalidEmail,
            password: 'secret123'
        );

        $this->fail('Esperado TypeError ao passar array para email');
    } catch (\TypeError $e) {
        $this->assertStringContainsString('must be of type string', $e->getMessage());
    }
});

test('login dto throws exception with invalid password type', function () {
    try {
        /** @var mixed $invalidPassword */
        $invalidPassword = null;

        new LoginDTO(
            email: 'user@example.com',
            password: $invalidPassword
        );

        $this->fail('Esperado TypeError ao passar null para password');
    } catch (\TypeError $e) {
        $this->assertStringContainsString('must be of type string', $e->getMessage());
    }
});

test('valid login dto', function () {
    $email = 'user@example.com';
    $password = 'secret123';

    $dto = new LoginDTO(
        email: $email,
        password: $password
    );

    $this->assertEquals($email, $dto->email);
    $this->assertEquals($password, $dto->password);
});

