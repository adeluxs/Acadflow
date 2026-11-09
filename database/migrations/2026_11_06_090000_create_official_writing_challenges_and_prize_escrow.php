<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Official AcadSyntra Writing Challenges (platform-run, platform-paid).
 * 2. Prize escrow for community challenges (creator-funded, held by AcadSyntra).
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_writing_challenges')) {
            Schema::create('knowledge_writing_challenges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('title');
                $table->string('theme', 160)->nullable();
                $table->text('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('submission_deadline');
                $table->dateTime('results_at');
                $table->json('tracks')->nullable();
                $table->json('prizes')->nullable();
                $table->unsignedBigInteger('track_prize_minor')->default(0);
                $table->unsignedInteger('min_words')->default(600);
                $table->unsignedTinyInteger('min_questions')->default(3);
                $table->unsignedTinyInteger('judge_weight')->default(70);
                $table->unsignedSmallInteger('finalists')->default(10);
                $table->string('status', 20)->default('draft');
                $table->timestamp('results_published_at')->nullable();
                $table->timestamp('announced_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'starts_at'], 'kwc_status_start');
            });
        }

        if (! Schema::hasTable('knowledge_writing_entries')) {
            Schema::create('knowledge_writing_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('knowledge_writing_challenge_id')->constrained('knowledge_writing_challenges')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('track_category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
                $table->string('status', 20)->default('entered');
                $table->decimal('judge_score', 6, 2)->nullable();
                $table->unsignedInteger('verified_reads')->default(0);
                $table->decimal('reader_score', 6, 2)->nullable();
                $table->decimal('final_score', 6, 2)->nullable();
                $table->unsignedInteger('rank')->nullable();
                $table->string('award', 60)->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->unique(['knowledge_writing_challenge_id', 'user_id'], 'kwe_challenge_user');
                $table->unique(['knowledge_writing_challenge_id', 'knowledge_publication_id'], 'kwe_challenge_publication');
                $table->index(['knowledge_publication_id', 'status'], 'kwe_publication_status');
            });
        }

        if (! Schema::hasTable('knowledge_writing_scores')) {
            Schema::create('knowledge_writing_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('knowledge_writing_entry_id')->constrained('knowledge_writing_entries')->cascadeOnDelete();
                $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
                $table->json('scores');
                $table->decimal('total', 6, 2);
                $table->string('comment', 1000)->nullable();
                $table->timestamps();
                $table->unique(['knowledge_writing_entry_id', 'judge_id'], 'kws_entry_judge');
            });
        }

        if (! Schema::hasTable('challenge_prize_funds')) {
            Schema::create('challenge_prize_funds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('academic_challenge_id')->unique()->constrained('academic_challenges')->cascadeOnDelete();
                $table->foreignId('funder_id')->constrained('users')->cascadeOnDelete();
                $table->string('prize_type', 20);
                $table->json('prizes');
                $table->unsignedBigInteger('total_minor')->default(0);
                $table->unsignedTinyInteger('fee_percent')->default(0);
                $table->unsignedBigInteger('fee_minor')->default(0);
                $table->unsignedBigInteger('from_earnings_minor')->default(0);
                $table->unsignedBigInteger('from_spending_minor')->default(0);
                $table->unsignedBigInteger('disbursed_minor')->default(0);
                $table->unsignedBigInteger('refunded_minor')->default(0);
                $table->string('status', 20)->default('unfunded');
                $table->timestamp('funded_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'funded_at'], 'cpf_status_funded');
            });
        }

        if (! Schema::hasTable('challenge_prize_awards')) {
            Schema::create('challenge_prize_awards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('challenge_prize_fund_id')->constrained('challenge_prize_funds')->cascadeOnDelete();
                $table->foreignId('academic_challenge_entry_id')->nullable()->constrained('academic_challenge_entries')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('rank');
                $table->unsignedBigInteger('amount_minor');
                $table->string('prize_type', 20);
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->unique(['challenge_prize_fund_id', 'rank'], 'cpa_fund_rank');
                $table->unique(['challenge_prize_fund_id', 'user_id'], 'cpa_fund_user');
            });
        }

        // Editable templates so admins can switch email/push per message type.
        if (Schema::hasTable('notification_event_templates')) {
            foreach (['knowledge_challenge_announcement', 'knowledge_writing_entry_received', 'knowledge_writing_results', 'challenge_prize_funded', 'challenge_prize_won', 'challenge_prize_refunded'] as $type) {
                if (! \Illuminate\Support\Facades\DB::table('notification_event_templates')->where('event_type', $type)->exists()) {
                    \Illuminate\Support\Facades\DB::table('notification_event_templates')->insert(['event_type' => $type, 'enabled' => true, 'title' => '{title}', 'message' => '{message}',
                        'channels' => json_encode(['email', 'push']), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_prize_awards');
        Schema::dropIfExists('challenge_prize_funds');
        Schema::dropIfExists('knowledge_writing_scores');
        Schema::dropIfExists('knowledge_writing_entries');
        Schema::dropIfExists('knowledge_writing_challenges');
    }
};
