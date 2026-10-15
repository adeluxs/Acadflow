<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_account_requests')) {
            return;
        }

        Schema::create('commercial_account_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->foreignId('commercial_account_id')->nullable()->constrained('commercial_accounts')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 40);
            $table->unsignedBigInteger('requested_amount_minor')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->json('requested_changes')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['university_id', 'status', 'created_at'], 'commercial_request_university_status_idx');
            $table->index(['request_type', 'status', 'created_at'], 'commercial_request_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_account_requests');
    }
};
