<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\SettingService;
use App\Support\Errors\UserFacingError;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    use ScopesTenantData;
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayManager $gateways,
    ) {
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['semester.academicSession.university', 'user']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } else {
            $this->scopeInvoiceQuery($query, $user);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query->latest()->paginate(20);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->assertInvoiceAccess($request, $invoice);

        return $invoice->load(['semester.academicSession.university', 'user', 'payments']);
    }

    public function payments(Request $request)
    {
        $query = Payment::with(['invoice.semester.academicSession.university']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } else {
            $this->scopeInvoiceQuery($query, $user, 'invoice');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query->latest()->paginate(20);
    }

    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $payment = Payment::with('invoice.user')->where('reference', $validated['reference'])->first();
        abort_if(! $payment, 404, 'Payment not found.');
        $this->assertInvoiceAccess($request, $payment->invoice);

        if ($payment->status === 'verified') {
            return response()->json(['message' => 'Payment already verified.', 'payment' => $payment]);
        }

        $metadata = json_decode((string) $payment->notes, true) ?: [];
        $gatewayCode = (string) ($metadata['gateway'] ?? $this->gateways->getDefaultGateway());
        $verification = $this->gateways->gateway($gatewayCode)->verifyPayment($payment->reference);

        if (! ($verification['status'] ?? false)) {
            $retryable = (bool) ($verification['retryable'] ?? false);
            $payment->update([
                // A temporary gateway/network failure does not prove that a
                // payment failed. Keep the record pending until it can be checked.
                'status' => $retryable ? 'pending' : 'failed',
                'notes' => json_encode(array_merge($metadata, [
                    'last_verification' => now()->toIso8601String(),
                    'verification_code' => $verification['code'] ?? 'PAYMENT_VERIFICATION_FAILED',
                ]), JSON_THROW_ON_ERROR),
            ]);

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => $verification['code'] ?? 'PAYMENT_VERIFICATION_FAILED',
                'message' => $verification['message'] ?? 'We could not verify the payment right now.',
                'retryable' => $retryable,
                'request_id' => UserFacingError::requestId($request),
                'payment' => $payment->fresh(),
            ], $retryable ? 503 : 422, ['X-Request-Id' => UserFacingError::requestId($request)]);
        }

        if (! ($verification['paid'] ?? false)) {
            $gatewayStatus = strtolower((string) data_get($verification, 'data.status', 'pending'));
            $definitivelyFailed = in_array($gatewayStatus, ['failed', 'abandoned', 'reversed', 'cancelled', 'canceled'], true);
            $payment->update([
                'status' => $definitivelyFailed ? 'failed' : 'pending',
                'notes' => json_encode(array_merge($metadata, [
                    'last_verification' => now()->toIso8601String(),
                    'gateway_status' => $gatewayStatus,
                ]), JSON_THROW_ON_ERROR),
            ]);

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => $definitivelyFailed ? 'PAYMENT_NOT_COMPLETED' : 'PAYMENT_STILL_PROCESSING',
                'message' => $definitivelyFailed
                    ? 'The payment was not completed.'
                    : 'The payment has not been confirmed yet. You can check its status again shortly.',
                'retryable' => ! $definitivelyFailed,
                'request_id' => UserFacingError::requestId($request),
                'payment' => $payment->fresh(),
            ], $definitivelyFailed ? 422 : 202, ['X-Request-Id' => UserFacingError::requestId($request)]);
        }

        $expectedMinor = $payment->amount_minor !== null ? (int) $payment->amount_minor : Money::toMinor((string) $payment->amount);
        $providerMinor = (int) data_get($verification, 'data.amount', -1);
        $providerCurrency = strtoupper((string) data_get($verification, 'data.currency', ''));
        $universityId=$payment->invoice?->user?->university_id;
        $commercial=$universityId?\App\Models\CommercialAccount::query()->where('university_id',$universityId)->first():null;
        $expectedCurrency = strtoupper((string) ($payment->currency ?: $payment->invoice?->currency ?: $commercial?->currency ?: SettingService::get('currency', 'NGN', $universityId)));
        if ($providerMinor !== $expectedMinor || $providerCurrency !== $expectedCurrency) {
            return response()->json([
                'status' => false,
                'success' => false,
                'code' => 'PAYMENT_VERIFICATION_MISMATCH',
                'message' => 'The payment provider confirmation did not match the expected invoice amount or currency.',
                'retryable' => false,
                'request_id' => UserFacingError::requestId($request),
            ], 422, ['X-Request-Id' => UserFacingError::requestId($request)]);
        }

        $payment = DB::transaction(fn () => $this->payments->verifyPayment($payment, $request->user()));

        return response()->json([
            'message' => 'Payment verified by the configured gateway.',
            'payment' => $payment->fresh(['invoice']),
        ]);
    }

    public function initiatePayment(Request $request)
    {
        $availableCodes = PaymentGateway::query()->where('is_active', true)->pluck('code')->all();
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'gateway' => ['nullable', 'string', Rule::in($availableCodes)],
            'payment_method' => ['nullable', Rule::in(['card', 'bank_transfer'])],
        ]);

        $invoice = Invoice::with('user')->findOrFail($validated['invoice_id']);
        $this->assertInvoiceAccess($request, $invoice);
        abort_if($invoice->status === 'paid', 422, 'This invoice has already been paid.');

        $gatewayCode = (string) ($validated['gateway'] ?? $this->gateways->getDefaultGateway());
        $payment = $this->payments->initiatePayment($invoice, (string) ($validated['payment_method'] ?? 'card'));
        $existingMetadata=json_decode((string)$payment->notes,true)?:[];
        if(($existingMetadata['gateway']??null)===$gatewayCode && !empty($existingMetadata['authorization_url'])){
            return response()->json(['message'=>'Existing pending payment resumed.','payment'=>$payment,'authorization_url'=>$existingMetadata['authorization_url']]);
        }
        $payment->update([
            'notes' => json_encode(array_merge($existingMetadata,['gateway' => $gatewayCode]), JSON_THROW_ON_ERROR),
        ]);

        $result = $this->gateways->gateway($gatewayCode)->initializePayment([
            'email' => $invoice->user?->email ?: $request->user()->email,
            'amount' => Money::fromMinor($payment->amount_minor !== null ? (int) $payment->amount_minor : Money::toMinor((string) $payment->amount)),
            'amount_minor' => $payment->amount_minor !== null ? (int) $payment->amount_minor : Money::toMinor((string) $payment->amount),
            'reference' => $payment->reference,
            'currency' => strtoupper((string) ($payment->currency ?: $invoice->currency ?: SettingService::get('currency', 'NGN', $request->user()->university_id))),
            'callback_url' => url('/billing/payment-return?reference='.$payment->reference),
            'user_id' => $request->user()->id,
            'type' => 'institutional_invoice',
            'metadata' => [
                'payment_uuid' => $payment->uuid,
                'invoice_uuid' => $invoice->uuid,
                'user_id' => $request->user()->id,
            ],
        ]);

        if (! ($result['status'] ?? false)) {
            $retryable = (bool) ($result['retryable'] ?? false);
            $payment->update(['status' => $retryable ? 'pending' : 'failed']);

            return response()->json([
                'status' => false,
                'success' => false,
                'code' => $result['code'] ?? 'PAYMENT_INITIALIZATION_FAILED',
                'message' => $result['message'] ?? 'We could not start the payment right now.',
                // Do not offer a blind client retry for a payment-creation POST.
                // The stored retryable flag is diagnostic; the user should verify
                // the pending payment state before trying to create another one.
                'retryable' => false,
                'request_id' => UserFacingError::requestId($request),
                'payment' => $payment->fresh(),
            ], $retryable ? 503 : 422, ['X-Request-Id' => UserFacingError::requestId($request)]);
        }

        $payment->update([
            'reference' => $result['reference'] ?? $payment->reference,
            'notes' => json_encode(array_merge($existingMetadata,[
                'gateway' => $gatewayCode,
                'access_code' => $result['access_code'] ?? null,
                'authorization_url' => $result['authorization_url'] ?? null,
            ]), JSON_THROW_ON_ERROR),
        ]);

        return response()->json([
            'payment' => $payment->fresh(),
            'payment_url' => $result['authorization_url'],
            'verification_reference' => $payment->reference,
        ], 201);
    }

    public function report(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $query = Payment::query();
        $this->scopeInvoiceQuery($query, $request->user(), 'invoice');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $reportCurrency = strtoupper((string) ($request->string('currency')->toString()
            ?: SettingService::get('currency', 'NGN', $request->user()->university_id)));
        abort_unless((bool) preg_match('/^[A-Z]{3}$/', $reportCurrency), 422, 'Currency must be a three-letter ISO code.');
        $query->where('currency', $reportCurrency);

        $totalMinor = (int) (clone $query)->where('status', 'verified')->sum('amount_minor');
        return [
            'currency' => $reportCurrency,
            'total_amount_minor' => $totalMinor,
            'total_amount' => Money::fromMinor($totalMinor),
            'total_payments' => (clone $query)->count(),
            'verified' => (clone $query)->where('status', 'verified')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }

    private function assertInvoiceAccess(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        if ($invoice->user_id === $user->id) { return; }
        abort_unless($user->isAdmin(), 403);
        $allowed = $this->scopeInvoiceQuery(Invoice::query()->whereKey($invoice->id), $user)->exists();
        abort_unless($allowed, 404);
    }
}
