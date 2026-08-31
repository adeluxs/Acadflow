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

        $now = now();
        $rows = [
            ['key' => 'login_requests_per_minute', 'value' => '10', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum login requests per email/IP combination per minute'],
            ['key' => 'registration_requests_per_hour', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum registration requests per IP address per hour'],
            ['key' => 'password_reset_requests_per_minute', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum password-reset requests per email/IP combination per minute'],
            ['key' => 'verification_requests_per_minute', 'value' => '6', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum email-verification requests per user or IP address per minute'],
            ['key' => 'two_factor_attempts_per_minute', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum two-factor verification attempts per user or IP address per minute'],
            ['key' => 'payment_requests_per_minute', 'value' => '10', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum wallet funding/payment initialization requests per user or IP address per minute'],
        ];

        foreach ($rows as &$row) {
            $row['is_public'] = false;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Intentionally non-destructive. Administrators may have customized
        // these values after deployment, so rollback must not delete them.
    }
};
