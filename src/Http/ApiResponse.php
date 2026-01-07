<?php namespace Seiger\sApi\Http;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(object|array $object = [], string $message = '', int $httpCode = 200): JsonResponse
    {
        return self::json(true, $message, $object, $httpCode);
    }

    public static function error(string $message, int $httpCode, object|array $object = []): JsonResponse
    {
        return self::json(false, $message, $object, $httpCode);
    }

    private static function json(bool $success, string $message, object|array $object, int $httpCode): JsonResponse
    {
        if (is_array($object) && $object === []) {
            $object = (object) [];
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'object' => $object,
            'code' => $httpCode,
        ], $httpCode);
    }
}
