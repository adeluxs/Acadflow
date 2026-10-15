<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenUserRoleColumn();

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 60)->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type', 60)->nullable()->index()->after('role');
            }
            if (! Schema::hasColumn('users', 'faculty_id')) {
                $table->foreignId('faculty_id')->nullable()->after('university_id')->constrained('faculties')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable()->after('country_code');
            }
            if (! Schema::hasColumn('users', 'programme')) {
                $table->string('programme')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('users', 'academic_level')) {
                $table->string('academic_level', 80)->nullable()->after('programme');
            }
            if (! Schema::hasColumn('users', 'research_interests')) {
                $table->json('research_interests')->nullable()->after('academic_level');
            }
            if (! Schema::hasColumn('users', 'skills')) {
                $table->json('skills')->nullable()->after('research_interests');
            }
            if (! Schema::hasColumn('users', 'topic_interests')) {
                $table->json('topic_interests')->nullable()->after('skills');
            }
            if (! Schema::hasColumn('users', 'event_interests')) {
                $table->json('event_interests')->nullable()->after('topic_interests');
            }
            if (! Schema::hasColumn('users', 'community_interests')) {
                $table->json('community_interests')->nullable()->after('event_interests');
            }
            if (! Schema::hasColumn('users', 'avatar_media_id')) {
                $table->foreignId('avatar_media_id')->nullable()->after('avatar')->constrained('media_assets')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'profile_visibility')) {
                $table->string('profile_visibility', 30)->default('public')->after('avatar');
            }
            if (! Schema::hasColumn('users', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('profile_visibility');
            }
            if (! Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->index()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'onboarding_version')) {
                $table->unsignedSmallInteger('onboarding_version')->default(1)->after('onboarding_completed_at');
            }
        });

        if (! Schema::hasTable('user_onboarding_states')) {
            Schema::create('user_onboarding_states', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('path', 60)->nullable()->index();
                $table->unsignedSmallInteger('current_step')->default(1);
                $table->json('data')->nullable();
                $table->json('skipped_steps')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_saved_at')->nullable();
                $table->timestamp('completed_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_states');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'avatar_media_id')) {
                $table->dropConstrainedForeignId('avatar_media_id');
            }
            if (Schema::hasColumn('users', 'faculty_id')) {
                $table->dropConstrainedForeignId('faculty_id');
            }

            $columns = [
                'username', 'account_type', 'country_code', 'location', 'programme', 'academic_level',
                'research_interests', 'skills', 'topic_interests', 'event_interests', 'community_interests',
                'profile_visibility', 'notification_preferences', 'onboarding_completed_at', 'onboarding_version',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function widenUserRoleColumn(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'member'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(50) USING role::text');
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'member'");
            return;
        }

        // Laravel 12 can rebuild SQLite columns safely during tests.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 50)->default('member')->change();
        });
    }

};
