<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_literature_notes')) {
            return;
        }

        Schema::table('research_literature_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('research_literature_notes', 'reading_status')) {
                $table->string('reading_status', 24)->default('saved')->after('created_by');
            }
            if (! Schema::hasColumn('research_literature_notes', 'collections')) {
                $table->json('collections')->nullable()->after('keywords');
            }
            if (! Schema::hasColumn('research_literature_notes', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('collections');
            }
        });

        if (Schema::hasColumn('research_literature_notes', 'reading_status')) {
            Schema::table('research_literature_notes', function (Blueprint $table): void {
                $table->index(['research_project_id', 'reading_status'], 'research_literature_status_idx');
            });

            DB::table('research_literature_notes')
                ->where(function ($query): void {
                    foreach (['summary', 'methodology', 'findings', 'limitations', 'contradictions', 'research_gap'] as $column) {
                        $query->orWhereNotNull($column);
                    }
                })
                ->update([
                    'reading_status' => 'reviewed',
                    'reviewed_at' => DB::raw('COALESCE(updated_at, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('research_literature_notes')) {
            return;
        }

        if (Schema::hasIndex('research_literature_notes', 'research_literature_status_idx')) {
            Schema::table('research_literature_notes', function (Blueprint $table): void {
                $table->dropIndex('research_literature_status_idx');
            });
        }

        foreach (['reading_status', 'collections', 'reviewed_at'] as $column) {
            if (Schema::hasColumn('research_literature_notes', $column)) {
                Schema::table('research_literature_notes', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
