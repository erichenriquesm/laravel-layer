<?php

namespace Tests\Regression\RegisterUser\DTOs;

use Domain\Auth\DTOs\RegisterUserDTO;
use Tests\TestCase;
use Faker\Factory as Faker;

final class RegisterUserDTOTest extends TestCase
{
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
    }

    public function testRegisterUserDTOThrowsExceptionWithInvalidNameType()
    {
        try {
            new RegisterUserDTO(
                name: null,
                email: 'teste@email.com',
                password: 'senha123'
            );

            $this->fail('Esperado TypeError ao passar null para name');
        } catch (\TypeError $e) {
            $this->assertStringContainsString('must be of type string', $e->getMessage());
        }
    }

    public function testRegisterUserDTOThrowsExceptionWithInvalidEmailType()
    {
        try {
            new RegisterUserDTO(
                name: 'Fulano',
                email: ['email' => 'fulano@email.com'],
                password: 'senha123'
            );

            $this->fail('Esperado TypeError ao passar array para email');
        } catch (\TypeError $e) {
            $this->assertStringContainsString('must be of type string', $e->getMessage());
        }
    }


    public function testRegisterUserDTOThrowsExceptionWithInvalidPasswordType()
    {
        try {
            new RegisterUserDTO(
                name: 'Fulano',
                email: 'teste@email.com',
                password: null
            );

            $this->fail('Esperado TypeError ao passar array para email');
        } catch (\TypeError $e) {
            $this->assertStringContainsString('must be of type string', $e->getMessage());
        }
    }


    public function testValidRegisterUserDTO()
    {
        $name =  $this->faker->name;
        $email =  $this->faker->email;
        $password =  $this->faker->password;
        $dto = new RegisterUserDTO(
            name: $name,
            email: $email,
            password: $password
        );

        $this->assertEquals($name, $dto->name);
        $this->assertEquals($email, $dto->email);
        $this->assertEquals($password, $dto->password);
    }
}
