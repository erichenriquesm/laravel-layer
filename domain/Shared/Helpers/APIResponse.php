<?php

namespace Domain\Shared\Helpers;

use Illuminate\Http\JsonResponse;

class APIResponse {
    static $SUCCESS = "SUCCESS";
    static $UNPROCESSABLE_ENTITY = "UNPROCESSABLE_ENTITY";
    static $INTERNAL_SERVER_ERROR = "INTERNAL_SERVER_ERROR";

    public static function success(array|string $messages) : JsonResponse
    {
        return response()->json([
            'status'   => self::$SUCCESS,
            'messages' => is_string($messages) ? json_decode($messages) : $messages
        ], 200);
    }

    public static function unprocessableEntity(array|string $messages) : JsonResponse
    {
        return response()->json([
            'status'   => self::$UNPROCESSABLE_ENTITY,
            'messages' => is_string($messages) ? json_decode($messages) : $messages
        ], 422);
    }

    public static function internalServerError(array|string $messages) : JsonResponse
    {
        return response()->json([
            'status'   => self::$INTERNAL_SERVER_ERROR,
            'messages' => is_string($messages) ? json_decode($messages) : $messages
        ], 500);
    }
}
