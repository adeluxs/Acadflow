<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('endpoint')->unique();
                $table->text('keys_p256dh')->nullable();
                $table->text('keys_auth')->nullable();
                $table->string('user_agent')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
