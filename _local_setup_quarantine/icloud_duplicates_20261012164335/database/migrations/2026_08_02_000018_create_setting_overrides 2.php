<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('setting_overrides')) {
            Schema::create('setting_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('setting_id')->constrained('settings')->cascadeOnDelete();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->longText('value')->nullable();
                $table->string('type', 30)->default('string');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['setting_id', 'university_id']);
                $table->index(['university_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_overrides');
    }
};
