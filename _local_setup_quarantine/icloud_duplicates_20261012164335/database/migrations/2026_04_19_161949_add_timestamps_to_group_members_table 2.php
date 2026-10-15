<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('group_members', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (!Schema::hasColumn('group_members', 'joined_at')) {
                $table->timestamp('joined_at')->useCurrent();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'joined_at')) {
                $table->dropColumn('joined_at');
            }
            if (Schema::hasColumn('group_members', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            if (Schema::hasColumn('group_members', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};
