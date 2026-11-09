<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reader Pass, quiz-verified Read & Earn, monthly reader/creator reward pools
 * and challenge prize payouts. Additive only: no existing table or row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_quiz_questions')) {
            Schema::create('knowledge_quiz_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->text('question');
                $table->json('options');
                $table->unsignedTinyInteger('correct_option');
                $table->text('explanation')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['knowledge_publication_id', 'is_active'], 'kqq_publication_active');
            });
        }

        if (! Schema::hasTable('knowledge_reader_views')) {
            Schema::create('knowledge_reader_views', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->char('period', 7);
                $table->boolean('metered')->default(false);
                $table->timestamp('first_opened_at')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'knowledge_publication_id', 'period'], 'krv_user_publication_period');
                $table->index(['user_id', 'period', 'metered'], 'krv_user_period_metered');
            });
        }

        if (! Schema::hasTable('knowledge_read_attempts')) {
            Schema::create('knowledge_read_attempts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->char('period', 7);
                $table->unsignedTinyInteger('question_count')->default(0);
                $table->unsignedTinyInteger('correct_count')->default(0);
                $table->unsignedTinyInteger('score_percent')->default(0);
                $table->boolean('passed')->default(false);
                $table->text('reflection')->nullable();
                $table->unsignedSmallInteger('reflection_words')->default(0);
                $table->unsignedInteger('points')->default(0);
                $table->unsignedBigInteger('credit_minor')->default(0);
                $table->boolean('had_pass')->default(false);
                $table->boolean('counts_for_creator')->default(false);
                $table->string('status', 20)->default('not_counted');
                $table->string('reason', 120)->nullable();
                $table->unsignedInteger('read_seconds')->default(0);
                $table->string('ip_hash', 64)->nullable();
                $table->json('answers')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'knowledge_publication_id'], 'kra_user_publication');
                $table->index(['period', 'status'], 'kra_period_status');
                $table->index(['creator_id', 'period'], 'kra_creator_period');
                $table->index(['ip_hash', 'created_at'], 'kra_ip_created');
            });
        }

        if (! Schema::hasTable('knowledge_reading_challenges')) {
            Schema::create('knowledge_reading_challenges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedBigInteger('prize_pool_minor')->default(0);
                $table->json('prizes')->nullable();
                $table->unsignedInteger('min_passed_reads')->default(5);
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'starts_at', 'ends_at'], 'krc_status_window');
            });
        }

        if (! Schema::hasTable('knowledge_reward_payouts')) {
            Schema::create('knowledge_reward_payouts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 30);
                $table->string('scope_key', 80);
                $table->unsignedInteger('rank')->nullable();
                $table->unsignedBigInteger('points')->default(0);
                $table->unsignedBigInteger('amount_minor')->default(0);
                $table->string('status', 20)->default('draft');
                $table->string('note', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->unique(['scope_key', 'user_id'], 'krp_scope_user');
                $table->index(['kind', 'status'], 'krp_kind_status');
            });
        }

        // Reader Pass price: NGN 500 a month (NGN 1,500 a semester). Editable in
        // Admin > Monetization > pricing rules; never overwrites an existing rule.
        if (Schema::hasTable('pricing_rules') && ! DB::table('pricing_rules')->where('key', 'feature.knowledge_reader_pass')->exists()) {
            DB::table('pricing_rules')->insert([
                'uuid' => (string) Str::uuid(),
                'key' => 'feature.knowledge_reader_pass',
                'name' => 'Knowledge Hub Reader Pass',
                'scope_type' => 'global',
                'scope_id' => 0,
                'version' => 1,
                'currency' => 'NGN',
                'unit_amount_minor' => 50000,
                'enabled' => true,
                'metadata' => json_encode([
                    'pricing_type' => 'fixed_access',
                    'duration_days' => 30,
                    'semester_amount_minor' => 150000,
                    'semester_duration_days' => 120,
                    'grace_days' => 0,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_reward_payouts');
        Schema::dropIfExists('knowledge_reading_challenges');
        Schema::dropIfExists('knowledge_read_attempts');
        Schema::dropIfExists('knowledge_reader_views');
        Schema::dropIfExists('knowledge_quiz_questions');
    }
};
