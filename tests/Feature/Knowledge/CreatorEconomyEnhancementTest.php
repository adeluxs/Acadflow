<?php

namespace Tests\Feature\Knowledge;

use App\Models\CommerceEntitlement;
use App\Models\CreatorMonetizationApplication;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePromotionCampaign;
use App\Models\KnowledgePromotionPackage;
use App\Models\KnowledgePublicationType;
use App\Models\KnowledgeSeries;
use App\Models\User;
use App\Services\Commerce\CommerceService;
use App\Services\Commerce\WalletService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CreatorEconomyEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_types_are_database_driven_and_admin_can_manage_categories_without_replacing_ids(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('admin.knowledge.index'))
            ->assertOk()->assertSee('Knowledge Hub administration')->assertSee('Stories & Creativity');

        $this->actingAs($admin)->post(route('admin.knowledge.categories.store'), [
            'name' => 'Hostel Life', 'slug' => 'hostel-life', 'area' => 'campus_student_life',
            'description' => 'Real campus accommodation experiences.', 'sort_order' => 25, 'is_active' => 1,
        ])->assertRedirect();

        $category = KnowledgeCategory::where('slug', 'hostel-life')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.knowledge.categories.update', $category), [
            'name' => 'Hostels & Roommates', 'slug' => 'hostel-life', 'area' => 'campus_student_life',
            'description' => 'Accommodation and roommate experiences.', 'sort_order' => 26,
        ])->assertRedirect();

        $this->assertDatabaseHas('knowledge_categories', ['id' => $category->id, 'slug' => 'hostel-life', 'name' => 'Hostels & Roommates', 'is_active' => false]);
    }

    public function test_paid_publishing_requires_reviewed_creator_eligibility_while_free_publishing_remains_available(): void
    {
        $creator = User::factory()->create();
        $type = KnowledgePublicationType::where('slug', 'tutorial')->firstOrFail();
        $service = app(PublicationService::class);

        $free = $service->createDraft(['title' => 'Free tutorial', 'body' => '<p>Useful free lesson.</p>', 'publication_type_id' => $type->id, 'visibility' => 'public', 'access_type' => 'free'], $creator);
        $this->assertSame('free', $free->access_type);

        try {
            $service->createDraft(['title' => 'Paid tutorial', 'body' => '<p>Original paid lesson.</p>', 'publication_type_id' => $type->id, 'visibility' => 'public', 'access_type' => 'premium', 'price' => '500', 'ownership_declaration' => true], $creator);
            $this->fail('Paid publishing was allowed without creator approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('access_type', $exception->errors());
        }

        CreatorMonetizationApplication::create(['user_id' => $creator->id, 'status' => 'approved', 'ownership_declaration' => true, 'terms_accepted' => true, 'submitted_at' => now(), 'reviewed_at' => now()]);
        $paid = $service->createDraft(['title' => 'Paid tutorial', 'body' => '<p>Original paid lesson.</p>', 'publication_type_id' => $type->id, 'visibility' => 'public', 'access_type' => 'premium', 'price' => '500', 'ownership_declaration' => true], $creator);
        $this->assertNotNull($paid->ownership_attested_at);
    }

    public function test_premium_chapter_reuses_verified_commerce_entitlements_and_historic_commission_split(): void
    {
        $creator = User::factory()->create();
        $buyer = User::factory()->create();
        $type = KnowledgePublicationType::where('slug', 'fiction')->firstOrFail();
        CreatorMonetizationApplication::create(['user_id' => $creator->id, 'status' => 'approved', 'ownership_declaration' => true, 'terms_accepted' => true, 'submitted_at' => now(), 'reviewed_at' => now()]);
        $series = KnowledgeSeries::create(['creator_id' => $creator->id, 'publication_type_id' => $type->id, 'title' => 'The Long Semester', 'description' => 'A campus fiction series.', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $chapter = app(PublicationService::class)->createDraft(['title' => 'The First Lecture', 'body' => '<p>The story begins.</p>', 'publication_type_id' => $type->id, 'series_id' => $series->id, 'chapter_number' => 1, 'visibility' => 'public', 'access_type' => 'premium', 'price' => '500', 'ownership_declaration' => true], $creator);
        $chapter->update(['status' => 'published', 'published_at' => now()->subMinute()]);

        app(WalletService::class)->creditSpending($buyer, 60000, 'test_funding', description: 'Test balance.');
        $commerce = app(CommerceService::class);
        $order = $commerce->createOrder($buyer, $chapter, [], 'chapter-order');
        $commerce->purchaseWithWallet($order, $buyer, 'chapter-payment');

        $this->assertSame(1, CommerceEntitlement::where('user_id', $buyer->id)->where('entitled_type', $chapter->getMorphClass())->where('entitled_id', $chapter->id)->count());
        $this->assertDatabaseHas('commerce_revenue_allocations', ['allocation_type' => 'platform', 'amount_minor' => 7500]);
        $this->assertDatabaseHas('commerce_revenue_allocations', ['allocation_type' => 'creator', 'amount_minor' => 42500]);
        $this->expectException(HttpException::class);
        $commerce->createOrder($buyer, $chapter, [], 'duplicate-chapter-order');
    }

    public function test_series_progress_is_user_scoped_and_runtime_toggles_are_backend_enforced(): void
    {
        $creator = User::factory()->create();
        $reader = User::factory()->create();
        $other = User::factory()->create();
        $type = KnowledgePublicationType::where('slug', 'fiction')->firstOrFail();
        $series = KnowledgeSeries::create(['creator_id' => $creator->id, 'publication_type_id' => $type->id, 'title' => 'Return Story', 'description' => 'A serialized story.', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $chapter = app(PublicationService::class)->createDraft(['title' => 'Chapter One', 'body' => '<p>Free chapter.</p>', 'publication_type_id' => $type->id, 'series_id' => $series->id, 'chapter_number' => 1, 'visibility' => 'public', 'access_type' => 'free'], $creator);
        $chapter->update(['status' => 'published', 'published_at' => now()->subMinute()]);

        $this->actingAs($reader)->put(route('knowledge.series.progress', [$series, $chapter]), ['progress_percent' => 75])->assertRedirect();
        $this->assertDatabaseHas('knowledge_reading_progress', ['user_id' => $reader->id, 'knowledge_series_id' => $series->id, 'progress_percent' => 75]);
        $this->assertDatabaseMissing('knowledge_reading_progress', ['user_id' => $other->id]);

        SettingService::set('knowledge_comments_enabled', false, 'boolean');
        $this->actingAs($reader)->post(route('knowledge.comments.store', $chapter), ['body' => 'A comment'])->assertNotFound();
    }

    public function test_paid_promotion_reuses_commerce_and_is_clearly_labelled_with_delivery_metrics(): void
    {
        $creator = User::factory()->create();
        $type = KnowledgePublicationType::where('slug', 'tutorial')->firstOrFail();
        $publication = app(PublicationService::class)->createDraft(['title' => 'Practical Machine Learning', 'body' => '<p>A practical guide.</p>', 'publication_type_id' => $type->id, 'visibility' => 'public', 'access_type' => 'free'], $creator);
        $publication->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $package = KnowledgePromotionPackage::query()->where('is_active', true)->firstOrFail();
        $campaign = KnowledgePromotionCampaign::create(['creator_id' => $creator->id, 'knowledge_publication_id' => $publication->id, 'knowledge_promotion_package_id' => $package->id, 'title' => $package->name.' — '.$publication->title, 'price' => $package->price, 'status' => 'draft']);

        app(WalletService::class)->creditSpending($creator, 300000, 'test_funding', description: 'Test balance.');
        $commerce = app(CommerceService::class);
        $order = $commerce->createOrder($creator, $campaign, [], 'promotion-order');
        $commerce->purchaseWithWallet($order, $creator, 'promotion-payment');

        $campaign->refresh();
        $this->assertSame('active', $campaign->status);
        $this->assertTrue($campaign->ends_at->greaterThan($campaign->starts_at));
        $this->assertDatabaseHas('commerce_revenue_allocations', ['commerce_order_item_id' => $order->items->first()->id, 'allocation_type' => 'platform', 'amount_minor' => (int) round((float) $package->price * 100)]);

        $this->get(route('knowledge.index'))->assertOk()->assertSee('Sponsored publications')->assertSee('Promotion does not indicate academic review or endorsement.');
        $this->get(route('knowledge.promotions.click', $campaign))->assertRedirect(route('knowledge.show', $publication));
        $this->assertGreaterThan(0, $campaign->fresh()->impressions);
        $this->assertSame(1, $campaign->fresh()->clicks);
    }
}
