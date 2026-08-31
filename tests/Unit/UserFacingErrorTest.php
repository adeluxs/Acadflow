<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Errors\UserFacingError;
use PHPUnit\Framework\TestCase;

class UserFacingErrorTest extends TestCase
{
    public function test_raw_framework_and_guzzle_details_are_never_reused_as_user_messages(): void
    {
        $raw = 'GuzzleHttp\\Exception\\ConnectException vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php on line 252 Stack trace: #0 /home/app/vendor/...';

        $safe = UserFacingError::safeMessage($raw, 'Safe fallback.');

        $this->assertSame('Safe fallback.', $safe);
        $this->assertStringNotContainsString('Guzzle', $safe);
        $this->assertStringNotContainsString('Stack trace', $safe);
    }

    public function test_normal_business_validation_message_is_preserved(): void
    {
        $this->assertSame(
            'The selected attendance session is already closed.',
            UserFacingError::safeMessage('The selected attendance session is already closed.')
        );
    }

    public function test_ai_timeout_and_configuration_failures_have_contextual_retry_policy(): void
    {
        $timeout = UserFacingError::fromAiCode('AI_PROVIDER_TIMEOUT');
        $configuration = UserFacingError::fromAiCode('AI_INVALID_CONFIGURATION');

        $this->assertTrue($timeout['retryable']);
        $this->assertStringContainsString('longer than expected', $timeout['message']);
        $this->assertFalse($configuration['retryable']);
        $this->assertSame('This AI service is currently unavailable.', $configuration['message']);
    }
}
