<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Remembers which category a duplicate was merged into, so merged copies stay out of every list. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('knowledge_categories', 'merged_into_id')) {
            Schema::table('knowledge_categories', function (Blueprint $table): void {
                $table->foreignId('merged_into_id')->nullable()->after('parent_id')->constrained('knowledge_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('knowledge_categories', 'merged_into_id')) {
            Schema::table('knowledge_categories', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('merged_into_id');
            });
        }
    }
};
