<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureIndex(
            'ai_grounding_sources',
            ['ai_grounding_session_id', 'relevance_score'],
            'ai_grounding_session_relevance_idx',
        );
        $this->ensureIndex(
            'verification_requests',
            ['university_id', 'status', 'verification_type'],
            'verification_tenant_status_type_idx',
        );
        $this->ensureIndex(
            'siwes_log_entries',
            ['siwes_placement_id', 'entry_date', 'period_type'],
            'siwes_log_placement_date_period_idx',
        );
        $this->ensureUnique(
            'siwes_attendance_records',
            ['siwes_placement_id', 'attendance_date'],
            'siwes_attendance_placement_date_uq',
        );
        $this->ensureIndex(
            'commerce_revenue_allocations',
            ['beneficiary_university_id', 'status'],
            'commerce_alloc_beneficiary_status_idx',
        );
        $this->ensureUnique(
            'academic_challenge_votes',
            ['academic_challenge_entry_id', 'user_id'],
            'challenge_votes_entry_user_uq',
        );
        $this->ensureIndex(
            'knowledge_community_invitations',
            ['knowledge_community_id', 'status'],
            'community_invites_community_status_idx',
        );
        $this->ensureIndex(
            'knowledge_citations',
            ['citing_publication_id', 'cited_publication_id'],
            'knowledge_citations_pub_pair_idx',
        );
        $this->ensureForeign(
            'academic_challenge_team_members',
            'academic_challenge_entry_id',
            'academic_challenge_entries',
            'id',
            'challenge_team_entry_fk',
        );
    }

    public function down(): void
    {
        // This is a repair migration. The indexes are part of the canonical schema
        // and intentionally remain in place on rollback to avoid degrading installs.
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    /** @param list<string> $columns */
    private function ensureUnique(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? []) === $columns && (bool) ($index['unique'] ?? false)) {
                return;
            }
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    private function ensureForeign(string $table, string $column, string $referencedTable, string $referencedColumn, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === [$column]) {
                return;
            }
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $name): void {
            $blueprint->foreign($column, $name)
                ->references($referencedColumn)
                ->on($referencedTable)
                ->cascadeOnDelete();
        });
    }
};
