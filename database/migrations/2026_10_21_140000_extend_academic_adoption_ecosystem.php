<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Keep a leading user_id index in place before dropping the old unique
        // key: MySQL may be using that key to support the existing foreign key.
        Schema::table('user_institution_affiliations',fn(Blueprint $table)=>$table->index(['user_id','university_id','department_id','affiliation_type'],'academic_member_lookup'));
        Schema::table('user_institution_affiliations', function (Blueprint $table): void {
            $table->dropUnique('user_institution_affiliation_unique');
            $table->string('scope_key', 100)->nullable()->unique('academic_membership_scope_unique');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
        DB::table('user_institution_affiliations')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('user_institution_affiliations')->where('id', $row->id)->update([
                    'scope_key' => $row->user_id.':'.$row->university_id.':'.($row->department_id ?: 0).':'.$row->affiliation_type,
                ]);
            }
        });
        // Preserve administrator appointments made by the existing trusted
        // admin workflows. Self-declared students and email domains are never
        // converted into verified membership by this migration.
        DB::table('users')->whereIn('role', ['university_admin', 'department_admin'])->whereNotNull('university_id')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $departmentId = $user->role === 'department_admin' ? $user->department_id : null;
                if ($user->role === 'department_admin' && ! $departmentId) {
                    continue;
                }
                if ($departmentId && ! DB::table('departments')->join('faculties', 'faculties.id', '=', 'departments.faculty_id')->where('departments.id', $departmentId)->where('faculties.university_id', $user->university_id)->exists()) {
                    continue;
                }
                if (DB::table('user_institution_affiliations')->where('user_id', $user->id)->where('university_id', $user->university_id)->where('affiliation_type', $user->role)->exists()) {
                    continue;
                }
                DB::table('user_institution_affiliations')->insert(['uuid' => (string) Str::uuid(), 'scope_key' => $user->id.':'.$user->university_id.':'.($departmentId ?: 0).':'.$user->role, 'user_id' => $user->id, 'university_id' => $user->university_id, 'faculty_id' => $departmentId ? $user->faculty_id : null, 'department_id' => $departmentId, 'affiliation_type' => $user->role, 'status' => 'verified', 'verification_method' => 'legacy_admin_appointment', 'source' => 'academic_adoption_migration', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::table('departments', function (Blueprint $table): void {
            $table->json('adoption_settings')->nullable();
        });
        Schema::table('department_adoption_requests', function (Blueprint $table): void {
            $table->string('faculty_name')->nullable();
            $table->string('classification', 80)->nullable();
            $table->string('official_email')->nullable();
            $table->string('official_phone', 50)->nullable();
            $table->string('plan_key', 60)->nullable();
        });
        Schema::create('department_institution_affiliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('university_id')->constrained('universities')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('pending_department')->index();
            $table->foreignId('department_approved_by')->nullable()->constrained('users', indexName: 'dept_affiliation_consent_fk')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('department_approved_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('billing_owner', 20)->default('department');
            $table->text('note')->nullable();
            $table->json('policy')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status'], 'dept_institution_affiliation_state');
        });
        Schema::create('academic_membership_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('university_id')->constrained('universities')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('email');
            $table->string('role', 40);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('department_onboarding_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->json('data')->nullable();
            $table->timestamps();
        });
        Schema::table('commercial_accounts', function (Blueprint $table): void {
            // Institutional accounts retain their unique university_id. Department
            // accounts use department_id and leave university_id null.
            $table->foreignId('department_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('plan_key', 60)->nullable();
            $table->timestamp('access_ends_at')->nullable();
        });
        Schema::table('commercial_account_requests', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('academic_sessions', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['university_id','department_id'],'academic_period_lookup');
        });
        Schema::table('academic_sessions', function (Blueprint $table): void {
            $table->dropUnique('academic_sessions_university_id_name_unique');
            $table->string('name', 100)->change();
            $table->string('scope_key', 180)->nullable()->unique('academic_period_scope_unique');
        });
        DB::table('academic_sessions')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('academic_sessions')->where('id', $row->id)->update(['scope_key' => $row->university_id.':0:'.$row->name]);
            }
        });
        Schema::table('semesters', fn (Blueprint $table) => $table->string('name', 100)->change());
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('commercial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Once multiple memberships or department contracts exist, collapsing the
        // schema would discard valid history. Deployment rollback uses a backup
        // or a forward compatibility fix, never a destructive collapse.
        throw new RuntimeException('Academic adoption history must be preserved. Use a forward migration or restore the pre-deployment backup.');
    }
};
