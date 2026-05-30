<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix the subscriptions table to properly support department-level subscriptions
        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'department_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                // Add department_id after university_id for department-level subscriptions
                $table->foreignId('department_id')->nullable()->after('university_id')
                      ->constrained('departments')->nullOnDelete();
                
                // Add index for department queries
                $table->index('department_id');
                
                // Rename plan_name to subscription_plan_id to properly link to plans
                // But since plan_name is a string and used in code, we'll add a proper foreign key instead
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'department_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }
};
