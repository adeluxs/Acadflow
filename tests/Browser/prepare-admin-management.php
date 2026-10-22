<?php

// Refuse to seed any database containing real application data.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with((string) $database, '/private/tmp/acadsyntra-studio-')) {
    throw new RuntimeException('Use an isolated /private/tmp/acadsyntra-studio-* testing database.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Services\SettingService::set('email_verification_enabled', false, 'boolean');
$password = Illuminate\Support\Facades\Hash::make('SpeedExample123!');
App\Models\User::firstOrCreate(['email' => 'speed.admin@acadsyntra.test'], [
    'first_name' => 'Speed', 'last_name' => 'Admin', 'role' => 'super_admin', 'account_type' => 'super_admin',
    'password' => $password, 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now(),
]);
foreach (['Alpha', 'Beta'] as $campus) {
    $university = App\Models\University::firstOrCreate(['code' => 'SPEED-'.$campus], ['name' => 'Speed '.$campus, 'short_name' => $campus, 'is_active' => true, 'adoption_status' => 'active']);
    for ($i = 0; $i < 30; $i++) {
        $faculty = App\Models\Faculty::firstOrCreate(['university_id' => $university->id, 'code' => 'F'.$i], ['name' => sprintf('Faculty %s %02d', $campus, $i), 'short_name' => 'F'.$i, 'is_active' => true]);
        App\Models\Department::firstOrCreate(['faculty_id' => $faculty->id, 'code' => 'D'.$i], ['name' => sprintf('Department %s %02d', $campus, $i), 'short_name' => 'D'.$i, 'is_active' => true, 'adoption_status' => 'institution_managed']);
        App\Models\User::firstOrCreate(['email' => strtolower($campus).'.dean'.$i.'@acadsyntra.test'], ['first_name' => $campus, 'last_name' => 'Dean'.$i, 'university_id' => $university->id,
            'role' => 'lecturer', 'account_type' => 'lecturer', 'password' => $password, 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now()]);
    }
    $session = App\Models\AcademicSession::firstOrCreate(['university_id' => $university->id, 'name' => 'Speed 2026/2027'], ['start_date' => '2026-09-01', 'end_date' => '2027-08-31', 'is_active' => true, 'is_current' => true]);
    App\Models\Semester::firstOrCreate(['academic_session_id' => $session->id, 'name' => 'Speed first semester'], ['number' => 1, 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'is_active' => true]);
}
$admin = App\Models\User::where('email', 'speed.admin@acadsyntra.test')->firstOrFail();
$sessionId = Illuminate\Support\Str::random(40);
Illuminate\Support\Facades\DB::table('sessions')->insert([
    'id' => $sessionId, 'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Admin browser fixture',
    'payload' => base64_encode(serialize(['_token' => Illuminate\Support\Str::random(40), auth()->guard('web')->getName() => $admin->id])), 'last_activity' => time(),
]);
$cookieName = config('session.cookie');
$encrypter = app('encrypter');
$cookie = ['name' => $cookieName, 'value' => $encrypter->encrypt(Illuminate\Cookie\CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId, false), 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax'];
$cookiePath = '/private/tmp/acadsyntra-studio-admin-cookie.json';
file_put_contents($cookiePath, json_encode($cookie, JSON_THROW_ON_ERROR));
chmod($cookiePath, 0600);
echo "Isolated admin management fixtures and test session ready.\n";
