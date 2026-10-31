<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_starts_with((string) $database, '/private/tmp/acadsyntra-ambassadors-')) {
    throw new RuntimeException('Use a separate /private/tmp/acadsyntra-ambassadors-* SQLite testing database.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Services\SettingService::set('email_verification_enabled', false, 'boolean');
$fixture = [];
foreach (['admin', 'ambassador', 'applicant', 'referred'] as $role) {
    $user = App\Models\User::factory()->create([
        'first_name' => ucfirst($role), 'last_name' => 'Preview', 'email' => $role.'.'.Illuminate\Support\Str::random(6).'@example.test',
        'role' => $role === 'admin' ? 'super_admin' : 'student',
    ]);
    if ($role === 'referred') $referredUser = $user;
    $sessionId = Illuminate\Support\Str::random(40);
    Illuminate\Support\Facades\DB::table('sessions')->insert([
        'id' => $sessionId, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Ambassador browser fixture',
        'payload' => base64_encode(serialize(['_token' => Illuminate\Support\Str::random(40), auth()->guard('web')->getName() => $user->id])), 'last_activity' => time(),
    ]);
    $cookieName = config('session.cookie');
    $encrypter = app('encrypter');
    $fixture[$role] = ['name' => $cookieName, 'value' => $encrypter->encrypt(Illuminate\Cookie\CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId, false),
        'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax'];
    if ($role === 'ambassador') {
        $application = App\Models\AmbassadorApplication::create([
            'user_id' => $user->id, 'institution' => 'Campus Preview University', 'department' => 'Computer Science', 'study_level' => '200 level',
            'leadership_role' => 'course_rep', 'phone' => '+2348000000000', 'community_size' => 50,
            'statement' => 'I will help classmates organise a useful study demo and collect their feedback.', 'status' => 'approved', 'terms_accepted_at' => now(),
        ]);
        $fixture['application'] = $application->uuid;
        $fixture['referralCode'] = $application->referral_code;
    }
}
$referredUser->forceFill(['has_ambassador_referral' => true])->save();
$referral = App\Models\AmbassadorReferral::create([
    'ambassador_application_id' => $application->id, 'user_id' => $referredUser->id,
    'registered_at' => now()->subDays(8), 'retention_starts_at' => now()->subDay(), 'retention_ends_at' => now()->addDays(22),
]);
foreach ([8, 7, 0] as $day) {
    Illuminate\Support\Facades\DB::table('ambassador_referral_days')->insert([
        'ambassador_referral_id' => $referral->id, 'activity_date' => now()->subDays($day)->toDateString(),
        'occurred_at' => now()->subDays($day), 'kind' => 'study_note', 'content_hash' => hash('sha256', 'fixture-'.$day),
    ]);
}
$fixture['referralId'] = $referral->id;
$path = '/private/tmp/acadsyntra-ambassadors-fixture.json';
file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR));
chmod($path, 0600);
echo "Isolated ambassador browser fixtures ready.\n";
