<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Errors\UserFacingError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = UserFacingError::requestId($request);
        $request->attributes->set('request_started_at', microtime(true));
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $duration = (microtime(true) - $request->attributes->get('request_started_at')) * 1000;
        if (config('performance.server_timing')) {
            $response->headers->set('Server-Timing', 'app;dur='.number_format($duration, 2, '.', ''), false);
        }
        if (config('performance.log_slow_requests') && $duration >= config('performance.slow_request_ms', 1000)) {
            \Illuminate\Support\Facades\Log::warning('Slow application request', [
                'request_id' => $requestId,
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'duration_ms' => round($duration, 2),
                'status' => $response->getStatusCode(),
                'peak_memory_bytes' => memory_get_peak_usage(true),
            ]);
        }

        return $response;
    }
}
