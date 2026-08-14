<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\UserSubscription;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\SettingService;
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

        $payment = Payment::with('invoice')->where('reference', $validated['reference'])->first();
        abort_if(! $payment, 404, 'Payment not found.');
        abort_unless($request->user()->isAdmin() || $payment->user_id === $request->user()->id, 403);

        if ($payment->status === 'verified') {
            return response()->json(['message' => 'Payment already verified.', 'payment' => $payment]);
        }

        $metadata = json_decode((string) $payment->notes, true) ?: [];
        $gatewayCode = (string) ($metadata['gateway'] ?? $this->gateways->getDefaultGateway());
        $verification = $this->gateways->gateway($gatewayCode)->verifyPayment($payment->reference);

        if (! ($verification['status'] ?? false) || ! ($verification['paid'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'notes' => json_encode(array_merge($metadata, [
                    'last_verification' => now()->toIso8601String(),
                    'verification_message' => $verification['message'] ?? 'Gateway did not confirm payment.',
                ]), JSON_THROW_ON_ERROR),
            ]);

            return response()->json([
                'message' => $verification['message'] ?? 'The gateway did not confirm this payment.',
                'payment' => $payment->fresh(),
            ], 422);
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

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $this->assertInvoiceAccess($request, $invoice);
        abort_if($invoice->status === 'paid', 422, 'This invoice has already been paid.');

        $gatewayCode = (string) ($validated['gateway'] ?? $this->gateways->getDefaultGateway());
        $payment = $this->payments->initiatePayment($invoice, (string) ($validated['payment_method'] ?? 'card'));
        $payment->update([
            'notes' => json_encode(['gateway' => $gatewayCode], JSON_THROW_ON_ERROR),
        ]);

        $result = $this->gateways->gateway($gatewayCode)->initializePayment([
            'email' => $request->user()->email,
            'amount' => (float) $payment->amount,
            'reference' => $payment->reference,
            'currency' => SettingService::get('currency', 'NGN'),
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
            $payment->update(['status' => 'failed']);

            return response()->json([
                'message' => $result['message'] ?? 'Payment initialization failed.',
                'payment' => $payment->fresh(),
            ], 422);
        }

        $payment->update([
            'reference' => $result['reference'] ?? $payment->reference,
            'notes' => json_encode([
                'gateway' => $gatewayCode,
                'access_code' => $result['access_code'] ?? null,
                'authorization_url' => $result['authorization_url'] ?? null,
            ], JSON_THROW_ON_ERROR),
        ]);

        return response()->json([
            'payment' => $payment->fresh(),
            'payment_url' => $result['authorization_url'],
            'verification_reference' => $payment->reference,
        ], 201);
    }

    public function subscriptions(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $query = UserSubscription::with(['university', 'plan']);
        if (! $request->user()->isSuperAdmin()) { $query->where('university_id', $request->user()->university_id); }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query->latest()->paginate(20);
    }

    public function report(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $query = Payment::query();
        $this->scopeInvoiceQuery($query, $request->user(), 'invoice');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return [
            'total_amount' => (clone $query)->where('status', 'verified')->sum('amount'),
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
