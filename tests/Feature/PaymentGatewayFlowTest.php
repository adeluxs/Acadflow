<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Semester;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFundingRequest;
use App\Services\Commerce\CommerceService;
use App\Services\Commerce\WalletService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentGatewayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\SettingService::flushRequestCache();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        \Illuminate\Support\Facades\Route::middleware('web')->put('/__gateway/{paymentGateway}', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'update']);
        \Illuminate\Support\Facades\Route::middleware('web')->get('/__gateway/{paymentGateway}', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'edit']);
        \Illuminate\Support\Facades\Route::middleware('web')->post('/__gateway/{paymentGateway}/test', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'testConnection']);
    }

    private function gateway(array $overrides = []): PaymentGateway
    {
        return PaymentGateway::create(array_merge([
            'code' => 'paystack', 'name' => 'Paystack', 'is_active' => true, 'is_test_mode' => true,
            'credentials' => ['secret_key_test' => 'sk_test_example', 'public_key_test' => 'pk_test_example'],
            'settings' => ['environment' => 'test', 'supported_currencies' => ['NGN']],
        ], $overrides));
    }

    private function fakeProvider(string $status = 'success', array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($status, $overrides) {
            if (str_ends_with($request->url(), '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => ['reference' => $request['reference'], 'access_code' => 'access', 'authorization_url' => 'https://checkout.paystack.com/access']]);
            }
            $reference = rawurldecode(basename($request->url()));
            return Http::response(['status' => true, 'data' => array_merge(['reference' => $reference, 'status' => $status, 'amount' => 50000, 'currency' => 'NGN'], $overrides)]);
        });
    }

    private function webhook(array $payload, string $path = '/commerce/webhook/paystack', ?string $signature = null)
    {
        $raw = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $raw, 'sk_test_example')], $raw);
    }

    private function funding(User $user): Transaction
    {
        $result = app(CommerceService::class)->initiateWalletFunding($user, '500', 'paystack', 'fund-'.$user->id);
        return Transaction::where('uuid', $result['transaction_uuid'])->firstOrFail();
    }

    public function test_raw_webhook_body_and_duplicate_deliveries_credit_wallet_once_even_after_deactivation(): void
    {
        $gateway = $this->gateway();
        $this->fakeProvider();
        $user = User::factory()->create();
        $tx = $this->funding($user);
        $gateway->update(['is_active' => false]);
        $event = ['event' => 'charge.success', 'data' => ['reference' => $tx->gateway_transaction_id, 'note' => 'Lagos / café']];
        $this->webhook($event)->assertOk()->assertJsonPath('verified', true);
        $this->webhook($event, '/webhook/payment/paystack')->assertOk()->assertJsonPath('verified', true);
        $this->assertSame(50000, app(WalletService::class)->account($user)->fresh()->spending_balance_minor);
        $this->assertSame('completed', $tx->fresh()->status);
        $this->assertSame(1, DB::table('wallet_ledger_entries')->where('entry_type', 'wallet_funding')->count());
    }

    public function test_tampered_webhook_is_rejected_before_provider_call(): void
    {
        $this->gateway();
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => 'tampered']], signature: str_repeat('a', 128))->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('invalidConfirmations')]
    public function test_incomplete_or_mismatched_confirmation_never_credits_wallet(array $overrides): void
    {
        $this->gateway();
        $this->fakeProvider();
        $user = User::factory()->create();
        $tx = $this->funding($user);
        $this->fakeProvider(overrides: $overrides);
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => $tx->gateway_transaction_id]])->assertStatus(503);
        $this->assertSame(0, app(WalletService::class)->account($user)->fresh()->spending_balance_minor);
        $this->assertSame('pending', $tx->transactionable->fresh()->status);
    }

    public static function invalidConfirmations(): array
    {
        return [
            'wrong amount' => [['amount' => 49999]], 'missing amount' => [['amount' => null]],
            'wrong currency' => [['currency' => 'USD']], 'missing currency' => [['currency' => null]],
            'wrong reference' => [['reference' => 'different-payment']], 'pending' => [['status' => 'pending']],
            'fractional amount' => [['amount' => 50000.5]],
        ];
    }

    public function test_timeout_preserves_funding_reference_and_same_key_never_reposts(): void
    {
        $this->gateway();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $user = User::factory()->create();
        foreach ([1, 2] as $attempt) {
            try {
                app(CommerceService::class)->initiateWalletFunding($user, '500', 'paystack', 'timeout-1');
                $this->fail('Uncertain initialization must not be reported as successful.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors()['payment']);
            }
        }
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, WalletFundingRequest::count());
        $this->assertSame('initialization_unknown', Transaction::first()->gateway_status);
        $this->fakeProvider();
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => Transaction::first()->gateway_transaction_id]])->assertOk();
        $this->assertSame(50000, app(WalletService::class)->account($user)->fresh()->spending_balance_minor);
    }

    public function test_funding_double_click_reuses_checkout_and_cannot_leak_another_users_key(): void
    {
        $this->gateway();
        $this->fakeProvider();
        $user = User::factory()->create();
        $first = app(CommerceService::class)->initiateWalletFunding($user, '500', 'paystack', 'shared-key');
        $second = app(CommerceService::class)->initiateWalletFunding($user, '500', 'paystack', 'shared-key');
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        $other = User::factory()->create();
        try {
            app(CommerceService::class)->initiateWalletFunding($other, '500', 'paystack', 'shared-key');
            $this->fail('Another user must not receive this checkout.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Http::assertSentCount(1);
    }

    public function test_admin_can_save_only_test_keys_and_blank_secrets_preserve_them(): void
    {
        $gateway = $this->gateway(['credentials' => [], 'is_active' => false]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $data = ['environment' => 'test', 'supported_currencies' => ['NGN'], 'is_active' => '1', 'secret_key_test' => 'sk_test_new', 'public_key_test' => 'pk_test_new'];
        $this->actingAs($admin)->put('/__gateway/'.$gateway->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $data['secret_key_test'] = '';
        $this->put('/__gateway/'.$gateway->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('sk_test_new', app(PaymentGatewayManager::class)->gateway('paystack')->getSecretKey());
        $this->assertStringNotContainsString('sk_test_new', $gateway->fresh()->getRawOriginal('credentials'));
        $this->assertArrayNotHasKey('credentials', $gateway->fresh()->toArray());
        $this->get('/__gateway/'.$gateway->id)->assertOk()->assertSee('commerce/webhook/paystack')->assertDontSee('sk_test_new');
    }

    public function test_mode_uses_model_flag_and_legacy_encrypted_keys_still_work(): void
    {
        $this->gateway(['is_test_mode' => true, 'settings' => ['environment' => 'live'], 'credentials' => ['secret_key_test' => Crypt::encryptString('sk_test_old'), 'secret_key_live' => 'sk_live_other']]);
        $adapter = app(PaymentGatewayManager::class)->gateway('paystack');
        $this->assertTrue($adapter->isConfigured());
        $this->assertSame('sk_test_old', $adapter->getSecretKey());
    }

    public function test_connection_test_rejects_http_200_provider_failure(): void
    {
        $gateway = $this->gateway();
        Http::fake(['*' => Http::response(['status' => false], 200)]);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->postJson('/__gateway/'.$gateway->id.'/test')->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_invoice_webhook_settles_without_browser_return_and_manual_reference_cannot_self_verify(): void
    {
        $this->gateway();
        $this->fakeProvider();
        $user = User::factory()->create();
        $invoice = Invoice::create(['user_id' => $user->id, 'semester_id' => Semester::factory()->create()->id, 'amount' => '500.00', 'amount_minor' => 50000, 'currency' => 'NGN', 'status' => 'pending', 'due_date' => now()->addDay()]);
        $payment = app(PaymentService::class)->initiatePayment($invoice, 'card', 'paystack');
        $event = ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]];
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNull($payment->fresh()->verified_by);
        $manual = Payment::create(['invoice_id' => $invoice->id, 'user_id' => $user->id, 'amount' => '500', 'amount_minor' => 50000, 'currency' => 'NGN', 'payment_method' => 'bank_transfer', 'transaction_ref' => 'MANUAL-1', 'reference' => 'MANUAL-1', 'status' => 'pending']);
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->verifyPaymentByReference($manual->reference);
    }

    public function test_invoice_api_checkout_reuses_reference_and_browser_return_is_authorized(): void
    {
        $this->gateway();
        $this->fakeProvider();
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $invoice = Invoice::create(['user_id' => $user->id, 'semester_id' => Semester::factory()->create()->id, 'amount' => '500.00', 'amount_minor' => 50000, 'currency' => 'NGN', 'status' => 'pending', 'due_date' => now()->addDay()]);
        $data = ['invoice_id' => $invoice->id, 'gateway' => 'paystack'];
        $first = $this->actingAs($user)->postJson('/api/v1/payments/initiate', $data)->assertCreated();
        $reference = $first->json('verification_reference');
        $this->postJson('/api/v1/payments/initiate', $data)->assertOk()->assertJsonPath('verification_reference', $reference);
        Http::assertSentCount(1);
        $this->get(route('billing.payment-return', ['reference' => $reference]))->assertRedirect(route('commerce.wallet'))->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status);
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($other)->get(route('billing.payment-return', ['reference' => $reference]))->assertForbidden();
    }

    public function test_invoice_initialization_timeout_remains_verifiable_and_does_not_post_again(): void
    {
        $this->gateway();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $invoice = Invoice::create(['user_id' => $user->id, 'semester_id' => Semester::factory()->create()->id, 'amount' => '500', 'amount_minor' => 50000, 'currency' => 'NGN', 'status' => 'pending', 'due_date' => now()->addDay()]);
        $data = ['invoice_id' => $invoice->id, 'gateway' => 'paystack'];
        $this->actingAs($user)->postJson('/api/v1/payments/initiate', $data)->assertStatus(422)->assertJsonPath('retryable', false);
        $this->assertSame('pending', Payment::first()->status);
        $this->postJson('/api/v1/payments/initiate', $data)->assertStatus(409);
        $this->assertSame(1, Payment::count());
        $this->fakeProvider();
        $this->postJson('/api/v1/payments/verify', ['reference' => Payment::first()->reference])->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_original_environment_is_used_after_gateway_mode_switch(): void
    {
        $gateway = $this->gateway(['credentials' => ['secret_key_test' => 'sk_test_example', 'secret_key_live' => 'sk_live_example']]);
        $this->fakeProvider();
        $user = User::factory()->create();
        $tx = $this->funding($user);
        $gateway->update(['is_test_mode' => false]);
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => $tx->gateway_transaction_id]])->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/verify/') && $request->hasHeader('Authorization', 'Bearer sk_test_example'));
        $this->assertSame('completed', $tx->fresh()->status);
    }

    public function test_invalid_admin_mode_and_missing_live_key_do_not_flash_or_replace_saved_secret(): void
    {
        $gateway = $this->gateway();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->put('/__gateway/'.$gateway->id, ['environment' => 'live', 'supported_currencies' => ['NGN'], 'is_active' => '1', 'secret_key_live' => 'sk_test_wrong'])
            ->assertSessionHasErrors('secret_key_live')->assertSessionMissing('_old_input.secret_key_live');
        $this->assertTrue($gateway->fresh()->is_test_mode);
        $this->putJson('/__gateway/'.$gateway->id, ['environment' => 'typo', 'supported_currencies' => ['EUR']])->assertUnprocessable();
        $this->assertSame('sk_test_example', app(PaymentGatewayManager::class)->gateway('paystack')->getSecretKey());
    }

    #[DataProvider('invalidInitializationResponses')]
    public function test_bad_initialization_response_never_yields_checkout(array $response): void
    {
        $this->gateway();
        Http::fake(['*' => Http::response($response)]);
        $result = app(PaymentGatewayManager::class)->gateway('paystack')->initializePayment(['email' => 'buyer@example.test', 'reference' => 'expected', 'amount_minor' => 50000, 'currency' => 'NGN']);
        $this->assertFalse($result['status']);
        $this->assertTrue($result['outcome_unknown']);
        $this->assertArrayNotHasKey('authorization_url', $result);
    }

    public static function invalidInitializationResponses(): array
    {
        return [
            'provider failure' => [['status' => false]],
            'missing data' => [['status' => true]],
            'wrong reference' => [['status' => true, 'data' => ['reference' => 'other', 'authorization_url' => 'https://checkout.paystack.com/access', 'access_code' => 'access']]],
            'untrusted redirect' => [['status' => true, 'data' => ['reference' => 'expected', 'authorization_url' => 'https://attacker.test/access', 'access_code' => 'access']]],
        ];
    }

    public function test_processing_refund_reserves_balance_against_another_request(): void
    {
        $gateway = $this->gateway();
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, $gateway);
        $refund = app(CommerceService::class)->requestRefund($order, $buyer, '400', 'First', 'reserve-1');
        $refund->update(['status' => 'processing']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CommerceService::class)->requestRefund($order, $buyer, '200', 'Second', 'reserve-2');
    }

    public function test_unknown_refund_server_error_is_not_safe_to_resend(): void
    {
        $this->gateway();
        Http::fake(['*' => Http::response(['status' => false], 500)]);
        $result = app(PaymentGatewayManager::class)->gateway('paystack')->refund('paid-reference', 50000);
        $this->assertFalse($result['status']);
        $this->assertTrue($result['outcome_unknown']);
        $this->assertFalse($result['retryable']);
        Http::assertSentCount(1);
    }

    public function test_marketplace_checkout_grants_access_and_allocates_earnings_only_once(): void
    {
        $this->gateway();
        $this->fakeProvider();
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['onboarding_completed_at' => now()]);
        $publication = app(\App\Services\Knowledge\PublicationService::class)->createDraft([
            'title' => 'Premium research guide', 'body' => '<p>Research guide</p>', 'visibility' => 'public', 'access_type' => 'premium', 'price' => '500',
        ], $seller);
        $publication->update(['status' => 'published', 'published_at' => now()->subMinute(), 'price' => '500', 'access_type' => 'premium']);
        $commerce = app(CommerceService::class);
        $order = $commerce->createOrder($buyer, $publication, [], 'purchase-1');
        $first = $commerce->initialize($order, 'paystack');
        $second = $commerce->initialize($order, 'paystack');
        $this->assertSame($first['authorization_url'], $second['authorization_url']);
        Http::assertSentCount(1);
        $tx = $first['transaction'];
        $event = ['event' => 'charge.success', 'data' => ['reference' => $tx->gateway_transaction_id]];
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, \App\Models\CommerceEntitlement::where('user_id', $buyer->id)->count());
        $this->assertSame(1, DB::table('wallet_ledger_entries')->where('entry_type', 'sale_earning')->count());
        $this->assertSame(50000, (int) \App\Models\CommerceRevenueAllocation::sum('amount_minor'));
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($other)->get(route('commerce.callback', $tx))->assertForbidden();
    }

    public function test_old_refund_event_does_not_settle_new_partial_refund(): void
    {
        $gateway = $this->gateway();
        $buyer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $order = $this->paidOrder($buyer, $gateway);
        $order->refunds()->create(['requested_by' => $buyer->id, 'processed_by' => $admin->id, 'amount' => '250', 'amount_minor' => 25000, 'status' => 'completed', 'reason' => 'Earlier refund', 'gateway_refund_id' => '123']);
        $next = $order->refunds()->create(['requested_by' => $buyer->id, 'processed_by' => $admin->id, 'amount' => '250', 'amount_minor' => 25000, 'status' => 'processing', 'reason' => 'Later refund', 'gateway_refund_id' => '456']);
        Http::fake(['*/refund/456' => Http::response(['status' => true, 'data' => ['id' => 456, 'status' => 'pending', 'amount' => 25000, 'currency' => 'NGN']])]);
        $this->webhook(['event' => 'refund.processed', 'data' => ['id' => 123, 'transaction_reference' => $order->transaction->gateway_transaction_id, 'amount' => '25000', 'currency' => 'NGN']])->assertOk();
        $this->assertSame('processing', $next->fresh()->status);
        $this->assertNull($next->fresh()->provider_confirmed_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Http::assertSentCount(1);
    }

    public function test_legacy_pending_payment_requests_webhook_redelivery(): void
    {
        $gateway = $this->gateway();
        $this->fakeProvider('pending');
        $user = User::factory()->create();
        $tx = Transaction::create(['user_id' => $user->id, 'payment_gateway_id' => $gateway->id, 'transactionable_type' => \App\Models\UserSubscription::class, 'transactionable_id' => 999, 'amount' => '500', 'amount_minor' => 50000, 'currency' => 'NGN', 'type' => 'payment', 'status' => 'processing', 'gateway_transaction_id' => 'LEGACY-1']);
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => $tx->gateway_transaction_id]], '/webhook/payment/paystack')->assertStatus(503);
        $this->assertSame('processing', $tx->fresh()->status);
    }

    public function test_wallet_pending_payment_links_are_scoped_to_the_owner(): void
    {
        $this->gateway();
        $this->fakeProvider();
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $ownPayment = $this->funding($owner);
        $otherPayment = $this->funding($other);
        $this->actingAs($owner)->get(route('commerce.wallet'))->assertOk()
            ->assertSee('Payments awaiting confirmation')->assertSee(route('commerce.wallet.callback', $ownPayment))
            ->assertDontSee($otherPayment->uuid);
    }

    private function paidOrder(User $buyer, PaymentGateway $gateway): CommerceOrder
    {
        $order = CommerceOrder::create(['buyer_id' => $buyer->id, 'order_number' => 'ORDER-'.$buyer->id, 'currency' => 'NGN', 'subtotal' => '500', 'subtotal_minor' => 50000, 'total_amount' => '500', 'total_amount_minor' => 50000, 'status' => 'completed', 'payment_status' => 'paid']);
        $tx = Transaction::create(['user_id' => $buyer->id, 'payment_gateway_id' => $gateway->id, 'transactionable_type' => CommerceOrder::class, 'transactionable_id' => $order->id, 'amount' => '500', 'amount_minor' => 50000, 'currency' => 'NGN', 'type' => 'payment', 'status' => 'completed', 'gateway_transaction_id' => 'PAID-'.$buyer->id, 'metadata' => ['payment_source' => 'gateway']]);
        $order->update(['transaction_id' => $tx->id]);
        return $order->fresh('transaction');
    }

    public function test_pending_refund_waits_for_processed_event_and_replay_does_not_reverse_twice(): void
    {
        $gateway = $this->gateway();
        $buyer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $order = $this->paidOrder($buyer, $gateway);
        $commerce = app(CommerceService::class);
        $refund = $commerce->requestRefund($order, $buyer, '500', 'Test refund', 'refund-1');
        Http::fake([
            '*/refund' => Http::response(['status' => true, 'data' => ['id' => 123, 'status' => 'pending']]),
            '*/refund/123' => Http::response(['status' => true, 'data' => ['id' => 123, 'status' => 'processed', 'amount' => 50000, 'currency' => 'NGN']]),
        ]);
        $refund = $commerce->processRefund($refund, $admin, true);
        $this->assertSame('processing', $refund->status);
        $this->assertNull($refund->provider_confirmed_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
        try {
            $commerce->processRefund($refund, $admin, true);
            $this->fail('Pending provider refunds cannot be resubmitted.');
        } catch (ValidationException $exception) {
            Http::assertSentCount(1);
        }
        $event = ['event' => 'refund.processed', 'data' => ['id' => 123, 'transaction_reference' => $order->transaction->gateway_transaction_id, 'amount' => '50000', 'currency' => 'NGN']];
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(1, DB::table('ledger_journals')->where('reference', 'refund-payout:'.$refund->uuid)->count());
        Http::assertSentCount(2);
    }
}

