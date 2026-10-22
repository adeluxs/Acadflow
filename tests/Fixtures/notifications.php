<?php

// This fixture never targets an application database or a real SMTP/push service.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = (string) config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, '/private/tmp/acadsyntra-notifications-')) throw new RuntimeException('Use an isolated /private/tmp/acadsyntra-notifications-*.sqlite testing database.');
if (in_array('--reset', $argv, true)) Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);

if (in_array('--deliver', $argv, true)) {
    $auth = app(App\Services\WebPushConfiguration::class)->auth();
    $handler = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler(array_fill(0, 100, new GuzzleHttp\Psr7\Response(201))));
    $app->instance(Minishlink\WebPush\WebPush::class, new Minishlink\WebPush\WebPush(['VAPID' => $auth], ['TTL' => 60], 5, ['handler' => $handler, 'allow_redirects' => false]));
    config(['mail.default' => 'array']);
    foreach (App\Models\NotificationLog::where('status', 'queued')->whereIn('channel', ['email', 'push'])->pluck('id') as $id) app(App\Services\NotificationDeliveryService::class)->deliver($id);
    echo "Fixture deliveries processed through isolated transports.\n";
    exit;
}

foreach ([['fresh@notifications.test', 'Fresh', 'student', null], ['student@notifications.test', 'Student', 'student', now()], ['platform@notifications.test', 'Platform', 'super_admin', now()]] as [$email, $name, $role, $completed]) {
    App\Models\User::factory()->create(['email' => $email, 'first_name' => $name, 'last_name' => 'Fixture', 'role' => $role, 'account_type' => $role === 'super_admin' ? 'academic_staff' : 'student', 'password' => 'NotificationExample123!', 'onboarding_completed_at' => $completed]);
}
App\Services\FeatureAccessService::setStatus('notifications', 'enabled');
App\Services\FeatureAccessService::setStatus('push_notifications', 'enabled');
App\Services\SettingService::set('push_notifications_enabled', true, 'boolean');
App\Services\SettingService::set('email_notifications_enabled', true, 'boolean');
app(App\Services\WebPushConfiguration::class)->generate('mailto:notifications@example.test', App\Models\User::where('email', 'platform@notifications.test')->value('id'));
$student = App\Models\User::where('email', 'student@notifications.test')->firstOrFail();
app(App\Services\NotificationService::class)->publish($student, 'assignment_created', 'An academic update is ready', 'Review your academic workspace.', ['url' => route('notifications.settings'), 'event_key' => 'browser-fixture']);
$keys = Minishlink\WebPush\VAPID::createVapidKeys();
$subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/isolated-browser-notifications', 'keys' => ['p256dh' => $keys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')], 'expirationTime' => null];
file_put_contents('/private/tmp/acadsyntra-notifications-browser-subscription.json', json_encode($subscription, JSON_THROW_ON_ERROR));
chmod('/private/tmp/acadsyntra-notifications-browser-subscription.json', 0600);
echo "Isolated notification browser fixtures ready.\n";
