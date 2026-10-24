<?php

declare(strict_types=1);

it('throttles every route after 30 requests per minute, not only the api group', function () {
    // Given
    $this->withServerVariables(['REMOTE_ADDR' => '10.4.0.1']);

    // When
    $accepted = collect(range(1, 30))->map(fn () => $this->get('/')->status());
    $thirtyFirst = $this->get('/');

    // Then
    expect($accepted->unique()->all())->toBe([200]);
    $thirtyFirst->assertStatus(429);
});

it('counts the global limit per ip, so a different client is not punished', function () {
    // Given
    $this->withServerVariables(['REMOTE_ADDR' => '10.5.0.1']);
    collect(range(1, 30))->each(fn () => $this->get('/'));
    $this->get('/')->assertStatus(429);

    // When
    $this->withServerVariables(['REMOTE_ADDR' => '10.5.0.2']);
    $otherClient = $this->get('/');

    // Then
    $otherClient->assertStatus(200);
});
