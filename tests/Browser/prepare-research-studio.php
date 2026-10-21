<?php

// Isolated browser fixtures only; never seed a developer or production database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, '/private/tmp/acadsyntra-studio-') || config('database.default') !== 'sqlite') throw new RuntimeException('Use the isolated /private/tmp/acadsyntra-studio-* SQLite database.');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Services\SettingService::set('allowed_file_extensions', 'pdf,docx,txt,csv,xlsx,png,jpg,jpeg');
App\Services\SettingService::set('email_verification_enabled', false, 'boolean');
App\Services\SettingService::set('ai_editor_suggestions_enabled', false, 'boolean');
$owner = App\Models\User::query()->firstOrCreate(['email' => 'studio.owner@acadsyntra.test'], ['first_name' => 'Ada', 'last_name' => 'Researcher', 'username' => 'studio-owner', 'role' => 'student', 'account_type' => 'student', 'password' => 'StudioExample123!', 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now(), 'programme' => 'Computer science', 'academic_level' => 'undergraduate']);
App\Models\User::query()->firstOrCreate(['email' => 'studio.new@acadsyntra.test'], ['first_name' => 'New', 'last_name' => 'Researcher', 'username' => 'studio-new', 'role' => 'student', 'account_type' => 'student', 'password' => 'StudioExample123!', 'is_active' => true, 'email_verified_at' => now()]);
App\Services\FeatureAccessService::setStatus('research_datasets', App\Services\FeatureAccessService::STATUS_ENABLED, $owner->id);
app(App\Services\IndependentAcademicContextService::class)->ensureResearchType();
$university = App\Models\University::firstOrCreate(['code' => 'STUDIO-TEST'], ['name' => 'Studio Test Institution', 'short_name' => 'Studio Test', 'is_active' => true]);
$faculty = App\Models\Faculty::firstOrCreate(['code' => 'STUDIO-TEST-FAC'], ['university_id' => $university->id, 'name' => 'Test Faculty', 'short_name' => 'Test Faculty', 'is_active' => true]);
$department = App\Models\Department::firstOrCreate(['code' => 'STUDIO-TEST-DEP'], ['faculty_id' => $faculty->id, 'name' => 'Test Research Department', 'short_name' => 'Test Research', 'is_active' => true]);
App\Models\User::firstOrCreate(['email' => 'studio.institution@acadsyntra.test'], ['first_name' => 'Institution', 'last_name' => 'Researcher', 'username' => 'studio-institution', 'role' => 'university_admin', 'account_type' => 'lecturer', 'university_id' => $university->id, 'department_id' => $department->id, 'password' => 'StudioExample123!', 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now()]);
App\Models\User::firstOrCreate(['email' => 'studio.supervisor@acadsyntra.test'], ['first_name' => 'Test', 'last_name' => 'Supervisor', 'username' => 'studio-supervisor', 'role' => 'lecturer', 'account_type' => 'lecturer', 'university_id' => $university->id, 'department_id' => $department->id, 'password' => 'StudioExample123!', 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now()]);
App\Services\FeatureAccessService::setStatus('research_institutional_expansion', App\Services\FeatureAccessService::STATUS_ENABLED, $owner->id);
echo "Isolated studio fixtures ready.\n";
