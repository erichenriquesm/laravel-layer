<?php
namespace Domain\Shared\Helpers;

use Illuminate\Http\JsonResponse;

final class APIResponse 
{
    const STATUS_SUCCESS = "SUCCESS";
    const STATUS_UNPROCESSABLE_ENTITY = "UNPROCESSABLE_ENTITY";
    const STATUS_INTERNAL_SERVER_ERROR = "INTERNAL_SERVER_ERROR";
    const STATUS_UNAUTHORIZED = "INTERNAL_SERVER_ERROR";

    private static function generateResponse(string $status, array|string $messages, int $statusCode): JsonResponse
    {
        return response()->json([
            'status'   => $status,
            'messages' => is_string($messages) ? json_decode($messages, true) : $messages
        ], $statusCode);
    }

    public static function success(array|string $messages): JsonResponse
    {
        return self::generateResponse(self::STATUS_SUCCESS, $messages, 200);
    }

    public static function unprocessableEntity(array|string $messages): JsonResponse
    {
        return self::generateResponse(self::STATUS_UNPROCESSABLE_ENTITY, $messages, 422);
    }

    public static function internalServerError(array|string $messages): JsonResponse
    {
        return self::generateResponse(self::STATUS_INTERNAL_SERVER_ERROR, $messages, 500);
    }

    public static function unauthorized(array|string $messages): JsonResponse
    {
        return self::generateResponse(self::STATUS_UNAUTHORIZED, $messages, 401);
    }
}

