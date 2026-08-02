<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
            $table->foreignId('university_id')->nullable()->after('user_id')->constrained('universities')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('university_id')->constrained('departments')->nullOnDelete();
            $table->string('plan_name', 100)->nullable()->after('plan_id');
            $table->enum('billing_model', ['institution', 'student', 'hybrid'])->nullable()->after('plan_name');
            $table->decimal('price_per_student', 10, 2)->nullable()->after('billing_model');
            $table->integer('grace_days')->default(7)->after('price_per_student');
            $table->date('start_date')->nullable()->after('grace_days');
            $table->date('end_date')->nullable()->after('start_date');
            $table->boolean('is_active')->default(true)->after('end_date');

            $table->index(['university_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('billing_model');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['university_id']);
            $table->dropIndex(['department_id', 'status']);
            $table->dropIndex(['university_id', 'status']);
            $table->dropIndex(['billing_model']);
            $table->dropColumn([
                'uuid',
                'university_id',
                'department_id',
                'plan_name',
                'billing_model',
                'price_per_student',
                'grace_days',
                'start_date',
                'end_date',
                'is_active',
            ]);
        });
    }
};
