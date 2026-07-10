<?php

namespace App\Exceptions;

use Domain\Auth\Exceptions\InvalidCredentialsException;
use Domain\Auth\Exceptions\InvalidRefreshTokenException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (InvalidCredentialsException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 401);
        });

        $this->renderable(function (InvalidRefreshTokenException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 401);
        });
    }

    /**
     * The app exposes API routes only, so errors must never redirect.
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return true;
    }
}
