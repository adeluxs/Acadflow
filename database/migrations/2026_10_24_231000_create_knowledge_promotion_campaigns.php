<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_promotion_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->decimal('price', 14, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('knowledge_promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
            $table->foreignId('knowledge_promotion_package_id')->constrained('knowledge_promotion_packages', indexName: 'kh_promo_campaign_package_fk')->restrictOnDelete();
            $table->string('title');
            $table->decimal('price', 14, 2);
            $table->string('status', 30)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at'], 'knowledge_promo_delivery_idx');
            $table->index(['creator_id', 'status', 'created_at'], 'knowledge_promo_creator_idx');
            $table->index(['knowledge_publication_id', 'status'], 'knowledge_promo_publication_idx');
        });

        DB::table('knowledge_promotion_packages')->insert([
            ['name' => 'Spotlight 3 days', 'slug' => 'spotlight-3-days', 'description' => 'Sponsored placement in Knowledge Hub discovery for three days.', 'duration_days' => 3, 'price' => 1000, 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Spotlight 7 days', 'slug' => 'spotlight-7-days', 'description' => 'Sponsored placement in Knowledge Hub discovery for seven days.', 'duration_days' => 7, 'price' => 2000, 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_promotion_campaigns');
        Schema::dropIfExists('knowledge_promotion_packages');
    }
};
