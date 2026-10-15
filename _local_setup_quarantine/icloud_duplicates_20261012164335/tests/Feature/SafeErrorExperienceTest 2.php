<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SafeErrorExperienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/__safe-error-test/runtime', function () {
            throw new RuntimeException('GuzzleHttp\\Exception\\ConnectException vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php Stack trace: #0 secret-path');
        });

        foreach ([400, 401, 403, 404, 408, 409, 429, 500, 502, 503] as $status) {
            Route::middleware('api')->get('/api/__safe-error-test/http-'.$status, function () use ($status) {
                abort($status, 'vendor/guzzlehttp internal diagnostic that must not be reflected');
            });
        }

        Route::middleware('api')->get('/api/__safe-error-test/validation', function () {
            throw ValidationException::withMessages([
                'question' => ['GuzzleHttp\\Exception\\RequestException vendor/guzzlehttp Stack trace: #0 secret'],
            ]);
        });

        Route::middleware('web')->post('/__safe-error-test/form', function () {
            throw new RuntimeException('SQLSTATE[HY000] vendor/framework internal failure');
        });
    }

    public function test_unhandled_api_exception_is_normalized_without_trace_or_file_paths(): void
    {
        $response = $this->getJson('/api/__safe-error-test/runtime');

        $response->assertStatus(500)
            ->assertJsonPath('status', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'REQUEST_FAILED')
            ->assertJsonPath('retryable', true)
            ->assertJsonStructure(['message', 'code', 'retryable', 'request_id']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('GuzzleHttp', $body);
        $this->assertStringNotContainsString('CurlFactory', $body);
        $this->assertStringNotContainsString('stack trace', strtolower($body));
    }

    #[DataProvider('structuredHttpStatusProvider')]
    public function test_common_http_api_failures_are_structured_and_do_not_reflect_internal_diagnostics(
        int $status,
        string $code,
        bool $retryable,
    ): void {
        $response = $this->getJson('/api/__safe-error-test/http-'.$status);

        $response->assertStatus($status)
            ->assertJsonPath('status', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', $code)
            ->assertJsonPath('retryable', $retryable)
            ->assertJsonStructure(['message', 'request_id']);

        $body = strtolower($response->getContent());
        $this->assertStringNotContainsString('vendor/guzzlehttp', $body);
        $this->assertStringNotContainsString('stack trace', $body);
    }

    public static function structuredHttpStatusProvider(): array
    {
        return [
            '400' => [400, 'BAD_REQUEST', false],
            '401' => [401, 'AUTHENTICATION_REQUIRED', false],
            '403' => [403, 'ACCESS_DENIED', false],
            '404' => [404, 'NOT_FOUND', false],
            '408' => [408, 'REQUEST_TIMEOUT', true],
            '409' => [409, 'REQUEST_CONFLICT', false],
            '429' => [429, 'TOO_MANY_REQUESTS', true],
            '500' => [500, 'SERVICE_TEMPORARILY_UNAVAILABLE', true],
            '502' => [502, 'SERVICE_TEMPORARILY_UNAVAILABLE', true],
            '503' => [503, 'SERVICE_TEMPORARILY_UNAVAILABLE', true],
        ];
    }

    public function test_validation_error_details_are_sanitized_before_json_output(): void
    {
        $response = $this->getJson('/api/__safe-error-test/validation');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('retryable', false)
            ->assertJsonPath('errors.question.0', 'The value provided is invalid.');

        $this->assertStringNotContainsString('GuzzleHttp', $response->getContent());
    }

    public function test_failed_web_form_preserves_non_sensitive_input_but_not_passwords(): void
    {
        config(['app.debug' => false]);

        $response = $this->from('/dashboard')->post('/__safe-error-test/form', [
            'title' => 'My preserved work',
            'password' => 'MustNotBeFlashed',
        ]);

        $response->assertRedirect('/dashboard')
            ->assertSessionHasInput('title', 'My preserved work')
            ->assertSessionHas('error')
            ->assertSessionHas('request_id');

        $this->assertArrayNotHasKey('password', (array) session('_old_input', []));
    }
}
