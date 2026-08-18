<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_logs')) return;

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_usage_logs', 'request_id')) $table->uuid('request_id')->nullable()->after('id');
            if (! Schema::hasColumn('ai_usage_logs', 'provider')) $table->string('provider', 40)->nullable()->after('source');
            if (! Schema::hasColumn('ai_usage_logs', 'model')) $table->string('model', 120)->nullable()->after('provider');
            if (! Schema::hasColumn('ai_usage_logs', 'fallback_used')) $table->boolean('fallback_used')->default(false)->after('model');
            if (! Schema::hasColumn('ai_usage_logs', 'fallback_provider')) $table->string('fallback_provider', 40)->nullable()->after('fallback_used');
            if (! Schema::hasColumn('ai_usage_logs', 'error_type')) $table->string('error_type', 80)->nullable()->after('fallback_provider');
            if (! Schema::hasColumn('ai_usage_logs', 'grounding_used')) $table->boolean('grounding_used')->default(false)->after('error_type');
            if (! Schema::hasColumn('ai_usage_logs', 'metadata')) $table->json('metadata')->nullable()->after('grounding_used');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            // Explicit short names avoid MySQL's 64-character identifier limit.
            $table->index('request_id', 'ai_usage_req_idx');
            $table->index(['university_id', 'provider', 'created_at'], 'ai_usage_tenant_provider_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_usage_logs')) return;
        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            try { $table->dropIndex('ai_usage_req_idx'); } catch (\Throwable) {}
            try { $table->dropIndex('ai_usage_tenant_provider_idx'); } catch (\Throwable) {}
            foreach (['request_id','provider','model','fallback_used','fallback_provider','error_type','grounding_used','metadata'] as $column) {
                if (Schema::hasColumn('ai_usage_logs', $column)) $table->dropColumn($column);
            }
        });
    }
};
