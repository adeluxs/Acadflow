<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'model_type')) {
                $table->string('model_type')->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'model_id')) {
                $table->unsignedBigInteger('model_id')->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'field_name')) {
                $table->string('field_name')->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'old_value')) {
                $table->text('old_value')->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'new_value')) {
                $table->text('new_value')->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        if (Schema::hasColumn('audit_logs', 'entity_type')) {
            DB::table('audit_logs')->whereNull('model_type')->update([
                'model_type' => DB::raw('entity_type'),
            ]);
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            DB::table('audit_logs')->whereNull('model_id')->update([
                'model_id' => DB::raw('entity_id'),
            ]);
        }

        if (Schema::hasColumn('audit_logs', 'old_values')) {
            DB::table('audit_logs')->whereNull('old_value')->update([
                'old_value' => DB::raw('old_values'),
            ]);
        }

        if (Schema::hasColumn('audit_logs', 'new_values')) {
            DB::table('audit_logs')->whereNull('new_value')->update([
                'new_value' => DB::raw('new_values'),
            ]);
        }


        // Legacy columns are intentionally retained for backward compatibility.
        // The AuditLog model dual-writes both schemas until a separately tested
        // deprecation migration can remove them safely.
    }

    public function down(): void
    {
        // Keep the normalized schema to preserve compatibility and audit data.
    }
};
