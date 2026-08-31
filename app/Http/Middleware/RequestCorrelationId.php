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
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
