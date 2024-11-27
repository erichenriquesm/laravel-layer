<?php

namespace Tests\Regression;

use Tests\TestCase;

final class LoginTest extends TestCase
{
    public function test_login_route_without_credentials(): void
    {
        $response = $this->post('/login');

        $response->assertStatus(422);
    }

    public function test_login_route_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email'     => 'example@gmail.com',
            'password'  => '123456'
        ]);

        $response->assertStatus(401);
    }

    public function test_login_route_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email'     => 'eric@greenn.com.br',
            'password'  => '@123Mudar!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages' => [
                    'token'
                ]
            ]);
    }
}
