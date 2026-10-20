<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('research_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('content_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['research_project_id', 'kind'], 'research_resource_project_kind');
        });
    }
    public function down(): void { Schema::dropIfExists('research_resources'); }
};
