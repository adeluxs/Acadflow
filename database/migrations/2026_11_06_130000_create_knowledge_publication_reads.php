<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per reader per publication, so the read count only goes up once per person. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('knowledge_publication_reads')) {
            return;
        }
        Schema::create('knowledge_publication_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('visitor_key', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['knowledge_publication_id', 'user_id'], 'kpr_publication_user');
            $table->unique(['knowledge_publication_id', 'visitor_key'], 'kpr_publication_visitor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_publication_reads');
    }
};
