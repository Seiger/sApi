<?php namespace Seiger\sApi\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    public static function success(object|array $data = [], string $message = '', int $httpCode = 200, object|array $meta = []): JsonResponse
    {
        if (is_object($meta)) {
            $meta = (array)$meta;
        }

        if (is_array($data) && $data === [] && $meta === []) {
            $data = (object) [];
        }

        $payload = ['data' => $data];
        foreach ($meta as $key => $value) {
            if (!in_array($key, ['data', 'message'], true)) {
                $payload[$key] = $value;
            }
        }

        if ($message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $httpCode);
    }

    public static function error(string $detail, int $httpCode, object|array $extensions = [], ?string $title = null, ?string $type = null): JsonResponse
    {
        $payload = [
            'type' => $type ?: self::problemType($httpCode),
            'title' => $title ?: (Response::$statusTexts[$httpCode] ?? 'Error'),
            'status' => $httpCode,
            'detail' => $detail,
        ];

        if (is_object($extensions)) {
            $extensions = (array)$extensions;
        }
        if (is_array($extensions)) {
            foreach ($extensions as $key => $value) {
                if (!in_array($key, ['type', 'title', 'status', 'detail'], true)) {
                    $payload[$key] = $value;
                }
            }
        }

        return response()
            ->json($payload, $httpCode)
            ->header('Content-Type', 'application/problem+json');
    }

    private static function problemType(int $httpCode): string
    {
        return 'about:blank';
    }
}
