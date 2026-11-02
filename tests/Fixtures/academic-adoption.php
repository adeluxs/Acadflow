<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = (string) config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, '/private/tmp/acadsyntra-adoption-')) {
    throw new RuntimeException('Use an isolated /private/tmp/acadsyntra-adoption-*.sqlite fixture database in testing environment.');
}
if (in_array('--reset', $argv, true)) {
    Artisan::call('migrate:fresh', ['--force' => true]);
}

$institution = University::factory()->create(['name' => 'Adoption Fixture University', 'code' => 'ADOPT-FIX', 'adoption_status' => 'active']);
$faculty = Faculty::factory()->create(['university_id' => $institution->id, 'name' => 'School of Science', 'code' => 'ADOPT-SCI']);
$department = Department::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Applied Research Department', 'code' => 'ADOPT-RES', 'adoption_status' => 'unclaimed']);
foreach ([
    ['email' => 'platform@adoption.test', 'role' => 'super_admin', 'account_type' => 'academic_staff', 'first_name' => 'Platform'],
    ['email' => 'representative@adoption.test', 'role' => 'member', 'account_type' => 'department_representative', 'first_name' => 'Department'],
    ['email' => 'institution@adoption.test', 'role' => 'university_admin', 'account_type' => 'university_representative', 'first_name' => 'Institution', 'university_id' => $institution->id],
    ['email' => 'student@adoption.test', 'role' => 'student', 'account_type' => 'student', 'first_name' => 'Student'],
] as $data) {
    $user = User::factory()->create($data + ['last_name' => 'Fixture', 'password' => 'AdoptionExample123!']);
    if ($user->role === 'university_admin') {
        UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $institution->id, 'affiliation_type' => 'university_admin', 'status' => 'verified', 'verified_at' => now(), 'source' => 'browser_fixture']);
    }
}
echo "Isolated academic adoption fixtures ready.\n";
