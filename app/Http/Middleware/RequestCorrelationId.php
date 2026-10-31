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
        $profiling = (bool) config('performance.server_timing');
        $queries = 0;
        $queryMs = 0.0;
        if ($profiling) {
            // Counts only; SQL text and bindings never leave the server.
            \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries, &$queryMs): void {
                $queries++;
                $queryMs += (float) $query->time;
            });
        }
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $duration = (microtime(true) - $request->attributes->get('request_started_at')) * 1000;
        if ($profiling) {
            $response->headers->set('Server-Timing', 'app;dur='.number_format($duration, 2, '.', ''), false);
            $response->headers->set('Server-Timing', 'db;desc="'.$queries.' queries";dur='.number_format($queryMs, 2, '.', ''), false);
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
