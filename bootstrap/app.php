<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckSessionTimeout;
use App\Http\Middleware\ConcurrentSessionLimitMiddleware;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureApiAccountReady;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\FeatureFlagMiddleware;
use App\Http\Middleware\FeatureAccessMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SubscriptionFeatureMiddleware;
use App\Http\Middleware\TwoFactorMiddleware;
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
        $middleware->validateCsrfTokens(except: [
            'webhook/payment/*',
            'commerce/webhook/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'feature.flag' => FeatureFlagMiddleware::class,
            'feature.access' => FeatureAccessMiddleware::class,
            'subscription.feature' => SubscriptionFeatureMiddleware::class,
            'webhook.verify' => \App\Http\Middleware\VerifyPaymentWebhook::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'api.account.ready' => EnsureApiAccountReady::class,
            'two-factor.authenticated' => EnsureTwoFactorAuthenticated::class,
        ]);
        
        // Add maintenance mode check (runs on web routes)
        $middleware->append(CheckMaintenanceMode::class);

        // Feature availability requires the authenticated session to be available.
        // Append to the web group after StartSession rather than making it a
        // pre-session global middleware. Protected API groups add the same
        // middleware after auth:sanctum in routes/api.php.
        $middleware->web(append: [FeatureAccessMiddleware::class]);
        
        // Add session timeout check (runs on web routes after auth)
        $middleware->append(CheckSessionTimeout::class);
        
        // Add concurrent session limit check (runs on web routes after auth)
        $middleware->append(ConcurrentSessionLimitMiddleware::class);
        
        // Add 2FA check (runs on web routes after auth)
        $middleware->append(TwoFactorMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e) {
            return response()->redirectToRoute('login')
                ->with('error', 'Your session has expired. Please log in again.');
        });
    })->create();
