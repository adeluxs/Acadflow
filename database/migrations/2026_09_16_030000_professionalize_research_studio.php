<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_project_invitations')) {
            Schema::create('research_project_invitations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 40)->default('supervisor');
                $table->string('status', 30)->default('pending');
                $table->text('message')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['invitee_id', 'status', 'expires_at'], 'research_project_invitee_status_idx');
                $table->index(['research_project_id', 'role', 'status'], 'research_project_invitation_role_idx');
                $table->unique(['research_project_id', 'invitee_id', 'role'], 'research_project_invitation_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_project_invitations');
    }
};
