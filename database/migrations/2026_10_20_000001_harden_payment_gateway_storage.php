<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel encrypted casts produce ciphertext, which is not a JSON document.
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->text('credentials')->nullable()->change();
        });
        DB::table('payment_gateways')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                if (! is_string($row->credentials)) continue;
                $decoded = json_decode($row->credentials, true);
                if (is_array($decoded)) {
                    DB::table('payment_gateways')->where('id', $row->id)->update(['credentials' => Crypt::encryptString(json_encode($decoded, JSON_THROW_ON_ERROR))]);
                } elseif (is_string($decoded)) {
                    DB::table('payment_gateways')->where('id', $row->id)->update(['credentials' => $decoded]);
                }
            }
        });
        // The historical MySQL enum omitted wallet_funding; SQLite did not expose it.
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('type', 40)->default('payment')->change();
        });
    }

    public function down(): void
    {
        // Keep the wider storage types: narrowing would discard funding records
        // or make existing encrypted credentials invalid JSON.
    }
};
