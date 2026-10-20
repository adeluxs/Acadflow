<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'email_verification_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'security',
            'description' => 'Require email verification before workspace access (platform-wide). Disabling does not mark email addresses as verified.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve administrator configuration on rollback.
    }
};
