<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $exists = DB::table('settings')->where('key', 'siwes_assignment_enabled')->exists();
        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'siwes_assignment_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'academic',
            'description' => 'Allow the SIWES assignment type to be created and selected. Disabled by default.',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'siwes_assignment_enabled')->delete();
        }
    }
};
