<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicAdoptionUpgradeTest extends TestCase
{
    public function test_upgrade_preserves_legacy_identity_finances_and_records_without_verifying_self_declarations(): void
    {
        $previousConnection = DB::getDefaultConnection();
        $previousSchema = Schema::getFacadeRoot();
        config(['database.connections.adoption_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('adoption_upgrade');
        Schema::swap(DB::connection()->getSchemaBuilder());
        try {
            Schema::create('universities', function (Blueprint $t) {
                $t->id();
                $t->string('name');
            });
            Schema::create('faculties', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('university_id');
            });
            Schema::create('departments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('faculty_id');
                $t->string('name');
                $t->string('adoption_status');
            });
            Schema::create('users', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('university_id')->nullable();
                $t->unsignedBigInteger('faculty_id')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->string('role');
            });
            Schema::create('user_institution_affiliations', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid');
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('university_id');
                $t->unsignedBigInteger('faculty_id')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->string('affiliation_type');
                $t->string('status');
                $t->string('verification_method')->nullable();
                $t->string('source');
                $t->timestamp('verified_at')->nullable();
                $t->timestamps();
                $t->unique(['user_id', 'university_id', 'affiliation_type'], 'user_institution_affiliation_unique');
            });
            Schema::create('department_adoption_requests', fn (Blueprint $t) => $t->id());
            Schema::create('commercial_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('university_id')->nullable()->unique();
                $t->unsignedBigInteger('prepaid_balance_minor');
            });
            Schema::create('commercial_account_requests', fn (Blueprint $t) => $t->id());
            Schema::create('academic_sessions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('university_id');
                $t->string('name', 20);
                $t->unique(['university_id', 'name']);
            });
            Schema::create('semesters', function (Blueprint $t) {
                $t->id();
                $t->string('name', 20);
            });
            Schema::create('invoices', fn (Blueprint $t) => $t->id());
            Schema::create('legacy_research_records', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('department_id');
                $t->text('body');
            });
            DB::table('universities')->insert(['id' => 1, 'name' => 'Legacy University']);
            DB::table('faculties')->insert(['id' => 1, 'university_id' => 1]);
            DB::table('departments')->insert(['id' => 1, 'faculty_id' => 1, 'name' => 'Existing department', 'adoption_status' => 'independent_active']);
            DB::table('users')->insert([
                ['id' => 1, 'university_id' => 1, 'faculty_id' => 1, 'department_id' => 1, 'role' => 'student'],
                ['id' => 2, 'university_id' => 1, 'faculty_id' => 1, 'department_id' => 1, 'role' => 'department_admin'],
                ['id' => 3, 'university_id' => 1, 'faculty_id' => 1, 'department_id' => 1, 'role' => 'lecturer'],
            ]);
            DB::table('user_institution_affiliations')->insert(['id' => 10, 'uuid' => 'b25d2d96-9847-454e-a17a-89f52f3d983e', 'user_id' => 1, 'university_id' => 1, 'faculty_id' => 1, 'department_id' => 1, 'affiliation_type' => 'student', 'status' => 'self_declared', 'source' => 'onboarding']);
            DB::table('commercial_accounts')->insert(['id' => 7, 'university_id' => 1, 'prepaid_balance_minor' => 123456]);
            DB::table('academic_sessions')->insert(['id' => 3, 'university_id' => 1, 'name' => '2026/2027']);
            DB::table('legacy_research_records')->insert(['id' => 99, 'user_id' => 1, 'department_id' => 1, 'body' => 'Unmodified personal academic work']);
            $before = (array) DB::table('legacy_research_records')->first();
            $migration = require database_path('migrations/2026_10_21_140000_extend_academic_adoption_ecosystem.php');
            $migration->up();
            $this->assertSame($before, (array) DB::table('legacy_research_records')->first());
            $this->assertSame(123456, DB::table('commercial_accounts')->where('id', 7)->value('prepaid_balance_minor'));
            $this->assertSame('self_declared', DB::table('user_institution_affiliations')->where('id', 10)->value('status'));
            $this->assertSame('independent_active', DB::table('departments')->where('id', 1)->value('adoption_status'));
            $this->assertSame(1, DB::table('user_institution_affiliations')->where('user_id', 2)->where('status', 'verified')->count());
            $this->assertSame(0, DB::table('user_institution_affiliations')->where('user_id', 3)->where('status', 'verified')->count());
            $this->assertSame('1:0:2026/2027', DB::table('academic_sessions')->where('id', 3)->value('scope_key'));
            $this->assertSame(0, DB::table('department_institution_affiliations')->count());
            try {
                $migration->down();
                $this->fail('Destructive collapse must be blocked.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('preserved', $exception->getMessage());
            }
            $this->assertSame($before, (array) DB::table('legacy_research_records')->first());
        } finally {
            Schema::swap($previousSchema);
            DB::setDefaultConnection($previousConnection);
            DB::purge('adoption_upgrade');
        }
    }
}
