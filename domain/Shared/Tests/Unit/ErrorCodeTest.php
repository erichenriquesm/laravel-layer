<?php

declare(strict_types=1);

use Domain\Auth\Exceptions\AuthErrorCode;
use Domain\Shared\Exceptions\GeneralErrorCode;

function allErrorCodes(): array
{
    return array_merge(GeneralErrorCode::cases(), AuthErrorCode::cases());
}

it('never reuses a number across catalogues', function () {
    // Given
    $codes = array_map(fn ($case) => $case->value, allErrorCodes());

    // When
    $duplicates = array_keys(array_filter(array_count_values($codes), fn (int $n) => $n > 1));

    // Then
    expect($duplicates)->toBe([]);
});

it('keeps every code inside the numeric range its catalogue owns', function () {
    // Given
    // general owns 1000-1099, auth owns 1100-1199

    // When / Then
    foreach (GeneralErrorCode::cases() as $case) {
        expect($case->value)->toBeGreaterThanOrEqual(1000)->toBeLessThanOrEqual(1099);
    }

    foreach (AuthErrorCode::cases() as $case) {
        expect($case->value)->toBeGreaterThanOrEqual(1100)->toBeLessThanOrEqual(1199);
    }
});

it('exposes opaque numbers on the wire, never a semantic string', function () {
    // Given
    $cases = allErrorCodes();

    // When / Then
    foreach ($cases as $case) {
        expect($case->value)->toBeInt();
    }
});

it('keeps a distinct internal description for every code', function () {
    // Given
    $cases = allErrorCodes();

    // When
    $descriptions = array_map(fn ($case) => $case->description(), $cases);

    // Then
    expect($descriptions)->each->not->toBeEmpty();
    expect(count(array_unique($descriptions)))->toBe(count($cases));
});

it('gives all auth codes the same generic public message, so the wire reveals nothing', function () {
    // Given
    // description() names the real failure; publicMessage() is what the client sees
    $messages = array_map(fn (AuthErrorCode $case) => $case->publicMessage(), AuthErrorCode::cases());

    // When
    $distinct = array_unique($messages);

    // Then
    expect($distinct)->toHaveCount(1);
    expect($distinct[array_key_first($distinct)])->toBe('Authentication failed');
});

it('keeps the detailed meaning out of the public message', function () {
    // Given
    $case = AuthErrorCode::InvalidCredentials;

    // When / Then
    // the real reason lives only in description(), never in what the client receives
    expect($case->description())->toBe('The email or the password is wrong');
    expect($case->publicMessage())->not->toContain('email');
    expect($case->publicMessage())->not->toContain('password');
});
