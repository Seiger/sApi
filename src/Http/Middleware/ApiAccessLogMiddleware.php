<?php namespace Seiger\sApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Logging\AccessLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiAccessLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = AccessLogger::init($request);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $exception) {
            $status = 500;
            if ($exception instanceof HttpExceptionInterface) {
                $status = (int)$exception->getStatusCode();
            }

            $context = [
                'request_id' => $requestId,
                'method' => strtoupper($request->getMethod()),
                'path' => $request->getPathInfo(),
                'status' => $status,
                'exception' => $exception,
            ];

            try {
                Log::channel('sapi')->log($status >= 500 ? 'error' : 'warning', 'Unhandled API exception', $context);
            } catch (\Throwable) {
                try {
                    Log::log($status >= 500 ? 'error' : 'warning', 'Unhandled API exception', $context);
                } catch (\Throwable) {
                    // Never fail the request because of logging issues.
                }
            }

            $response = ApiResponse::error('Internal server error.', 500, (object) []);
        }

        AccessLogger::rememberResponse($response);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            AccessLogger::log($request, $response);
        } catch (\Throwable) {
            // Never fail the request because of access log issues.
        }
    }
}
