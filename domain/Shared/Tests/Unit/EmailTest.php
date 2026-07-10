<?php

declare(strict_types=1);

use Domain\Shared\DomainTypes\Email;

it('holds the address when the format is valid', function () {
    // Given
    $address = 'jane@example.com';

    // When
    $email = new Email($address);

    // Then
    expect($email->getValue())->toBe($address);
});

it('rejects an address with an invalid format', function () {
    // Given
    $address = 'not-an-email';

    // When
    $act = fn () => new Email($address);

    // Then
    expect($act)->toThrow(DomainException::class, 'Invalid e-mail.');
});

it('rejects an empty address', function () {
    // Given
    $address = '';

    // When
    $act = fn () => new Email($address);

    // Then
    expect($act)->toThrow(DomainException::class);
});

it('guards the domain regardless of the HTTP edge validation', function () {
    // Given
    $invalidAddresses = ['no-at-sign', '@no-local-part.com', 'no-domain@', 'spaced out@example.com'];

    // When / Then
    foreach ($invalidAddresses as $address) {
        expect(fn () => new Email($address))->toThrow(DomainException::class);
    }
});
