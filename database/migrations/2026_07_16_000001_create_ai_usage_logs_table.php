<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('feature');
            $table->string('mode')->default('rule_based');
            $table->string('source')->default('rule_engine');
            $table->boolean('cached')->default(false);
            $table->boolean('success')->default(true);
            $table->decimal('processing_time', 10, 4)->nullable();
            $table->decimal('cost', 12, 6)->default(0);
            $table->decimal('estimated_savings', 12, 6)->default(0);
            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedInteger('issue_count')->default(0);
            $table->timestamps();

            $table->index(['feature']);
            $table->index(['university_id', 'department_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
