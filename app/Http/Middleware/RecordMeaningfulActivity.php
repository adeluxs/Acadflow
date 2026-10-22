<?php

namespace App\Http\Middleware;

use App\Services\MeaningfulActivityService;
use Closure;
use Illuminate\Http\Request;

class RecordMeaningfulActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $user = $request->user();
        if (! $user || $response->getStatusCode() >= 400 || $request->isMethod('HEAD') || $request->ajax()
            || str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '')), 'prefetch')
            || $request->is('login', 'logout', 'browser-push/*', 'notifications/unread-count', 'notification-events/*', '*autosave*', '*draft*', '*status*', '*progress*')
            || str_contains((string) $request->route()?->getName(), 'poll')) return $response;
        $document = $request->isMethod('GET') && ! $request->expectsJson()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && $request->header('Sec-Fetch-Dest', 'document') === 'document';
        $mutation = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && ! $request->is('api/*') && ! $request->expectsJson()
            && ! $request->hasHeader('X-Background-Request');
        if ($document || $mutation) app(MeaningfulActivityService::class)->record($user);

        return $response;
    }
}
