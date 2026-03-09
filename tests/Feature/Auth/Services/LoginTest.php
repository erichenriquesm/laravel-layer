<?php

use App\Models\User;
use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\Services\Login;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->loginService = app(Login::class);
});

test('login service throws exception with invalid dto', function () {
    $this->expectException(\TypeError::class);
    $this->expectExceptionMessage('Argument #1 ($input) must be of type Domain\Auth\DTOs\LoginDTO');

    /** @var mixed $invalid */
    $invalid = null;
    $this->loginService->exec($invalid);
});

test('login service throws invalid credentials exception', function () {
    $dto = new LoginDTO(
        email: 'invalid@example.com',
        password: 'wrong-password'
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid_credentials');

    $this->loginService->exec($dto);
});

test('login service with valid credentials returns token', function () {
    $user = User::create([
        'name'     => 'Login Service User',
        'email'    => 'service-login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $dto = new LoginDTO(
        email: $user->email,
        password: 'secret123'
    );

    $result = $this->loginService->exec($dto);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('token', $result);
    $this->assertIsString($result['token']);
});

