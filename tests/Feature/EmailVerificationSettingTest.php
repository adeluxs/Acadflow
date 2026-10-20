<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
    }

    public function test_verification_remains_required_by_default_for_web_and_api(): void
    {
        $user = User::factory()->unverified()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->getJson('/api/v1/user')->assertForbidden()->assertJsonPath('next_action', 'verify_email');
        $this->assertFalse((new \App\Policies\GroupPolicy())->create($user));
    }

    public function test_super_admin_can_disable_then_reenable_verification_without_faking_verified_emails(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed_at' => now()]);
        $user = User::factory()->unverified()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($admin)->put(route('admin.settings.update-key', 'email_verification_enabled'), ['value' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse(SettingService::emailVerificationEnabled());
        $this->actingAs($user)->getJson('/api/v1/user')->assertOk();
        $this->get(route('verification.notice'))->assertRedirect(route('onboarding.show'));
        $this->assertTrue((new \App\Policies\GroupPolicy())->create($user));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->actingAs($admin)->put(route('admin.settings.update-key', 'email_verification_enabled'), ['value' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->getJson('/api/v1/user')->assertForbidden();
    }

    public function test_institution_admin_cannot_override_platform_verification(): void
    {
        $admin = User::factory()->create(['role' => 'university_admin', 'onboarding_completed_at' => now()]);
        $this->actingAs($admin)->putJson(route('admin.settings.update-key', 'email_verification_enabled'), ['value' => '0'])->assertForbidden();
        $this->assertTrue(SettingService::emailVerificationEnabled());
    }

    public function test_security_settings_screen_and_bulk_save_expose_switch_only_to_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed_at' => now()]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('Email Verification Enabled');
        $this->post(route('admin.settings.update'), ['settings' => ['email_verification_enabled' => '0']])->assertSessionHasNoErrors();
        $this->assertFalse(SettingService::emailVerificationEnabled());
        $institutionAdmin = User::factory()->create(['role' => 'university_admin', 'onboarding_completed_at' => now()]);
        $this->actingAs($institutionAdmin)->get(route('admin.settings'))->assertOk()->assertDontSee('Email Verification Enabled');
        $this->postJson(route('admin.settings.update'), ['settings' => ['email_verification_enabled' => '1']])->assertForbidden();
    }

    private function registration(): array
    {
        return ['account_type' => 'independent_professional', 'first_name' => 'Ada', 'last_name' => 'Okafor', 'username' => 'verification-test', 'email' => 'ada@example.test', 'password' => 'EnterprisePass123!', 'password_confirmation' => 'EnterprisePass123!', 'terms' => '1'];
    }

    public function test_disabled_registration_skips_verification_mail_and_goes_to_onboarding(): void
    {
        SettingService::set('email_verification_enabled', false, 'boolean');
        Notification::fake();
        $this->post(route('store-register'), $this->registration())->assertRedirect(route('onboarding.show'));
        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertNotSentTo($user, VerifyEmail::class);
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_enabled_registration_sends_a_real_verification_notification(): void
    {
        Notification::fake();
        $this->post(route('store-register'), $this->registration())->assertRedirect(route('verification.notice'));
        Notification::assertSentTo(User::where('email', 'ada@example.test')->firstOrFail(), VerifyEmail::class);
    }

    public function test_optional_api_registration_and_login_issue_correct_next_action_and_access_token(): void
    {
        SettingService::set('email_verification_enabled', false, 'boolean');
        Notification::fake();
        $this->postJson('/api/v1/auth/register', $this->registration())->assertCreated()->assertJsonPath('next_action', 'complete_onboarding');
        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $user->update(['onboarding_completed_at' => now()]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'EnterprisePass123!'])->assertOk()->assertJsonPath('next_action', null);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'abilities' => '["platform:access"]']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_signed_verification_still_marks_actual_verification_and_rejects_invalid_hash(): void
    {
        SettingService::set('email_verification_enabled', false, 'boolean');
        $user = User::factory()->unverified()->create();
        $invalid = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1('other@example.test')]);
        $this->actingAs($user)->get($invalid)->assertForbidden();
        $valid = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($valid)->assertRedirect(route('onboarding.show'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
