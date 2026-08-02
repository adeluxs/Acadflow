<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_layout_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Font requirements (JSON array of normalized font family names)
            $table->json('required_fonts')->nullable();

            // Preferred page size: A4, Letter, Legal, A3, A5, or null for any
            $table->string('page_size')->nullable();

            // Minimum margin in inches (all four sides use this value)
            $table->float('min_margin_inches')->nullable();

            // Expected line spacing multiplier: 1.0, 1.5, 2.0, etc.
            $table->string('line_spacing')->nullable();

            // Minimum font size in points for body text
            $table->integer('min_font_size_pt')->nullable();

            // Whether page numbering is required
            $table->boolean('require_page_numbering')->default(false);

            // Whether institution branding (university name) must appear
            $table->boolean('require_institution_branding')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_layout_preferences');
    }
};
