<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\Response;

final class AccessTokenDTO extends Data
{
    public function __construct(
        public readonly string $token,
    ) {}

    /**
     * Login creates no resource, so laravel-data's default 201 for POST does not apply.
     */
    protected function calculateResponseStatus(Request $request): int
    {
        return Response::HTTP_OK;
    }
}
