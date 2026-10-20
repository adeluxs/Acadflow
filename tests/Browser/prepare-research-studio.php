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
echo "Isolated studio fixtures ready.\n";
