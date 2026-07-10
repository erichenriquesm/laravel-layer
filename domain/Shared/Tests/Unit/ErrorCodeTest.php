<?php

declare(strict_types=1);

use Domain\Auth\Exceptions\AuthErrorCode;
use Domain\Shared\Exceptions\GeneralErrorCode;

function allErrorCodes(): array
{
    return array_merge(GeneralErrorCode::cases(), AuthErrorCode::cases());
}

it('never reuses a code across catalogues', function () {
    // Given
    $codes = array_map(fn ($case) => $case->value, allErrorCodes());

    // When
    $duplicates = array_keys(array_filter(array_count_values($codes), fn (int $n) => $n > 1));

    // Then
    expect($duplicates)->toBe([]);
});

it('prefixes every auth code with AUTH_, so the owner is readable in a log line', function () {
    // Given
    $cases = AuthErrorCode::cases();

    // When / Then
    foreach ($cases as $case) {
        expect($case->value)->toStartWith('AUTH_');
    }
});

it('leaves general codes unprefixed, since no domain owns them', function () {
    // Given
    $cases = GeneralErrorCode::cases();

    // When / Then
    foreach ($cases as $case) {
        expect($case->value)->not->toContain('AUTH_');
    }
});

it('keeps every code in SCREAMING_SNAKE_CASE', function () {
    // Given
    $codes = array_map(fn ($case) => $case->value, allErrorCodes());

    // When / Then
    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Z][A-Z0-9_]*$/');
    }
});

it('describes every code, so the catalogue documents itself', function () {
    // Given
    $cases = allErrorCodes();

    // When
    $descriptions = array_map(fn ($case) => $case->description(), $cases);

    // Then
    expect($descriptions)->each->not->toBeEmpty();
    expect(count(array_unique($descriptions)))->toBe(count($cases));
});

it('does not tell a wrong password apart from an unknown email', function () {
    // Given
    // both map to the same code on purpose: telling them apart enables email enumeration

    // When
    $code = AuthErrorCode::InvalidCredentials;

    // Then
    expect($code->value)->toBe('AUTH_INVALID_CREDENTIALS');
    expect($code->description())->toBe('The email or the password is wrong');
});
