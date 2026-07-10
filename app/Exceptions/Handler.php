<?php

namespace App\Exceptions;

use Domain\Auth\Exceptions\AuthErrorCode;
use Domain\Shared\Contracts\HasErrorCode;
use Domain\Shared\Exceptions\GeneralErrorCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
     * Every error answers with the same shape, so a client can branch on `code` alone and
     * never has to parse a human message or guess from the status.
     */
    public function render($request, Throwable $e): Response
    {
        # Passport builds a spec-compliant OAuth error on itself (invalid_grant, etc). Reshaping
        # /oauth/* into our {code, message} would break clients that expect the OAuth format.
        if ($e instanceof OAuthServerException) {
            return $e->render($request);
        }

        [$code, $status] = $this->classify($e);

        $body = [
            'code'    => $code,
            'message' => $this->messageFor($code),
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        if ($status === 500 && config('app.debug')) {
            $body['debug'] = [
                'exception' => $e::class,
                'at'        => $e->getFile().':'.$e->getLine(),
            ];
        }

        # ThrottleRequestsException carries Retry-After and the X-RateLimit-* headers on itself.
        # Building the response from scratch would silently drop them.
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return response()->json($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: int} the error code and the HTTP status
     */
    private function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof HasErrorCode => [$e->errorCode(), $e->httpStatus()],
            $e instanceof ValidationException => [GeneralErrorCode::ValidationFailed->value, 422],
            $e instanceof ThrottleRequestsException => [GeneralErrorCode::RateLimitExceeded->value, 429],
            $e instanceof AuthenticationException => [AuthErrorCode::Unauthenticated->value, 401],
            $e instanceof NotFoundHttpException => [GeneralErrorCode::NotFound->value, 404],
            $e instanceof MethodNotAllowedHttpException => [GeneralErrorCode::MethodNotAllowed->value, 405],
            $e instanceof HttpExceptionInterface => [$this->codeForStatus($e->getStatusCode()), $e->getStatusCode()],
            default => [GeneralErrorCode::InternalError->value, 500],
        };
    }

    private function codeForStatus(int $status): int
    {
        return match ($status) {
            401 => AuthErrorCode::Unauthenticated->value,
            404 => GeneralErrorCode::NotFound->value,
            405 => GeneralErrorCode::MethodNotAllowed->value,
            429 => GeneralErrorCode::RateLimitExceeded->value,
            default => GeneralErrorCode::InternalError->value,
        };
    }

    /**
     * The client only ever sees publicMessage(): a generic, safe text. The exception's own
     * message (which may spell out the failure, a query or a secret) never reaches the wire.
     */
    private function messageFor(int $code): string
    {
        return AuthErrorCode::tryFrom($code)?->publicMessage()
            ?? GeneralErrorCode::tryFrom($code)?->publicMessage()
            ?? GeneralErrorCode::InternalError->publicMessage();
    }
}
