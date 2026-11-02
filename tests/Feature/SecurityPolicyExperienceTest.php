<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityPolicyExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:test@example.test|127.0.0.1');
    }

    public function test_public_settings_expose_the_same_password_policy_used_by_registration(): void
    {
        $this->securitySetting('password_min_length', 12);
        $this->securitySetting('password_require_uppercase', true, 'boolean');
        $this->securitySetting('password_require_numbers', true, 'boolean');
        $this->securitySetting('password_require_special', true, 'boolean');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('password_policy.min_length', 12)
            ->assertJsonPath('password_policy.require_uppercase', true)
            ->assertJsonPath('password_policy.require_numbers', true)
            ->assertJsonPath('password_policy.require_special', true);

        $this->post('/register', $this->registrationPayload('Password1234'))
            ->assertSessionHasErrors('password');
    }

    public function test_login_feedback_reports_remaining_attempts_and_real_lockout_wait(): void
    {
        $this->securitySetting('max_login_attempts', 2);
        $this->securitySetting('lockout_duration_minutes', 3);
        $this->securitySetting('login_requests_per_minute', 20);

        User::factory()->create([
            'email' => 'test@example.test',
            'password' => 'CorrectPassword1',
        ]);

        $first = $this->post('/login', [
            'email' => 'test@example.test',
            'password' => 'wrong',
        ]);
        $first->assertSessionHasErrors('email');
        $this->assertStringContainsString('1 attempt remaining', (string) session('errors')->first('email'));

        $second = $this->post('/login', [
            'email' => 'test@example.test',
            'password' => 'wrong',
        ]);
        $second->assertSessionHasErrors('email');
        $second->assertSessionHas('retry_after');
        $this->assertGreaterThan(0, (int) session('retry_after'));
    }

    public function test_api_throttle_returns_structured_retry_after_response(): void
    {
        $this->securitySetting('registration_requests_per_hour', 1);

        $this->postJson('/api/v1/auth/register', []);
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertJsonStructure(['message', 'code', 'retry_after']);

        $this->assertGreaterThan(0, (int) $response->json('retry_after'));
        $this->assertStringContainsString('try again in', strtolower((string) $response->json('message')));
    }

    public function test_password_rules_are_built_from_the_central_policy(): void
    {
        $this->securitySetting('password_min_length', 10);
        $this->securitySetting('password_require_uppercase', false, 'boolean');
        $this->securitySetting('password_require_numbers', true, 'boolean');
        $this->securitySetting('password_require_special', false, 'boolean');

        $rules = SettingService::getPasswordRules();

        $this->assertContains('min:10', $rules);
        $this->assertContains('regex:/[0-9]/', $rules);
        $this->assertNotContains('regex:/[A-Z]/', $rules);
        $this->assertNotContains('regex:/[@$!%*#?&]/', $rules);
    }

    private function securitySetting(string $key, mixed $value, string $type = 'integer'): void
    {
        Setting::query()->firstOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'group' => 'security',
                'description' => $key,
            ]
        );

        SettingService::set($key, $value, $type);
    }

    private function registrationPayload(string $password): array
    {
        return [
            'account_type' => 'student',
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'username' => 'ada.okafor',
            'email' => 'ada@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => '1',
        ];
    }
}
