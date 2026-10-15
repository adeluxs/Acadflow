<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckSessionTimeout;
use App\Http\Middleware\ConcurrentSessionLimitMiddleware;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureApiAccountReady;
use App\Http\Middleware\EnsureAiFeatureEnabled;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\FeatureFlagMiddleware;
use App\Http\Middleware\FeatureAccessMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\RequestCorrelationId;
use App\Http\Middleware\TwoFactorMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use App\Support\Security\RetryAfter;
use App\Support\Errors\UserFacingError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Give every request a correlation ID that can be shown safely to users
        // and matched to secure server logs without exposing technical details.
        $middleware->prepend(RequestCorrelationId::class);

        $middleware->validateCsrfTokens(except: [
            'webhook/payment/*',
            'commerce/webhook/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'feature.flag' => FeatureFlagMiddleware::class,
            'feature.access' => FeatureAccessMiddleware::class,
            'ai.feature' => EnsureAiFeatureEnabled::class,
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
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $headers = $e->getHeaders();
            $retryAfter = RetryAfter::secondsFromHeaders($headers);
            $lead = match (true) {
                $request->is('login', 'api/v1/auth/login') => 'Too many sign-in attempts.',
                $request->is('register', 'api/v1/auth/register') => 'Too many registration attempts.',
                $request->is('forgot-password', 'api/v1/auth/password/reset') => 'Too many password reset requests.',
                $request->is('email/verification-notification', 'api/v1/auth/email/verification-notification', 'verify-email/*') => 'Too many verification requests.',
                $request->is('two-factor-challenge') => 'Too many verification attempts.',
                default => 'Too many requests.',
            };
            $message = RetryAfter::message($lead, $retryAfter);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => $message,
                    'code' => 'TOO_MANY_REQUESTS',
                    'retryable' => true,
                    'retry_after' => $retryAfter,
                    'request_id' => UserFacingError::requestId($request),
                ], 429, array_merge($headers, ['Retry-After' => (string) $retryAfter, 'X-Request-Id' => UserFacingError::requestId($request)]));
            }

            $safeInput = $request->except([
                'password', 'password_confirmation', 'current_password',
                'two_factor_code', 'code', 'token',
            ]);

            return redirect()->back()
                ->withInput($safeInput)
                ->with('error', $message)
                ->with('retry_after', $retryAfter)
                ->withHeaders(array_merge($headers, ['Retry-After' => (string) $retryAfter]));
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'code' => 'SESSION_EXPIRED',
                    'message' => 'Your session has expired. Please sign in again.',
                    'retryable' => false,
                    'request_id' => UserFacingError::requestId($request),
                ], 419, ['X-Request-Id' => UserFacingError::requestId($request)]);
            }

            return response()->redirectToRoute('login')
                ->with('error', 'Your session has expired. Please log in again.');
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) return null;

            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$field] = array_map(
                    fn ($message) => UserFacingError::safeMessage((string) $message, 'The value provided is invalid.'),
                    (array) $messages
                );
            }

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Please check the highlighted fields and try again.',
                'retryable' => false,
                'errors' => $errors,
                'request_id' => UserFacingError::requestId($request),
            ], 422, ['X-Request-Id' => UserFacingError::requestId($request)]);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) return null;

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => 'AUTHENTICATION_REQUIRED',
                'message' => 'Please sign in to continue.',
                'retryable' => false,
                'request_id' => UserFacingError::requestId($request),
            ], 401, ['X-Request-Id' => UserFacingError::requestId($request)]);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) return null;

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => 'You do not have permission to perform this action.',
                'retryable' => false,
                'request_id' => UserFacingError::requestId($request),
            ], 403, ['X-Request-Id' => UserFacingError::requestId($request)]);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) return null;
            $status = $exception->getStatusCode();
            if ($status >= 500) return null;

            [$code, $message, $retryable] = match ($status) {
                400 => ['BAD_REQUEST', 'The request could not be processed. Please review it and try again.', false],
                401 => ['AUTHENTICATION_REQUIRED', 'Please sign in to continue.', false],
                403 => ['ACCESS_DENIED', 'You do not have permission to perform this action.', false],
                404 => ['NOT_FOUND', 'The requested resource could not be found.', false],
                408 => ['REQUEST_TIMEOUT', 'This request is taking longer than expected. Please try again.', true],
                409 => ['REQUEST_CONFLICT', 'The request conflicts with the current state. Refresh the information and try again.', false],
                422 => ['REQUEST_NOT_PROCESSABLE', UserFacingError::safeMessage($exception->getMessage(), 'The request could not be completed with the information provided.'), false],
                429 => ['TOO_MANY_REQUESTS', 'Too many requests. Please try again shortly.', true],
                default => ['REQUEST_FAILED', UserFacingError::safeMessage($exception->getMessage(), 'The request could not be completed.'), false],
            };

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => $code,
                'message' => $message,
                'retryable' => $retryable,
                'request_id' => UserFacingError::requestId($request),
            ], $status, ['X-Request-Id' => UserFacingError::requestId($request)]);
        });

        // Final safety boundary for transport/framework failures. Known HTTP,
        // validation and authorization exceptions keep Laravel's normal handling.
        // In production (even if APP_DEBUG was accidentally enabled) raw exception
        // pages are never returned to normal users or API clients.
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)
                || $exception instanceof TokenMismatchException
                || $exception instanceof ThrottleRequestsException) {
                return null;
            }

            $isTransportFailure = $exception instanceof \Illuminate\Http\Client\ConnectionException
                || str_contains(strtolower($exception::class), 'guzzlehttp')
                || str_contains(strtolower($exception::class), 'connectexception')
                || str_contains(strtolower($exception::class), 'requestexception');

            $mustRenderSafely = $isTransportFailure
                || $request->expectsJson()
                || $request->is('api/*')
                || app()->environment('production')
                || ! config('app.debug');

            if (! $mustRenderSafely) {
                return null;
            }

            $safe = UserFacingError::fromThrowable($exception, $request);
            Log::error('Request failed and was converted to a safe user-facing response.', [
                'request_id' => $safe->requestId,
                'user_id' => $request->user()?->id,
                'operation' => optional($request->route())->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'exception_class' => $exception::class,
                'internal_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'retryable' => $safe->retryable,
                'user_error_code' => $safe->code,
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json($safe->toArray(), $safe->status, [
                    'X-Request-Id' => $safe->requestId,
                    'Cache-Control' => 'no-store',
                ]);
            }

            // Preserve form input but never echo sensitive values. Unknown POST/
            // transactional operations are not given an automatic retry action.
            if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
                return redirect()->back()
                    ->withInput($request->except([
                        'password', 'password_confirmation', 'current_password',
                        'two_factor_code', 'code', 'token', 'otp', 'secret',
                        'card_number', 'cvv', 'cvc',
                    ]))
                    ->with('error', $safe->message)
                    ->with('request_id', $safe->requestId);
            }

            return response()->view('errors.request-failed', [
                'message' => $safe->message,
                'requestId' => $safe->requestId,
                'retryable' => $safe->retryable,
                'statusCode' => $safe->status,
            ], $safe->status, ['X-Request-Id' => $safe->requestId, 'Cache-Control' => 'no-store']);
        });
    })->create();
