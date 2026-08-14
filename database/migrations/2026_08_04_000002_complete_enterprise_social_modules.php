<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('academic_challenge_scores') && ! Schema::hasColumn('academic_challenge_scores', 'metadata')) {
            Schema::table('academic_challenge_scores', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('is_ai_assisted');
            });
        }

        Schema::table('knowledge_communities', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_communities', 'category_id')) $table->foreignId('category_id')->nullable()->after('department_id')->constrained('knowledge_categories')->nullOnDelete();
            if (! Schema::hasColumn('knowledge_communities', 'cover_media_id')) $table->foreignId('cover_media_id')->nullable()->after('owner_id')->constrained('media_assets')->nullOnDelete();
            if (! Schema::hasColumn('knowledge_communities', 'requires_moderation')) $table->boolean('requires_moderation')->default(false)->after('membership_mode');
            if (! Schema::hasColumn('knowledge_communities', 'member_count')) $table->unsignedInteger('member_count')->default(0)->after('status');
            if (! Schema::hasColumn('knowledge_communities', 'published_at')) $table->timestamp('published_at')->nullable()->after('settings');
        });

        if (! Schema::hasTable('knowledge_community_tag')) {
            Schema::create('knowledge_community_tag', function (Blueprint $table): void {
                $table->foreignId('knowledge_community_id')->constrained('knowledge_communities')->cascadeOnDelete();
                $table->foreignId('knowledge_tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
                $table->primary(['knowledge_community_id','knowledge_tag_id']);
                $table->timestamps();
            });
        }

        Schema::table('knowledge_community_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_community_members', 'invited_by')) $table->foreignId('invited_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('knowledge_community_members', 'reviewed_by')) $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('knowledge_community_members', 'reviewed_at')) $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            if (! Schema::hasColumn('knowledge_community_members', 'left_at')) $table->timestamp('left_at')->nullable()->after('joined_at');
        });

        if (! Schema::hasTable('knowledge_community_invitations')) {
            Schema::create('knowledge_community_invitations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('knowledge_community_id')->constrained('knowledge_communities')->cascadeOnDelete();
                $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invitee_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('email')->nullable();
                $table->string('role', 30)->default('member');
                $table->string('status', 30)->default('pending');
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['knowledge_community_id', 'status'], 'community_invites_community_status_idx');
                $table->index(['invitee_id', 'status']);
            });
        }

        Schema::table('academic_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_events', 'faculty_id')) $table->foreignId('faculty_id')->nullable()->after('university_id')->constrained('faculties')->nullOnDelete();
            if (! Schema::hasColumn('academic_events', 'group_id')) $table->foreignId('group_id')->nullable()->after('knowledge_community_id')->constrained('groups')->nullOnDelete();
            if (! Schema::hasColumn('academic_events', 'category_id')) $table->foreignId('category_id')->nullable()->after('group_id')->constrained('knowledge_categories')->nullOnDelete();
            if (! Schema::hasColumn('academic_events', 'cover_media_id')) $table->foreignId('cover_media_id')->nullable()->after('category_id')->constrained('media_assets')->nullOnDelete();
            if (! Schema::hasColumn('academic_events', 'format')) $table->string('format', 20)->default('physical')->after('event_type');
            if (! Schema::hasColumn('academic_events', 'timezone')) $table->string('timezone', 80)->default('UTC')->after('format');
            if (! Schema::hasColumn('academic_events', 'registration_deadline')) $table->dateTime('registration_deadline')->nullable()->after('ends_at');
            if (! Schema::hasColumn('academic_events', 'registration_mode')) $table->string('registration_mode', 30)->default('open')->after('registration_deadline');
            if (! Schema::hasColumn('academic_events', 'waitlist_enabled')) $table->boolean('waitlist_enabled')->default(true)->after('capacity');
            if (! Schema::hasColumn('academic_events', 'requires_moderation')) $table->boolean('requires_moderation')->default(false)->after('waitlist_enabled');
            if (! Schema::hasColumn('academic_events', 'published_at')) $table->timestamp('published_at')->nullable()->after('settings');
            if (! Schema::hasColumn('academic_events', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable()->after('published_at');
            if (! Schema::hasColumn('academic_events', 'cancellation_reason')) $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });

        if (! Schema::hasTable('academic_event_tag')) {
            Schema::create('academic_event_tag', function (Blueprint $table): void {
                $table->foreignId('academic_event_id')->constrained('academic_events')->cascadeOnDelete();
                $table->foreignId('knowledge_tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
                $table->primary(['academic_event_id','knowledge_tag_id']);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('academic_event_organizers')) {
            Schema::create('academic_event_organizers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_event_id')->constrained('academic_events')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('co_organizer');
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['academic_event_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('academic_event_invitations')) {
            Schema::create('academic_event_invitations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('academic_event_id')->constrained('academic_events')->cascadeOnDelete();
                $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invitee_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('email')->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['academic_event_id', 'status']);
                $table->index(['invitee_id', 'status']);
            });
        }

        if (! Schema::hasTable('academic_event_reminders')) {
            Schema::create('academic_event_reminders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_event_id')->constrained('academic_events')->cascadeOnDelete();
                $table->unsignedInteger('minutes_before');
                $table->string('channel', 30)->default('database');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_dispatched_at')->nullable();
                $table->timestamps();
                $table->unique(['academic_event_id', 'minutes_before', 'channel'], 'event_reminder_unique');
            });
        }

        Schema::table('academic_event_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_event_registrations', 'checked_in_by')) $table->foreignId('checked_in_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('academic_event_registrations', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable()->after('attended_at');
            if (! Schema::hasColumn('academic_event_registrations', 'check_in_code')) $table->string('check_in_code', 64)->nullable()->after('certificate_path');
        });

        Schema::table('academic_challenges', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_challenges', 'department_id')) $table->foreignId('department_id')->nullable()->after('university_id')->constrained('departments')->nullOnDelete();
            if (! Schema::hasColumn('academic_challenges', 'knowledge_community_id')) $table->foreignId('knowledge_community_id')->nullable()->after('organizer_id')->constrained('knowledge_communities')->nullOnDelete();
            if (! Schema::hasColumn('academic_challenges', 'group_id')) $table->foreignId('group_id')->nullable()->after('knowledge_community_id')->constrained('groups')->nullOnDelete();
            if (! Schema::hasColumn('academic_challenges', 'category_id')) $table->foreignId('category_id')->nullable()->after('group_id')->constrained('knowledge_categories')->nullOnDelete();
            if (! Schema::hasColumn('academic_challenges', 'cover_media_id')) $table->foreignId('cover_media_id')->nullable()->after('category_id')->constrained('media_assets')->nullOnDelete();
            if (! Schema::hasColumn('academic_challenges', 'visibility')) $table->string('visibility', 30)->default('public')->after('challenge_type');
            if (! Schema::hasColumn('academic_challenges', 'participation_mode')) $table->string('participation_mode', 30)->default('individual')->after('visibility');
            if (! Schema::hasColumn('academic_challenges', 'submission_deadline')) $table->dateTime('submission_deadline')->nullable()->after('ends_at');
            if (! Schema::hasColumn('academic_challenges', 'eligibility_rules')) $table->json('eligibility_rules')->nullable()->after('rules');
            if (! Schema::hasColumn('academic_challenges', 'max_team_members')) $table->unsignedSmallInteger('max_team_members')->nullable()->after('eligibility_rules');
            if (! Schema::hasColumn('academic_challenges', 'requires_moderation')) $table->boolean('requires_moderation')->default(false)->after('ai_assistance_enabled');
            if (! Schema::hasColumn('academic_challenges', 'published_at')) $table->timestamp('published_at')->nullable()->after('requires_moderation');
            if (! Schema::hasColumn('academic_challenges', 'results_published_at')) $table->timestamp('results_published_at')->nullable()->after('published_at');
        });

        if (! Schema::hasTable('academic_challenge_tag')) {
            Schema::create('academic_challenge_tag', function (Blueprint $table): void {
                $table->foreignId('academic_challenge_id')->constrained('academic_challenges')->cascadeOnDelete();
                $table->foreignId('knowledge_tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
                $table->primary(['academic_challenge_id','knowledge_tag_id']);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('academic_challenge_judges')) {
            Schema::create('academic_challenge_judges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_challenge_id')->constrained('academic_challenges')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('active');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
                $table->unique(['academic_challenge_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('academic_challenge_team_members')) {
            Schema::create('academic_challenge_team_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_challenge_entry_id')->constrained('academic_challenge_entries', 'id', 'challenge_team_entry_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('member');
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->unique(['academic_challenge_entry_id', 'user_id'], 'challenge_team_unique');
            });
        }

        Schema::table('academic_challenge_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_challenge_entries', 'team_name')) $table->string('team_name')->nullable()->after('user_id');
            if (! Schema::hasColumn('academic_challenge_entries', 'submission_url')) $table->text('submission_url')->nullable()->after('title');
            if (! Schema::hasColumn('academic_challenge_entries', 'is_final')) $table->boolean('is_final')->default(false)->after('status');
            if (! Schema::hasColumn('academic_challenge_entries', 'rank')) $table->unsignedInteger('rank')->nullable()->after('score');
            if (! Schema::hasColumn('academic_challenge_entries', 'reviewed_at')) $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
        });

        Schema::table('groups', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->change();
            $table->foreignId('semester_id')->nullable()->change();
            if (! Schema::hasColumn('groups', 'university_id')) $table->foreignId('university_id')->nullable()->after('uuid')->constrained('universities')->nullOnDelete();
            if (! Schema::hasColumn('groups', 'department_id')) $table->foreignId('department_id')->nullable()->after('university_id')->constrained('departments')->nullOnDelete();
            if (! Schema::hasColumn('groups', 'knowledge_community_id')) $table->foreignId('knowledge_community_id')->nullable()->after('department_id')->constrained('knowledge_communities')->nullOnDelete();
            if (! Schema::hasColumn('groups', 'research_project_id')) $table->foreignId('research_project_id')->nullable()->after('knowledge_community_id')->constrained('research_projects')->nullOnDelete();
            if (! Schema::hasColumn('groups', 'cover_media_id')) $table->foreignId('cover_media_id')->nullable()->after('leader_id')->constrained('media_assets')->nullOnDelete();
            if (! Schema::hasColumn('groups', 'group_type')) $table->string('group_type', 40)->default('study')->after('description');
            if (! Schema::hasColumn('groups', 'visibility')) $table->string('visibility', 30)->default('private')->after('group_type');
            if (! Schema::hasColumn('groups', 'membership_mode')) $table->string('membership_mode', 30)->default('approval')->after('visibility');
            if (! Schema::hasColumn('groups', 'settings')) $table->json('settings')->nullable()->after('formed_at');
        });

        Schema::table('group_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_members', 'status')) $table->string('status', 30)->default('active')->after('role');
            if (! Schema::hasColumn('group_members', 'invited_by')) $table->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('group_members', 'left_at')) $table->timestamp('left_at')->nullable()->after('joined_at');
            if (! Schema::hasColumn('group_members', 'updated_at')) $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        if (! Schema::hasTable('group_join_requests')) {
            Schema::create('group_join_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('message')->nullable();
                $table->string('status', 30)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['group_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('group_invitations')) {
            Schema::create('group_invitations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invitee_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('email')->nullable();
                $table->string('role', 30)->default('member');
                $table->string('status', 30)->default('pending');
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['group_id', 'status']);
            });
        }

        if (! Schema::hasTable('group_tasks')) {
            Schema::create('group_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 30)->default('open');
                $table->string('priority', 20)->default('normal');
                $table->dateTime('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['group_id', 'status', 'due_at']);
            });
        }

        if (! Schema::hasTable('group_resources')) {
            Schema::create('group_resources', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('external_url')->nullable();
                $table->string('visibility', 30)->default('members');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_challenge_scores') && Schema::hasColumn('academic_challenge_scores', 'metadata')) {
            Schema::table('academic_challenge_scores', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }

        Schema::dropIfExists('group_resources');
        Schema::dropIfExists('group_tasks');
        Schema::dropIfExists('group_invitations');
        Schema::dropIfExists('group_join_requests');
        Schema::dropIfExists('academic_challenge_team_members');
        Schema::dropIfExists('academic_challenge_tag');
        Schema::dropIfExists('academic_challenge_judges');
        Schema::dropIfExists('academic_event_reminders');
        Schema::dropIfExists('academic_event_invitations');
        Schema::dropIfExists('academic_event_tag');
        Schema::dropIfExists('academic_event_organizers');
        Schema::dropIfExists('knowledge_community_invitations');
        Schema::dropIfExists('knowledge_community_tag');
    }
};
