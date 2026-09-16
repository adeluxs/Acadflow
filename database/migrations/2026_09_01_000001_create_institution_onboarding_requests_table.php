<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institution_onboarding_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
            $table->string('institution_name');
            $table->string('short_name', 80)->nullable();
            $table->string('code', 20)->nullable();
            $table->string('institution_type', 30)->default('university');
            $table->string('ownership', 30)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('regulator', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website', 500)->nullable();
            $table->text('address')->nullable();
            $table->string('representative_title', 150)->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status','created_at']);
            $table->index(['requester_user_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('institution_onboarding_requests'); }
};
