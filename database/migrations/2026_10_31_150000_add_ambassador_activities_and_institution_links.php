<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: no existing column is renamed, retyped or backfilled, so the
 * migration is safe on a live database and its down() removes only what it adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ambassador_applications', function (Blueprint $table): void {
            // Set from the applicant's existing account affiliation, never from form input.
            $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
            $table->string('faculty', 200)->nullable();
            $table->string('social_profile_url', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->index(['status', 'created_at'], 'amb_app_status_created');
        });

        Schema::create('ambassador_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 160);
            $table->string('kind', 30);
            $table->text('summary');
            $table->text('instructions')->nullable();
            $table->string('resource_url', 1000)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_evidence')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_on', 'ends_on'], 'amb_tasks_active_window');
        });

        Schema::table('ambassador_reports', function (Blueprint $table): void {
            $table->foreignId('ambassador_task_id')->nullable()->constrained('ambassador_tasks')->nullOnDelete();
            // Private-disk path; files are served only through an authorised route.
            $table->string('evidence_path', 255)->nullable();
            $table->string('evidence_name', 160)->nullable();
            $table->string('evidence_mime', 100)->nullable();
            $table->unsignedInteger('evidence_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ambassador_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ambassador_task_id');
            $table->dropColumn(['evidence_path', 'evidence_name', 'evidence_mime', 'evidence_size']);
        });
        Schema::dropIfExists('ambassador_tasks');
        Schema::table('ambassador_applications', function (Blueprint $table): void {
            $table->dropIndex('amb_app_status_created');
            $table->dropConstrainedForeignId('university_id');
            $table->dropColumn(['faculty', 'social_profile_url', 'submitted_at', 'withdrawn_at']);
        });
    }
};
