<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_sessions', 'geofence_lat')) {
                $table->decimal('geofence_lat', 10, 8)->nullable()->after('status');
            }

            if (! Schema::hasColumn('attendance_sessions', 'geofence_lng')) {
                $table->decimal('geofence_lng', 11, 8)->nullable()->after('geofence_lat');
            }
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_records', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->timestamp('check_in_at')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_sessions', 'geofence_lng')) {
                $table->dropColumn('geofence_lng');
            }

            if (Schema::hasColumn('attendance_sessions', 'geofence_lat')) {
                $table->dropColumn('geofence_lat');
            }
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_records', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
