<?php

declare(strict_types=1);

use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\AccessTokenDTO;
use Domain\Auth\DTOs\LoginDTO;
use Mockery\MockInterface;

it('swaps the bound action for a hand written fake', function () {
    // Given
    $fake = new class implements LoginContract
    {
        public function handle(LoginDTO $input): AccessTokenDTO
        {
            return new AccessTokenDTO(token: 'fake-token');
        }
    };
    $this->app->instance(LoginContract::class, $fake);

    // When
    $response = $this->postJson('/login', ['email' => 'anyone@example.com', 'password' => 'whatever']);

    // Then
    $response->assertStatus(200)->assertExactJson(['token' => 'fake-token']);
});

it('swaps the bound action for a mockery double and asserts the call', function () {
    // Given
    $this->mock(LoginContract::class, function (MockInterface $mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->withArgs(fn (LoginDTO $dto) => $dto->email === 'anyone@example.com')
            ->andReturn(new AccessTokenDTO(token: 'mocked-token'));
    });

    // When
    $response = $this->postJson('/login', ['email' => 'anyone@example.com', 'password' => 'whatever']);

    // Then
    $response->assertStatus(200)->assertExactJson(['token' => 'mocked-token']);
});

it('resolves the real action again once the swap is gone', function () {
    // Given
    // no swap in this test: the container falls back to AuthDomainServiceProvider's binding

    // When
    $resolved = port(LoginContract::class);

    // Then
    expect($resolved)->toBeInstanceOf(Domain\Auth\Actions\Login::class);
});
