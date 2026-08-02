<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['university', 'user']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
    }

    public function show(Invoice $invoice)
    {
        $user = request()->user();
        if (! $user->isAdmin() && $invoice->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $invoice->load(['university', 'user', 'payments']);
    }

    public function payments(Request $request)
    {
        $query = Payment::with(['invoice']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
    }

    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        $payment = Payment::where('reference', $validated['reference'])->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $user = $request->user();
        if (! $user->isAdmin() && $payment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($payment->status === 'verified') {
            return response()->json(['message' => 'Payment already verified', 'payment' => $payment]);
        }

        $payment->update(['status' => 'verified', 'verified_at' => now()]);
        $payment->invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return response()->json(['message' => 'Payment verified', 'payment' => $payment]);
    }

    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $user = $request->user();

        if (! $user->isAdmin() && $invoice->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'reference' => 'PAY-'.strtoupper(uniqid()),
            'amount' => $invoice->amount,
            'status' => 'pending',
        ]);

        return response()->json([
            'payment' => $payment,
            'payment_url' => 'https://payment.gateway.com/pay/'.$payment->reference,
        ]);
    }

    public function subscriptions(Request $request)
    {
        $query = UserSubscription::with(['university', 'plan']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
    }

    public function report(Request $request)
    {
        $query = Payment::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return [
            'total_amount' => $query->sum('amount'),
            'total_payments' => $query->count(),
            'verified' => $query->where('status', 'verified')->count(),
            'pending' => $query->where('status', 'pending')->count(),
        ];
    }
}
