<?php

// The fixture script rejects every database except the isolated test database.
require __DIR__.'/../../tests/Browser/prepare-research-studio.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Cookie\CookieValuePrefix;

$password = $owner->getRawOriginal('password');
$guardKey = auth()->guard('web')->getName();
$cookieName = config('session.cookie');
$encrypter = app('encrypter');
$cookies = [];
for ($i = 0; $i < 1000; $i++) {
    $email = 'load-'.$i.'@acadsyntra.test';
    $id = DB::table('users')->where('email', $email)->value('id');
    if (! $id) {
        $id = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'first_name' => 'Load', 'last_name' => 'Researcher',
            'email' => $email, 'username' => 'load-'.$i, 'password' => $password,
            'role' => 'student', 'account_type' => 'student', 'is_active' => true,
            'onboarding_completed_at' => now(), 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    // A fixture rerun replaces sessions instead of tripping the real
    // concurrent-session limit with abandoned sessions from previous runs.
    DB::table('sessions')->where('user_id', $id)->delete();
    $session = Str::random(40);
    DB::table('sessions')->updateOrInsert(['id' => $session], [
        'user_id' => $id, 'ip_address' => '127.0.0.1', 'user_agent' => 'AcadSyntra load fixture',
        'payload' => base64_encode(serialize(['_token' => Str::random(40), $guardKey => $id])),
        'last_activity' => time(),
    ]);
    $cookies[] = $cookieName.'='.rawurlencode($encrypter->encrypt(CookieValuePrefix::create($cookieName, $encrypter->getKey()).$session, false));
}
$output = '/private/tmp/acadsyntra-studio-load-cookies.json';
file_put_contents($output, json_encode($cookies, JSON_THROW_ON_ERROR));
chmod($output, 0600);
echo 'Prepared 1000 distinct authenticated test users. Credentials saved only to '.$output."\n";
