<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationSetting;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\PushNotificationService;
use App\Services\SettingService;
use App\Services\WebPushConfiguration;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrowserPushDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private array $auth;
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
        $this->auth = ['subject' => 'mailto:push@example.test'] + VAPID::createVapidKeys();
        config(['services.webpush.vapid_public_key' => $this->auth['publicKey'], 'services.webpush.vapid_private_key' => $this->auth['privateKey'], 'services.webpush.vapid_subject' => $this->auth['subject']]);
        FeatureAccessService::setStatus('push_notifications', 'enabled');
        SettingService::set('push_notifications_enabled', true, 'boolean');
    }

    private function data(string $endpoint = 'https://fcm.googleapis.com/fcm/send/browser-test'): array
    {
        $keys = VAPID::createVapidKeys();
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => $keys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')], 'expirationTime' => null];
    }

    private function user(): User { return User::factory()->create(['onboarding_completed_at' => now()]); }

    private function transport(int $status): void
    {
        $handler = HandlerStack::create(new MockHandler([new Response($status)]));
        $handler->push(Middleware::history($this->history));
        $this->app->instance(WebPush::class, new WebPush(['VAPID' => $this->auth], ['TTL' => 60], 5, ['handler' => $handler, 'allow_redirects' => false]));
    }

    public function test_browser_enrollment_is_idempotent_supports_long_endpoints_and_hides_keys(): void
    {
        $user = $this->user();
        $data = $this->data('https://fcm.googleapis.com/fcm/send/'.str_repeat('x', 600));
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->assertArrayNotHasKey('keys_auth', PushSubscription::first()->toArray());
        $this->get(route('notifications.settings'))->assertOk()->assertSee('Enable this browser');
    }

    public function test_logout_removes_only_this_browsers_subscription_without_a_browser_cleanup_request(): void
    {
        $user = $this->user();
        $current = $this->data();
        $otherDevice = $this->data('https://fcm.googleapis.com/fcm/send/other-device');
        app(PushNotificationService::class)->subscribe($user, $otherDevice['endpoint'], $otherDevice['keys']);
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), $current)
            ->assertOk()->assertSessionHas('browser_push.endpoint_hash', hash('sha256', $current['endpoint']));

        // Cleanup still works if notifications have since been disabled.
        SettingService::set('push_notifications_enabled', false, 'boolean');
        $this->withSession(['private_marker' => 'must-be-cleared'])->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('browser_push.endpoint_hash')
            ->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $current['endpoint']]);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => $otherDevice['endpoint']]);
    }

    public function test_logout_accepts_a_known_endpoint_from_an_older_session(): void
    {
        $user = $this->user();
        $data = $this->data();
        app(PushNotificationService::class)->subscribe($user, $data['endpoint'], $data['keys']);
        $this->actingAs($user)->post(route('logout'), ['push_endpoint' => $data['endpoint']])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $data['endpoint']]);
    }

    public function test_logout_cannot_remove_another_accounts_subscription(): void
    {
        $owner = $this->user();
        $data = $this->data();
        app(PushNotificationService::class)->subscribe($owner, $data['endpoint'], $data['keys']);
        $this->actingAs($this->user())->withSession(['browser_push.endpoint_hash' => hash('sha256', $data['endpoint'])])
            ->post(route('logout'), ['push_endpoint' => $data['endpoint']])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $owner->id, 'endpoint' => $data['endpoint']]);
    }

    #[DataProvider('invalidLogoutEndpoints')]
    public function test_optional_push_metadata_cannot_prevent_logout(mixed $endpoint): void
    {
        $this->actingAs($this->user())->post(route('logout'), ['push_endpoint' => $endpoint])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public static function invalidLogoutEndpoints(): array
    {
        return [[null], [['unexpected' => 'array']], [str_repeat('x', 4097)]];
    }

    public function test_unsubscribing_clears_current_device_session_tracking(): void
    {
        $data = $this->data();
        $this->actingAs($this->user())->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->postJson(route('browser-push.unsubscribe'), ['endpoint' => $data['endpoint']])
            ->assertOk()->assertSessionMissing('browser_push.endpoint_hash');
    }

    public function test_delivery_encrypts_payload_and_signs_vapid_using_actual_web_push_library(): void
    {
        $user = $this->user(); $data = $this->data();
        app(PushNotificationService::class)->subscribe($user, $data['endpoint'], $data['keys']);
        $this->transport(201);
        $this->assertSame(1, app(PushNotificationService::class)->send($user, 'Private title', 'Private body', ['url' => '/notifications']));
        $request = $this->history[0]['request'];
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('k='.$this->auth['publicKey'], $request->getHeaderLine('Authorization'));
        $this->assertStringNotContainsString('Private body', (string) $request->getBody());
        $this->assertGreaterThan(100, strlen((string) $request->getBody()));
    }

    #[DataProvider('failures')]
    public function test_expired_endpoints_are_removed_but_temporary_failures_are_retained(int $status, int $remaining): void
    {
        $user = $this->user(); $data = $this->data();
        app(PushNotificationService::class)->subscribe($user, $data['endpoint'], $data['keys']);
        $this->transport($status);
        $this->assertSame(0, app(PushNotificationService::class)->send($user, 'Title', 'Body'));
        $this->assertSame($remaining, PushSubscription::count());
    }
    public static function failures(): array { return [[410, 0], [404, 0], [429, 1], [503, 1]]; }

    public function test_disabled_channel_module_and_user_preference_prevent_delivery_and_cleanup_still_works(): void
    {
        $user = $this->user(); $data = $this->data();
        app(PushNotificationService::class)->subscribe($user, $data['endpoint'], $data['keys']);
        NotificationSetting::create(['user_id' => $user->id, 'push_enabled' => false]);
        $this->assertSame(0, app(PushNotificationService::class)->send($user, 'Title', 'Body'));
        NotificationSetting::where('user_id', $user->id)->update(['push_enabled' => true]);
        SettingService::set('push_notifications_enabled', false, 'boolean');
        $this->assertSame(0, app(PushNotificationService::class)->send($user, 'Title', 'Body'));
        FeatureAccessService::setStatus('push_notifications', 'disabled');
        $this->actingAs($user)->postJson(route('browser-push.unsubscribe'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_unsubscribe_cannot_remove_another_users_subscription(): void
    {
        $owner = $this->user(); $other = $this->user(); $data = $this->data();
        app(PushNotificationService::class)->subscribe($owner, $data['endpoint'], $data['keys']);
        $this->actingAs($other)->postJson(route('browser-push.unsubscribe'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_browser_account_switch_requires_possession_of_existing_encryption_keys(): void
    {
        $owner = $this->user(); $other = $this->user(); $data = $this->data();
        app(PushNotificationService::class)->subscribe($owner, $data['endpoint'], $data['keys']);
        $this->actingAs($other)->postJson(route('browser-push.subscribe'), $this->data($data['endpoint']))->assertUnprocessable();
        $this->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->assertSame($other->id, PushSubscription::first()->user_id);
    }

    #[DataProvider('unsafeEndpoints')]
    public function test_subscriptions_reject_internal_or_untrusted_push_endpoints(string $endpoint): void
    {
        $this->actingAs($this->user())->postJson(route('browser-push.subscribe'), $this->data($endpoint))->assertUnprocessable();
        $this->assertSame(0, PushSubscription::count());
    }
    public static function unsafeEndpoints(): array { return [['http://fcm.googleapis.com/push'], ['https://127.0.0.1/private'], ['https://metadata.google.internal/'], ['https://fcm.googleapis.com.attacker.test/'], ['https://fcm.googleapis.com:8443/push']]; }

    public function test_admin_setup_stores_encrypted_keys_and_never_replaces_existing_key_pair(): void
    {
        config(['services.webpush.vapid_public_key' => '', 'services.webpush.vapid_private_key' => '', 'services.webpush.vapid_subject' => '']);
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed_at' => now()]);
        $this->actingAs($admin)->post(route('admin.notifications.push-keys'), ['subject' => 'mailto:admin@example.test'])->assertSessionHasNoErrors();
        $configuration = app(WebPushConfiguration::class);
        $this->assertTrue($configuration->isConfigured());
        $keys = $configuration->auth();
        $this->assertNotSame($keys['privateKey'], \App\Models\Setting::where('key', 'webpush_vapid_private_key')->value('value'));
        $this->post(route('admin.notifications.push-keys'), ['subject' => 'mailto:admin@example.test'])->assertSessionHasErrors('push');
        $this->assertSame($keys['publicKey'], $configuration->auth()['publicKey']);
        $this->getJson(route('browser-push.configuration'))->assertOk()->assertDontSee($keys['privateKey']);
    }

    public function test_explicit_enrollment_enables_preference_but_background_reconciliation_does_not(): void
    {
        $user = $this->user();
        NotificationSetting::create(['user_id' => $user->id, 'push_enabled' => false]);
        $data = $this->data();
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->assertFalse($user->notificationSetting()->first()->push_enabled);
        $this->postJson(route('browser-push.subscribe'), $data + ['enablePreference' => true])->assertOk();
        $this->assertTrue($user->notificationSetting()->first()->push_enabled);
    }

    public function test_saved_expiry_is_respected_and_expired_subscriptions_can_be_cleaned(): void
    {
        $user = $this->user(); $data = $this->data();
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), array_merge($data, ['expirationTime' => now()->subMinute()->getTimestampMs()]))->assertOk();
        $this->assertSame(0, app(PushNotificationService::class)->send($user, 'Title', 'Body'));
        $this->assertSame(1, app(PushNotificationService::class)->cleanup());
    }

    public function test_invalid_curve_points_and_short_auth_keys_are_rejected(): void
    {
        $user = $this->user(); $data = $this->data();
        $data['keys']['p256dh'] = rtrim(strtr(base64_encode("\x04".str_repeat("\x00", 64)), '+/', '-_'), '=');
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), $data)->assertUnprocessable();
        $data = $this->data(); $data['keys']['auth'] = 'short';
        $this->postJson(route('browser-push.subscribe'), $data)->assertUnprocessable();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_unconfigured_delivery_returns_actionable_state_and_test_does_not_claim_success(): void
    {
        config(['services.webpush.vapid_public_key' => '', 'services.webpush.vapid_private_key' => '']);
        $this->actingAs($this->user())->getJson(route('browser-push.configuration'))->assertOk()->assertJsonPath('available', false)->assertJsonPath('publicKey', null);
        $this->postJson(route('browser-push.test'))->assertStatus(503);
    }
}
