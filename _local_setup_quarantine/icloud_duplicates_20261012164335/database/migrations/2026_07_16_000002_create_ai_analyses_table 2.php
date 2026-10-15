<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature'); // submission_validator | plagiarism | ...
            $table->string('status')->default('queued'); // queued|processing|completed|failed
            $table->string('source')->default('rule_engine');
            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedInteger('issue_count')->default(0);
            $table->json('issues')->nullable();
            $table->text('summary')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'feature']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
