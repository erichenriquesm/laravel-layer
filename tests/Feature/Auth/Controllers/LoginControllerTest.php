<?php

namespace Tests\Feature\Auth\Controllers;

use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    public function testLoginRouteWithoutCredentials(): void
    {
        $response = $this->post('/login');

        $response->assertStatus(422);
    }

    public function testLoginRouteWithInvalidCredentials(): void
    {
        $response = $this->post('/login', [
            'email'     => 'example@gmail.com',
            'password'  => '123456'
        ]);

        $response->assertStatus(401);
    }

    public function testLoginRouteWithValidCredentials(): void
    {
        $response = $this->post('/login', [
            'email'     => 'layer@gmail.com',
            'password'  => '123Mudar!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages' => [
                    'token'
                ]
            ]);
    }
}
