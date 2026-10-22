<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->index(['user_id', 'type'], 'academic_notice_recipient'));
        Schema::table('user_institution_affiliations', fn (Blueprint $table) => $table->index(['department_id', 'status', 'affiliation_type'], 'academic_department_members'));
    }

    public function down(): void
    {
        Schema::table('user_institution_affiliations', fn (Blueprint $table) => $table->dropIndex('academic_department_members'));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('academic_notice_recipient'));
    }
};
