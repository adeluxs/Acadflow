<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique('contact_reference_unique');
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('topic', 40);
            $table->text('message');
            $table->string('status', 20)->default('open')->index('contact_status_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Contact enquiries are retained; rolling back must not delete them.
    }
};
