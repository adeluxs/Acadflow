<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class UserFacingError
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly bool $retryable,
        public readonly int $status,
        public readonly string $requestId,
    ) {
    }

    public static function fromThrowable(Throwable $exception, Request $request): self
    {
        $requestId = self::requestId($request);
        $message = strtolower($exception->getMessage());
        $isAi = self::isAiRequest($request);
        $isLogin = self::isLoginRequest($request);

        if ($exception instanceof RequestException && $exception->response) {
            $status = $exception->response->status();

            if ($status === 429) {
                return new self(
                    $isAi ? 'AI_PROVIDER_RATE_LIMITED' : 'EXTERNAL_SERVICE_RATE_LIMITED',
                    $isAi ? 'This AI service is temporarily busy. Please try again shortly.' : 'The service is temporarily busy. Please try again shortly.',
                    true,
                    503,
                    $requestId,
                );
            }

            if ($status === 408 || $status === 504) {
                return new self(
                    $isAi ? 'AI_PROVIDER_TIMEOUT' : 'SERVICE_TIMEOUT',
                    $isAi ? 'The AI request took longer than expected. You can try again.' : 'This request is taking longer than expected. Please try again.',
                    true,
                    503,
                    $requestId,
                );
            }

            if (in_array($status, [401, 403, 404], true)) {
                return new self(
                    $isAi ? 'AI_SERVICE_UNAVAILABLE' : 'EXTERNAL_SERVICE_UNAVAILABLE',
                    $isAi ? 'This AI service is currently unavailable.' : 'This service is currently unavailable. Please try again later.',
                    false,
                    503,
                    $requestId,
                );
            }

            if ($status >= 500) {
                return new self(
                    $isAi ? 'AI_PROVIDER_UNAVAILABLE' : 'EXTERNAL_SERVICE_UNAVAILABLE',
                    $isAi ? 'AI is temporarily unavailable. Your work has not been lost. Please try again shortly.' : 'We could not connect to the service right now. Please try again.',
                    true,
                    503,
                    $requestId,
                );
            }
        }

        if ($exception instanceof ConnectionException || self::looksLikeTransportFailure($exception)) {
            if (self::containsAny($message, ['timed out', 'timeout', 'curl error 28'])) {
                return new self(
                    $isAi ? 'AI_PROVIDER_TIMEOUT' : 'SERVICE_TIMEOUT',
                    $isAi ? 'The AI request took longer than expected. You can try again.' : 'This request is taking longer than expected. Please try again.',
                    true,
                    503,
                    $requestId,
                );
            }

            if (self::containsAny($message, ['certificate', 'ssl', 'tls', 'curl error 60'])) {
                return new self(
                    $isAi ? 'AI_SECURE_CONNECTION_FAILED' : 'SECURE_CONNECTION_FAILED',
                    $isAi ? 'AI is temporarily unavailable because a secure connection could not be established. Please try again shortly.' : 'We could not establish a secure connection to the service. Please try again shortly.',
                    true,
                    503,
                    $requestId,
                );
            }

            return new self(
                $isAi ? 'AI_NETWORK_ERROR' : 'NETWORK_SERVICE_UNAVAILABLE',
                $isAi ? 'AI is temporarily unavailable. Your work has not been lost. Please try again.' : 'We could not connect to the service. Check your connection and try again.',
                true,
                503,
                $requestId,
            );
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() >= 500) {
            $status = $exception->getStatusCode();
            return new self(
                $isAi ? 'AI_SERVICE_UNAVAILABLE' : 'SERVICE_TEMPORARILY_UNAVAILABLE',
                $isAi
                    ? 'AI is temporarily unavailable. Your work has not been lost. Please try again shortly.'
                    : 'We could not complete your request right now. Please try again.',
                true,
                $status,
                $requestId,
            );
        }

        if ($exception instanceof QueryException) {
            return new self(
                'SERVICE_TEMPORARILY_UNAVAILABLE',
                $isLogin ? 'We could not sign you in right now. Please try again.' : 'We could not complete your request right now. Please try again.',
                true,
                503,
                $requestId,
            );
        }

        return new self(
            $isAi ? 'AI_REQUEST_FAILED' : 'REQUEST_FAILED',
            $isLogin
                ? 'We could not sign you in right now. Please try again.'
                : ($isAi
                    ? 'AI is temporarily unavailable. Your work has not been lost. Please try again.'
                    : 'We could not complete your request right now. Please try again.'),
            true,
            500,
            $requestId,
        );
    }

    public static function fromAiCode(?string $code, ?string $fallback = null): array
    {
        $code = strtoupper(trim((string) $code));

        return match ($code) {
            'AI_PROVIDER_TIMEOUT' => ['message' => 'The AI request took longer than expected. You can try again.', 'retryable' => true],
            'AI_PROVIDER_RATE_LIMITED' => ['message' => 'This AI service is temporarily busy. Please try again shortly.', 'retryable' => true],
            'AI_NETWORK_ERROR', 'AI_DNS_ERROR', 'AI_CONNECTION_REFUSED', 'AI_TLS_ERROR' => ['message' => 'AI is temporarily unavailable. Your work has not been lost. Please try again.', 'retryable' => true],
            'AI_PROVIDER_UNAVAILABLE', 'AI_ALL_PROVIDERS_FAILED' => ['message' => 'AI is temporarily unavailable. Your work has not been lost. Please try again shortly.', 'retryable' => true],
            'AI_PROVIDER_AUTH_FAILED', 'AI_MODEL_NOT_FOUND', 'AI_INVALID_CONFIGURATION', 'AI_PROVIDER_INCOMPATIBLE' => ['message' => 'This AI service is currently unavailable.', 'retryable' => false],
            'AI_USAGE_LIMIT_REACHED' => ['message' => 'The configured AI usage limit has been reached.', 'retryable' => false],
            'AI_FEATURE_DISABLED', 'AI_DISABLED' => ['message' => 'AI assistance is currently unavailable.', 'retryable' => false],
            default => ['message' => self::safeMessage((string) $fallback, 'AI is temporarily unavailable. Please try again.'), 'retryable' => true],
        };
    }

    public static function safeMessage(?string $message, string $fallback = 'We could not complete your request right now. Please try again.'): string
    {
        $message = trim((string) $message);
        if ($message === '') {
            return $fallback;
        }

        $lower = strtolower($message);
        $technicalMarkers = [
            'stack trace', 'exception trace', 'gettrace', 'vendor/guzzlehttp', 'guzzlehttp\\', 'curlfactory.php',
            'symfony\\component', 'illuminate\\', 'sqlstate[', 'uncaught exception', 'whoops', 'fatal error',
            ' on line ', '#0 ', '#1 ', 'requestexception', 'connectexception', 'connectionexception',
            '/vendor/', '\\vendor\\', 'storage/framework/views/', 'app/http/controllers/', '.php:',
        ];

        foreach ($technicalMarkers as $marker) {
            if (str_contains($lower, strtolower($marker))) {
                return $fallback;
            }
        }

        // Preserve ordinary validation/business messages while stopping long
        // provider/framework diagnostics from being reflected into the UI.
        if (mb_strlen($message) > 500 || preg_match('/\b(?:curl error|traceback|framework error)\b/i', $message)) {
            return $fallback;
        }

        return $message;
    }

    public static function requestId(Request $request): string
    {
        $existing = trim((string) $request->attributes->get('request_id', ''));
        if ($existing !== '') {
            return $existing;
        }

        $incoming = trim((string) $request->header('X-Request-Id', ''));
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $incoming)) {
            $request->attributes->set('request_id', $incoming);
            return $incoming;
        }

        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        return $id;
    }

    public function toArray(): array
    {
        return [
            'status' => false,
            'success' => false,
            'code' => $this->code,
            'message' => $this->message,
            'retryable' => $this->retryable,
            'request_id' => $this->requestId,
        ];
    }

    private static function isAiRequest(Request $request): bool
    {
        $routeName = strtolower((string) optional($request->route())->getName());
        $path = strtolower($request->path());

        return str_contains($routeName, 'ai')
            || str_contains($routeName, 'assistant')
            || str_contains($path, '/ai')
            || str_contains($path, 'assistant');
    }

    private static function isLoginRequest(Request $request): bool
    {
        return $request->is('login', 'api/v1/auth/login', 'api/login')
            || str_contains(strtolower((string) optional($request->route())->getName()), 'login');
    }

    private static function looksLikeTransportFailure(Throwable $exception): bool
    {
        $class = strtolower($exception::class);

        return str_contains($class, 'guzzlehttp')
            || str_contains($class, 'connectexception')
            || str_contains($class, 'requestexception')
            || str_contains($class, 'curlexception');
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
