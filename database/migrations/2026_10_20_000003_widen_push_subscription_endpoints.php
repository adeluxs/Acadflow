<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->char('endpoint_hash', 64)->nullable()->unique('push_endpoint_hash_unique');
        });
        DB::table('push_subscriptions')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) DB::table('push_subscriptions')->where('id', $row->id)->update(['endpoint_hash' => hash('sha256', $row->endpoint)]);
        });
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['endpoint']);
            $table->text('endpoint')->change();
            $table->char('endpoint_hash', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Preserve endpoints longer than the historical 255-character limit.
    }
};
