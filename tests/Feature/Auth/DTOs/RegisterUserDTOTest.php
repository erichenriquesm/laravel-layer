<?php

namespace Tests\Feature\RegisterUser\DTOs;

use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Shared\DomainTypes\Email;
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
                email: new Email('teste@email.com'),
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
            $this->assertStringContainsString('must be of type Domain\Shared\DomainTypes\Email', $e->getMessage());
        }
    }


    public function testRegisterUserDTOThrowsExceptionWithInvalidPasswordType()
    {
        try {
            new RegisterUserDTO(
                name: 'Fulano',
                email: new Email('teste@email.com'),
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
        $email =  new Email($this->faker->email);
        $password =  $this->faker->password;
        $dto = new RegisterUserDTO(
            name: $name,
            email: $email,
            password: $password
        );

        $this->assertEquals($name, $dto->name);
        $this->assertEquals($email->getValue(), $dto->email->getValue());
        $this->assertEquals($password, $dto->password);
    }
}
