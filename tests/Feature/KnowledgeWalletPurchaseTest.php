<?php

namespace Tests\Feature;

use App\Models\CreatorMonetizationApplication;
use App\Models\KnowledgePublication;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Commerce\WalletService;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Premium Knowledge Hub content can be bought from the wallet even when no payment gateway is configured. */
class KnowledgeWalletPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
        // Selling is opt-in per installation (these features default to disabled).
        foreach (['knowledge_hub_premium', 'commerce_marketplace', 'creator_payouts'] as $feature) FeatureAccessService::setStatus($feature, 'enabled');
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function premiumPublication(User $creator, string $price = '800'): KnowledgePublication
    {
        CreatorMonetizationApplication::create(['user_id' => $creator->id, 'status' => 'approved', 'statement' => 'I publish my own original study guides for students.',
            'ownership_declaration' => true, 'terms_accepted' => true, 'submitted_at' => now(), 'reviewed_at' => now()]);
        $this->actingAs($creator);
        $type = DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id')
            ?? DB::table('knowledge_publication_types')->where('is_active', true)->value('id');
        $publication = app(PublicationService::class)->createDraft(['title' => 'Normalisation Study Guide', 'body' => '<p>Full premium guide content for revision.</p>',
            'publication_type_id' => $type, 'access_type' => 'premium', 'price' => $price, 'visibility' => 'public', 'ownership_declaration' => true], $creator);
        $publication->forceFill(['status' => 'published', 'visibility' => 'public', 'published_at' => now()])->save();
        $publication->document?->forceFill(['status' => 'published', 'visibility' => 'public'])->save();
        return $publication->fresh();
    }

    public function test_reader_unlocks_with_wallet_without_any_gateway_and_creator_earns_their_share(): void
    {
        $this->assertSame(0, PaymentGateway::where('is_active', true)->count());
        $creator = User::factory()->create();
        $publication = $this->premiumPublication($creator);
        $reader = User::factory()->create();
        app(WalletService::class)->creditSpending($reader, 200000, 'wallet_fund_test');

        $this->actingAs($reader)->get(route('knowledge.show', $publication))->assertOk()
            ->assertSee('Unlock for NGN 800.00')->assertSee('Wallet balance')->assertDontSee('Payment gateway</option>', false)
            ->assertDontSee('Full premium guide content');

        $this->post(route('commerce.purchase', $publication), ['payment_method' => 'wallet', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('knowledge.show', $publication));
        $this->get(route('knowledge.show', $publication))->assertSee('Full premium guide content');

        $this->assertSame(200000 - 80000, (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor'));
        // Default platform commission is 15%: the creator's 85% waits in pending earnings during the settlement hold.
        $this->assertSame(68000, (int) WalletAccount::where('user_id', $creator->id)->value('pending_earnings_minor'));
    }

    public function test_no_purchase_form_while_the_marketplace_is_switched_off(): void
    {
        FeatureAccessService::setStatus('commerce_marketplace', 'disabled');
        FeatureAccessService::flushRequestCache();
        $publication = $this->premiumPublication(User::factory()->create());
        $this->actingAs(User::factory()->create())->get(route('knowledge.show', $publication))->assertOk()
            ->assertSee('Protected academic resource')->assertDontSee('Unlock for');
    }

    public function test_guests_still_see_sign_in_instead_of_a_purchase_form(): void
    {
        $publication = $this->premiumPublication(User::factory()->create());
        auth()->logout();
        $this->get(route('knowledge.show', $publication))->assertOk()->assertSee('Sign in to continue')->assertDontSee('Unlock for');
    }
}
