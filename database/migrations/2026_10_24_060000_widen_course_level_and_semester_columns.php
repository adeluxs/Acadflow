<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        $hasLevel = Schema::hasColumn('courses', 'level');
        $hasSemester = Schema::hasColumn('courses', 'semester');

        Schema::table('courses', function (Blueprint $table) use ($hasLevel, $hasSemester): void {
            if ($hasLevel) {
                $table->string('level', 30)->change();
            }
            if ($hasSemester) {
                $table->string('semester', 30)->change();
            }
        });
    }

    public function down(): void
    {
        // Preserve values longer than the historical 10-character limit.
        // Narrowing these columns would corrupt valid independent-course data.
    }
};
