<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('creator_profiles', 'external_profiles')) {
                $table->json('external_profiles')->nullable()->after('social_links');
            }
            if (! Schema::hasColumn('creator_profiles', 'orcid_synced_at')) {
                $table->timestamp('orcid_synced_at')->nullable()->after('external_profiles');
            }
        });

        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE search_documents ADD FULLTEXT INDEX search_documents_fulltext (title, summary, body, keywords)');
            } elseif ($driver === 'pgsql') {
                DB::statement("CREATE INDEX IF NOT EXISTS search_documents_fulltext ON search_documents USING GIN (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(summary,'') || ' ' || coalesce(body,'') || ' ' || coalesce(keywords,'')))");
            }
        } catch (\Throwable) {
            // Existing or unsupported full-text indexes should not block installation;
            // the shared lexical/semantic application index remains functional.
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'mysql') DB::statement('ALTER TABLE search_documents DROP INDEX search_documents_fulltext');
            if ($driver === 'pgsql') DB::statement('DROP INDEX IF EXISTS search_documents_fulltext');
        } catch (\Throwable) {
        }

        Schema::table('creator_profiles', function (Blueprint $table): void {
            $columns = array_values(array_filter(['external_profiles', 'orcid_synced_at'], fn (string $column): bool => Schema::hasColumn('creator_profiles', $column)));
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
