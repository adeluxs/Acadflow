<?php

use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SubscriptionFeatureMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'subscription.feature' => SubscriptionFeatureMiddleware::class,
            'webhook.verify' => \App\Http\Middleware\VerifyPaymentWebhook::class,
        ]);
        
        // Add maintenance mode check (runs on web routes)
        $middleware->append(\App\Http\Middleware\CheckMaintenanceMode::class);
        
        // Add session timeout check (runs on web routes after auth)
        $middleware->append(\App\Http\Middleware\CheckSessionTimeout::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e) {
            return response()->redirectToRoute('login')
                ->with('error', 'Your session has expired. Please log in again.');
        });
    })->create();
