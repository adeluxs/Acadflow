<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['user_id', 'read_at'], 'notification_user_read_idx');
                $table->index(['user_id', 'created_at'], 'notification_user_created_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['user_id', 'status'], 'invoice_user_status_idx');
                $table->index(['user_id', 'semester_id'], 'invoice_user_semester_idx');
            });
        }

        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->index(['session_id', 'status'], 'attendance_session_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->dropIndex('attendance_session_status_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoice_user_status_idx');
                $table->dropIndex('invoice_user_semester_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notification_user_read_idx');
                $table->dropIndex('notification_user_created_idx');
            });
        }
    }
};
